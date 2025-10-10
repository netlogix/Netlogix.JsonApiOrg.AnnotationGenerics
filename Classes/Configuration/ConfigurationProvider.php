<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Configuration;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Collections\Collection;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\Proxy\Compiler;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;
use Netlogix\JsonApiOrg\Schema\Relationships;

use function str_ends_with;

/**
 * @Flow\Scope("singleton")
 */
class ConfigurationProvider
{
    /**
     * @var ReflectionService
     * @Flow\Inject
     */
    protected $reflectionService;

    /**
     * @var ResourceMapper
     * @Flow\Inject
     */
    protected $resourceMapper;

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Netlogix.JsonApiOrg", path="exposingConfiguration")
     */
    protected $exposingConfiguration = [];

    /**
     * @var Configuration[]
     */
    protected array $runtimeCache = [];

    public function getSettingsForType(mixed $objectOrObjectType): Configuration
    {
        if (!$objectOrObjectType) {
            return Configuration::create('null');
        } elseif (is_object($objectOrObjectType)) {
            $type = TypeHandling::getTypeForValue($objectOrObjectType);
        } else {
            $type = $objectOrObjectType;
        }

        if (!array_key_exists($type, $this->runtimeCache)) {
            $settings = Configuration::create($type);
            $settings = $this->mergeWithParentConfiguration($settings, $type);
            $settings = $this->mergeWithYamlBasedConfiguration($settings, $type);
            $settings = $this->mergeWithAnnotationBasedConfiguration($settings, $type);
            $this->runtimeCache[$type] = $settings;
        }
        return $this->runtimeCache[$type];
    }

    protected function mergeWithParentConfiguration(Configuration $settings, string $type): Configuration
    {
        $parent = (string)get_parent_class($type);
        if (str_ends_with($parent, Compiler::ORIGINAL_CLASSNAME_SUFFIX)) {
            $parent = (string)get_parent_class($parent);
        }
        if (!$parent) {
            return $settings;
        }

        $parentSettings = $this->getSettingsForType($parent);
        foreach ($parentSettings->toArray() as $key => $value) {
            if ($value !== null && $key !== 'className') {
                $settings = $settings->with($key, $value);
            }
        }
        return $settings;
    }

    protected function mergeWithYamlBasedConfiguration(Configuration $settings, string $type): Configuration
    {
        $yamlBased = $this->exposingConfiguration[$type] ?? [];
        foreach ($yamlBased as $key => $value) {
            $settings = $settings->with($key, $value);
        }
        return $settings;
    }

