<?php

namespace PostmanGeneratorBundle\Generator;

use Doctrine\Common\Inflector\Inflector;
use Dunglas\ApiBundle\Api\Operation\OperationInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactoryInterface;
use PostmanGeneratorBundle\Model\Folder;
use PostmanGeneratorBundle\Model\Request;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class RequestGenerator implements GeneratorInterface
{
    /**
     * @var AuthenticationGenerator
     */
    private $authenticationGenerator;

    /**
     * @var ClassMetadataFactoryInterface
     */
    private $classMetadataFactory;

    /**
     * @var PropertyAccessor
     */
    private $propertyAccessor;

    /**
     * @var NameConverterInterface
     */
    private $nameConverter;

    /**
     * @var string
     */
    private $authentication;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var Folder
     */
    private $currentFolder;

    /**
     * @param AuthenticationGenerator       $authenticationGenerator
     * @param ClassMetadataFactoryInterface $classMetadataFactory
     * @param PropertyAccessor              $propertyAccessor
     * @param NameConverterInterface        $nameConverter
     * @param string                        $authentication
     * @param string                        $baseUrl
     */
    public function __construct(
        AuthenticationGenerator $authenticationGenerator,
        ClassMetadataFactoryInterface $classMetadataFactory,
        PropertyAccessor $propertyAccessor,
        $baseUrl,
        $authentication = null,
        NameConverterInterface $nameConverter = null
    ) {
        $this->authenticationGenerator = $authenticationGenerator;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->propertyAccessor = $propertyAccessor;
        $this->nameConverter = $nameConverter;
        $this->authentication = $authentication;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @var Folder $folder
     */
    public function setCurrentFolder(Folder $folder)
    {
        $this->currentFolder = $folder;
    }

    /**
     * {@inheritdoc}
     *
     * @return Request[]
     */
    public function generate(ResourceInterface $resource = null)
    {
        /** @var OperationInterface[] $operations */
        $operations = array_merge($resource->getCollectionOperations(), $resource->getItemOperations());
        $requests = [];

        foreach ($operations as $operation) {
            foreach ($operation->getRoute()->getMethods() as $method) {
                $isCollection = 'DunglasApiBundle:Resource:cget' === $operation->getRoute()->getDefault('_controller');
                $name = $this->generateDefaultName($method, $resource->getShortName(), $isCollection);
                if (isset($operation->getContext()['hydra:title'])) {
                    $name = $operation->getContext()['hydra:title'];
                }

                $request = new Request();
                $request->setUrl($this->generateUrl($operation->getRoute()->getPath()));
                $request->setId(md5($method.' '.$request->getUrl()));
                $request->setMethod($method);
                $request->setName($name);

                // @todo Add tests

                // Authentication
                if (null !== $this->authentication) {
                    $this->authenticationGenerator->get($this->authentication)->generate($request);
                }

                // Manage request data & ContentType header
                if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    $request->addHeader('Content-Type', 'application/json');
                    $request->setDataMode(Request::DATA_MODE_RAW);

                    $rawModeData = [];
                    // @todo Externalize this process
                    $classMetadata = $this->classMetadataFactory->getMetadataFor(
                        $resource->getEntityClass(),
                        $resource->getNormalizationGroups(),
                        $resource->getDenormalizationGroups(),
                        $resource->getValidationGroups()
                    );
                    foreach ($classMetadata->getAttributes() as $attributeMetadata) {
                        if ($attributeMetadata->isIdentifier() || !$attributeMetadata->isReadable()) {
                            continue;
                        }

                        // Regular attribute
                        $attributeName = $attributeMetadata->getName();
                        if ($this->nameConverter) {
                            $attributeName = $this->nameConverter->normalize($attributeName);
                        }

                        // @todo Implement faker
                        $value = '';

                        // Association(s)
                        if (isset($attributeMetadata->getTypes()[0])) {
                            $type = $attributeMetadata->getTypes()[0];

                            // @todo Implement faker
                            $value = $type->isCollection() ? [] : '';

                            // @todo According to serialization groups, is it possible to build sub-association from main object ?
//                            if ($subResource = $this->getResourceFromType($type)) {
//                                $value = $this->normalizeRelation($attributeMetadata, $attributeValue, $subResource, $context);
//                            }
                        }

                        $rawModeData[$attributeName] = $value;
                    }
                    $request->setRawModeData($rawModeData);
                }

                $requests[] = $request;
            }
        }

        return $requests;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    private function generateUrl($url)
    {
        return rtrim($this->baseUrl, '/').str_ireplace('{id}', 1, $url);
    }

    /**
     * @param string $method
     * @param string $name
     * @param bool   $isCollection
     *
     * @return string
     */
    private function generateDefaultName($method, $name, $isCollection = true)
    {
        switch ($method) {
            case 'POST':
                return sprintf('Create %s', Inflector::camelize($name));
            case 'PUT':
            case 'PATCH':
                return sprintf('Update %s', Inflector::camelize($name));
            case 'DELETE':
                return sprintf('Delete %s', Inflector::camelize($name));
            case 'GET':
                if ($isCollection) {
                    return sprintf('Get %s list', Inflector::pluralize(Inflector::camelize($name)));
                }

                return sprintf('Get %s', Inflector::camelize($name));
        }
    }
}