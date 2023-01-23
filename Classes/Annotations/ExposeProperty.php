<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Each property meant to be exposed either as an attribute or as a relationship
 * has to be flagged with this annotation. Depending on the "@var" of the
 * corresponding property, it is either used as an attribute or a relationship.
 *
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 */
#[\Attribute(\Attribute::TARGET_METHOD|\Attribute::TARGET_PROPERTY)]
final class ExposeProperty
{
    public function __construct(
        public readonly bool $exposeAsAttribute = false,
        public readonly mixed $defaultValue = null
    ) {
    }
}