<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Neos\Flow\Annotations as Flow;

/**
 * A model class which should be available as an api resource needs this
 * annotation.
 *
 * @Annotation
 * @Target("CLASS")
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
#[Flow\Proxy(false)]
final class ExposeType
{
    public function __construct(
        public readonly ?string $typeName = null,

        public readonly ?string $requestPackageKey = null,

        public readonly ?string $requestControllerName = null,

        public readonly ?string $requestSubPackageKey = null,

        public readonly ?string $requestActionName = null,

        public readonly ?string $requestArgumentName = null,

        public readonly ?bool $private = null,
    ) {
    }

    public function toArray(bool $skipNull = true): array
    {
        $result = [
            'typeName' => $this->typeName,
            'requestPackageKey' => $this->requestPackageKey,
            'requestControllerName' => $this->requestControllerName,
            'requestSubPackageKey' => $this->requestSubPackageKey,
            'requestActionName' => $this->requestActionName,
            'requestArgumentName' => $this->requestArgumentName,
            'private' => $this->private,
        ];
        if ($skipNull) {
            $result = array_filter($result, fn($v) => $v !== null);
        }
        return $result;
    }
}
