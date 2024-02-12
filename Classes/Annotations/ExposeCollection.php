<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_METHOD| Attribute::TARGET_PROPERTY)]
final class ExposeCollection
{
    public function __construct(
        public readonly string $targetType
    ) {
    }
}