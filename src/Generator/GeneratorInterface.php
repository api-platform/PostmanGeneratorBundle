<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PostmanGeneratorBundle\Generator;

use Dunglas\ApiBundle\Api\ResourceInterface;

interface GeneratorInterface
{
    /**
     * @param ResourceInterface $resource
     *
     * @return mixed
     */
    public function generate(ResourceInterface $resource = null);
}
