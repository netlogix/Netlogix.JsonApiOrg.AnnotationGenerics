<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

/**
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 */
#[\Attribute(\Attribute::TARGET_METHOD|\Attribute::TARGET_PROPERTY)]
final class ExposeCollection
{
    public function __construct(
        public readonly string $targetType
    ) {
    }
}