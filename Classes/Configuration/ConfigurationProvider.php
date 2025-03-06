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
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;
use Netlogix\JsonApiOrg\Schema\Relationships;

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
     * @var array
     */
    protected $runtimeCache = [];

    /**
     * @var array
     */
    protected $defaultConfigurationSchema = [
        'argumentName' => '',
        'actionName' => '',
        'controllerName' => '',
        'packageKey' => '',
        'subPackageKey' => null,
        'private' => false,
        'attributesToBeApiExposed' => [],
        'relationshipsToBeApiExposed' => [],
        'identityAttributes' => [],
    ];

    /**
     * @param $objectOrObjectType
     * @return array
     * @throws InvalidTypeException
     */
    public function getSettingsForType($objectOrObjectType): array
    {
        if (!$objectOrObjectType) {
            return $this->defaultConfigurationSchema;
        } elseif (is_object($objectOrObjectType)) {
            $type = TypeHandling::getTypeForValue($objectOrObjectType);
        } else {
            $type = $objectOrObjectType;
        }

        if (!array_key_exists($type, $this->runtimeCache)) {
            $settings = $this->fetchYamlBasedConfiguration($type);
            $settings = $this->applyAnnotationBasedConfiguration($type, $settings);
            $this->runtimeCache[$type] = $settings;
        }
        return $this->runtimeCache[$type];
    }

    protected function fetchYamlBasedConfiguration(string $type): array
    {
        $parentConfiguration = $this->getSettingsForType(get_parent_class($type));
        if (isset($this->exposingConfiguration[$type])) {
            return array_merge($parentConfiguration, $this->exposingConfiguration[$type]);
        } else {
            return $parentConfiguration;
        }
    }

    /**
     * @param string $type
     * @param array $settings
     * @return array
     * @throws InvalidTypeException
     */
    protected function applyAnnotationBasedConfiguration(string $type, array $settings = []): array
    {
        $flatType = trim(strrchr($type, '\\'), '\\');
        // unproxied className
        $className = preg_replace('/' . Compiler::ORIGINAL_CLASSNAME_SUFFIX . '$/', '', $type);

        $reflection = $this->reflectionService;

        foreach ($reflection->getPropertyNamesByAnnotation($className, JsonApi\ExposeProperty::class) as $propertyName) {
            $annotation = $reflection->getPropertyAnnotation($className, $propertyName, JsonApi\ExposeProperty::class);
            assert($annotation instanceof JsonApi\ExposeProperty);
            $propertyTagValues = $reflection->getPropertyTagValues($className, $propertyName, 'var');
            if (array_key_exists(0, $propertyTagValues)) {
                $targetType = $propertyTagValues[0];
            } else {
                $targetType = $reflection->getPropertyType($className, $propertyName);
            }
            $settings = $this->applyAnnotationBasedConfigurationForProperty(
                $propertyName,
                $annotation->exposeAsAttribute,
                $targetType,
                $settings
            );
        }

        foreach ($reflection->getPropertyNamesByAnnotation($className, JsonApi\Identity::class) as $propertyName) {
            $settings['identityAttributes'][$propertyName] = $propertyName;
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

            $annotation = $reflection->getMethodAnnotation($className, $methodName, JsonApi\ExposeProperty::class);
            if ($annotation !== null) {
                assert($annotation instanceof JsonApi\ExposeProperty);

                $targetType = $reflection->getMethodDeclaredReturnType($className, $methodName);
                if (is_subclass_of($targetType, Collection::class) || $targetType === null) {
                    $targetType = $reflection->getMethodTagsValues($className, $methodName)['return'][0] ?: '';
                }
                $settings = $this->applyAnnotationBasedConfigurationForProperty(
                    $propertyName,
                    $annotation->exposeAsAttribute,
                    $targetType,
                    $settings
                );
            }

            $annotation = $reflection->getMethodAnnotation($className, $methodName, JsonApi\ExposeCollection::class);
            if ($annotation !== null) {
                assert($annotation instanceof JsonApi\ExposeCollection);

                $declaredReturnType = $reflection->getMethodDeclaredReturnType($className, $methodName);
                $targetType = sprintf('%s<%s>', $declaredReturnType, $annotation->targetType);
                $settings = $this->applyAnnotationBasedConfigurationForProperty(
                    $propertyName,
                    false,
                    $targetType,
                    $settings
                );
            }

            $annotation = $reflection->getMethodAnnotation($className, $methodName, JsonApi\Identity::class);
            if ($annotation !== null) {
                assert($annotation instanceof JsonApi\Identity);
                $settings['identityAttributes'][$propertyName] = $propertyName;
            }
        }

        foreach ($reflection->getClassAnnotations($className, JsonApi\ExposeType::class) as $annotation) {
            assert($annotation instanceof JsonApi\ExposeType);
            foreach ([
                         'packageKey',
                         'subPackageKey',
                         'controllerName',
                         'actionName',
                         'argumentName',
                         'private'
                     ] as $setting) {
                if ((!array_key_exists($setting, $settings) || !$settings[$setting]) && $annotation->{$setting}) {
                    $settings[$setting] = $annotation->{$setting};
                } elseif (!array_key_exists($setting, $settings)) {
                    $settings[$setting] = null;
                }
            }
            if (!$settings['controllerName']) {
                $settings['controllerName'] = $flatType;
            }
            if (!$settings['argumentName']) {
                $settings['argumentName'] = lcfirst($flatType);
            }

        }

        return $settings;
    }

    protected function applyAnnotationBasedConfigurationForProperty(
        string $propertyName,
        bool $exposeAsAttribute,
        string $type,
        array $settings
    ): array {
        $targetType = TypeHandling::parseType($type);
        $isCollection = (bool)$targetType['elementType'];
        $elementType = $isCollection ? $targetType['elementType'] : $targetType['type'];
        $isSimpleType = TypeHandling::isSimpleType($elementType);

        if ($exposeAsAttribute) {
            $settings['attributesToBeApiExposed'][$propertyName] = $propertyName;

        } elseif ($isSimpleType) {
            $settings['attributesToBeApiExposed'][$propertyName] = $propertyName;

        } elseif (!$isSimpleType && !$this->resourceMapper->findResourceInformation($elementType)) {
            $settings['attributesToBeApiExposed'][$propertyName] = $propertyName;

        } elseif ($isCollection) {
            $settings['relationshipsToBeApiExposed'][$propertyName] = Relationships::RELATIONSHIP_TYPE_COLLECTION;

        } else {
            $settings['relationshipsToBeApiExposed'][$propertyName] = Relationships::RELATIONSHIP_TYPE_SINGLE;

        }

        return $settings;
    }
}