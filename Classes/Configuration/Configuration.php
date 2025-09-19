<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Configuration;

use JsonSerializable;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final class Configuration
{
    private function __construct(
        public readonly string $typeName,
        public readonly string $packageKey,
        public readonly ?string $subPackageKey,
        public readonly string $controllerName,
        public readonly string $actionName,
        public readonly string $argumentName,
        public readonly bool $private,
        public readonly array $identityAttributes,
        public readonly array $attributesToBeApiExposed,
        public readonly array $relationshipsToBeApiExposed,
    ) {
        /**
         * TODO: Validate:
         * - $identityAttributes
         * - $attributesToBeApiExposed
         * - $relationshipsToBeApiExposed
         */
    }

    public static function create(): static
    {
        return new static(
            typeName: '',
            packageKey: 'Netlogix.WebApi',
            subPackageKey: null,
            controllerName: 'GenericModel',
            actionName: 'index',
            argumentName: 'resource',
            private: false,
            identityAttributes: [],
            attributesToBeApiExposed: [],
            relationshipsToBeApiExposed: [],
        );
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function with(string $key, mixed $value): static
    {
        $array = $this->toArray();
        $array[$key] = $value;
        return new static(...$array);
    }

    public function toArray(): array
    {
        return [
            'typeName' => $this->typeName,
            'packageKey' => $this->packageKey,
            'subPackageKey' => $this->subPackageKey,
            'controllerName' => $this->controllerName,
            'actionName' => $this->actionName,
            'argumentName' => $this->argumentName,
            'private' => $this->private,
            'identityAttributes' => $this->identityAttributes,
            'attributesToBeApiExposed' => $this->attributesToBeApiExposed,
            'relationshipsToBeApiExposed' => $this->relationshipsToBeApiExposed,
        ];
    }
}