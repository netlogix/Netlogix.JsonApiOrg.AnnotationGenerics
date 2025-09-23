<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Configuration;

use Neos\Flow\Annotations as Flow;

use function array_intersect_key;
use function current;
use function preg_match;
use function preg_split;

#[Flow\Proxy(false)]
final class Configuration
{
    private const TYPE_NAME_PATTERN = '%^(?<subPackage>[^/]+)/(?<resourceType>.+)$%';

    public readonly string $foo;

    private function __construct(
        public readonly string $className,
        public readonly string $typeName,
        public readonly string $requestPackageKey,
        public readonly ?string $requestSubPackageKey,
        public readonly string $requestControllerName,
        public readonly string $requestActionName,
        public readonly string $argumentName,
        public readonly bool $private,
        public readonly array $identityAttributes,
        public readonly array $attributesToBeApiExposed,
        public readonly array $relationshipsToBeApiExposed,
        public readonly ?string $apiVersion,
    ) {
        /**
         * TODO: Validate:
         * - $identityAttributes
         * - $attributesToBeApiExposed
         * - $relationshipsToBeApiExposed
         */
    }

    public static function create(string $className): static
    {
        return new static(
            className: $className,
            typeName: '',
            requestPackageKey: 'Netlogix.WebApi',
            requestSubPackageKey: null,
            requestControllerName: 'GenericModel',
            requestActionName: 'index',
            argumentName: 'resource',
            private: false,
            identityAttributes: [],
            attributesToBeApiExposed: [],
            relationshipsToBeApiExposed: [],
            apiVersion: null,
        );
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function with(string $key, mixed $value): static
    {
        if ($key === 'className' && $value !== $this->className) {
            throw new \InvalidArgumentException(
                \vsprintf(
                    'Class name (%s) must not be changed (to %s)',
                    [$this->className, $value]
                ),
                1758288139
            );
        }
        $array = $this->toArray();
        $array[$key] = $value;
        return new static(...$array);
    }

    public function getModelPackageKey(): string
    {
        return str_replace('\\', '.', current(preg_split('%\\\\(Domain|Model)\\\\%i', $this->className, 2)));
    }

    public function toArray(): array
    {
        return [
            'className' => $this->className,
            'typeName' => $this->typeName,
            'requestPackageKey' => $this->requestPackageKey,
            'requestSubPackageKey' => $this->requestSubPackageKey,
            'requestControllerName' => $this->requestControllerName,
            'requestActionName' => $this->requestActionName,
            'argumentName' => $this->argumentName,
            'private' => $this->private,
            'identityAttributes' => $this->identityAttributes,
            'attributesToBeApiExposed' => $this->attributesToBeApiExposed,
            'relationshipsToBeApiExposed' => $this->relationshipsToBeApiExposed,
            'apiVersion' => $this->apiVersion,
        ];
    }

    public function getRequestArgumentPointer(): array
    {
        $arguments = [];
        if (preg_match(self::TYPE_NAME_PATTERN, (string)$this->typeName, $matches)) {
            $arguments = array_intersect_key($matches, ['subPackage' => true, 'resourceType' => true]);
        }
        if ($this->apiVersion) {
            $arguments['apiVersion'] = $this->apiVersion;
        }
        return $arguments;
    }
}