    protected function mergeWithAnnotationBasedConfiguration(Configuration $settings, string $type): Configuration
    {
        $flatType = trim(strrchr($type, '\\'), '\\');
        // unproxied className
        $className = preg_replace('/' . Compiler::ORIGINAL_CLASSNAME_SUFFIX . '$/', '', $type);

        $reflection = $this->reflectionService;

        $propertyNames = $reflection->getPropertyNamesByAnnotation($className, JsonApi\ExposeProperty::class);
        foreach ($propertyNames as $propertyName) {
            $annotation = $reflection->getPropertyAnnotation($className, $propertyName, JsonApi\ExposeProperty::class);
            assert($annotation instanceof JsonApi\ExposeProperty);
            $propertyTagValues = $reflection->getPropertyTagValues($className, $propertyName, 'var');
            if (array_key_exists(0, $propertyTagValues)) {
                $targetType = $propertyTagValues[0];
            } else {
                $targetType = $reflection->getPropertyType($className, $propertyName);
            }
            $settings = $this->applyAnnotationBasedConfigurationForProperty(
                settings: $settings,
                type: $targetType,
                propertyName: $propertyName,
                exposeAsAttribute: $annotation->exposeAsAttribute
            );
        }

        $propertyNames = $reflection->getPropertyNamesByAnnotation($className, JsonApi\Identity::class);
        foreach ($propertyNames as $propertyName) {
            $settings = $settings->with('identityAttributes', [
                ... $settings->identityAttributes,
                $propertyName => $propertyName,
            ]);
        }

        foreach (get_class_methods($className) as $methodName) {
            if (
                (substr($methodName, 0, 3) !== 'get'
                    && substr($methodName, 0, 2) !== 'is'
                    && substr($methodName, 0, 3) !== 'has'
                )
            ) {
                continue;
            }
            $propertyName = lcfirst(ltrim($methodName, 'getisha'));

            $exposeProperties = $reflection->getMethodAnnotations(
                $className,
                $methodName,
                JsonApi\ExposeProperty::class
            );
            foreach ($exposeProperties as $exposeProperty) {
                assert($exposeProperty instanceof JsonApi\ExposeProperty);

                $targetType = $reflection->getMethodDeclaredReturnType($className, $methodName);
                if (is_subclass_of($targetType, Collection::class) || $targetType === null) {
                    $targetType = $reflection->getMethodTagsValues($className, $methodName)['return'][0] ?: '';
                }
                $settings = $this->applyAnnotationBasedConfigurationForProperty(
                    settings: $settings,
                    type: $targetType,
                    propertyName: $propertyName,
                    exposeAsAttribute: $exposeProperty->exposeAsAttribute,
                );
            }

            $exposeCollections = $reflection->getMethodAnnotations(
                $className,
                $methodName,
                JsonApi\ExposeCollection::class
            );
            foreach ($exposeCollections as $exposeCollection) {
                assert($exposeCollection instanceof JsonApi\ExposeCollection);

                $declaredReturnType = $reflection->getMethodDeclaredReturnType($className, $methodName);
                $targetType = sprintf('%s<%s>', $declaredReturnType, $exposeCollection->targetType);
                $settings = $this->applyAnnotationBasedConfigurationForProperty(
                    settings: $settings,
                    type: $targetType,
                    propertyName: $propertyName,
                    exposeAsAttribute: false
                );
            }

            $annotation = $reflection->getMethodAnnotation($className, $methodName, JsonApi\Identity::class);
            if ($annotation !== null) {
                assert($annotation instanceof JsonApi\Identity);
                $settings = $settings->with('identityAttributes', [
                    ... $settings->identityAttributes,
                    $propertyName => $propertyName,
                ]);
            }
        }

        $exposeTypes = $reflection->getClassAnnotations($className, JsonApi\ExposeType::class);
        foreach ($exposeTypes as $annotation) {
            assert($annotation instanceof JsonApi\ExposeType);
            foreach (
                $annotation->toArray(skipNull: true) as $setting => $value
            ) {
                $settings = $settings->with($setting, $annotation->{$setting});
            }
            if (!$settings->requestControllerName) {
                $settings = $settings->with('controllerName', $flatType);
            }
            if (!$settings->argumentName) {
                $settings = $settings->with('argumentName', lcfirst($flatType));
            }
        }

        return $settings;
    }

    protected function applyAnnotationBasedConfigurationForProperty(
        Configuration $settings,
        string $type,
        string $propertyName,
        bool $exposeAsAttribute,
    ): Configuration {
        [
            'isSimpleType' => $isSimpleType,
            'isCollection' => $isCollection,
            'elementType' => $elementType,
        ] = self::typeHandling($type);

        if ($exposeAsAttribute
            || $isSimpleType
            || (!$isSimpleType && !$this->resourceMapper->findResourceInformation($elementType))
        ) {
            $settings = $settings->with('attributesToBeApiExposed', [
                ... $settings->attributesToBeApiExposed,
                $propertyName => $propertyName,
            ]);
        } elseif ($isCollection) {
            $settings = $settings->with('relationshipsToBeApiExposed', [
                ... $settings->relationshipsToBeApiExposed,
                $propertyName => Relationships::RELATIONSHIP_TYPE_COLLECTION,
            ]);
        } else {
            $settings = $settings->with('relationshipsToBeApiExposed', [
                ... $settings->relationshipsToBeApiExposed,
                $propertyName => Relationships::RELATIONSHIP_TYPE_SINGLE,
            ]);
        }

        return $settings;
    }

    protected static function typeHandling(string $type): array
    {
        $targetType = TypeHandling::parseType($type);
        $isCollection = (bool)$targetType['elementType'];
        $elementType = $isCollection ? $targetType['elementType'] : $targetType['type'];
        $isSimpleType = TypeHandling::isSimpleType($elementType);
        return ['isSimpleType' => $isSimpleType, 'isCollection' => $isCollection, 'elementType' => $elementType];
    }
}