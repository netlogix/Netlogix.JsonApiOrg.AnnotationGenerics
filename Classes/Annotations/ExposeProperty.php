<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Each property meant to be exposed either as an attribute or as a relationship
 * has to be flagged with this annotation. Depending on the "@var" of the
 * corresponding property, it is either used as an attribute or a relationship.
 *
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_METHOD| Attribute::TARGET_PROPERTY)]
final class ExposeProperty
{
    public function __construct(
        public readonly bool $exposeAsAttribute = false,
        public readonly mixed $defaultValue = null
    ) {
    }
}