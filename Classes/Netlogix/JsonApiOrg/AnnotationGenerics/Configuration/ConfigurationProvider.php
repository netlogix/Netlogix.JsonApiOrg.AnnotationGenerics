<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Configuration;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;
use Netlogix\JsonApiOrg\Schema\Relationships;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\TypeHandling;

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
    ];

    /**
     * @param mixed $objectOrObjectType
     * @return array|null
     */
    public function getSettingsForType($objectOrObjectType)
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

    /**
     * @param string $type
     * @return array
     */
    protected function fetchYamlBasedConfiguration($type)
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
    protected function applyAnnotationBasedConfiguration($type, array $settings = [])
    {
        $flatType = trim(strrchr($type, '\\'), '\\');

        foreach ($this->reflectionService->getPropertyNamesByAnnotation($type, JsonApi\ExposeProperty::class) as $propertyName) {
            /** @var JsonApi\ExposeProperty $annotation */
            $annotation = $this->reflectionService->getPropertyAnnotation($type, $propertyName, JsonApi\ExposeProperty::class);
            $targetType = $this->reflectionService->getPropertyTagValues($type, $propertyName, 'var')[0];
            $settings = $this->applyAnnotationBasedConfigurationForProperty($propertyName, $annotation, $targetType, $settings);
        }

        foreach (get_class_methods($type) as $methodName) {
            if ((substr($methodName, 0, 3) !== 'get' && substr($methodName, 0, 2) !== 'is' && substr($methodName, 0, 3) !== 'has') || !is_callable(array($type, $methodName))) {
                continue;
            }

            /** @var JsonApi\ExposeProperty $annotation */
            $annotation = $this->reflectionService->getMethodAnnotation($type, $methodName, JsonApi\ExposeProperty::class);
            if ($annotation === null) {
                continue;
            }
            $targetType = $this->reflectionService->getMethodTagsValues($type, $methodName)['return'][0];
            $settings = $this->applyAnnotationBasedConfigurationForProperty(lcfirst(ltrim($methodName, 'getisha')), $annotation, $targetType, $settings);
        }

        /** @var JsonApi\ExposeType $annotation */
        foreach ($this->reflectionService->getClassAnnotations($type, JsonApi\ExposeType::class) as $annotation) {
            foreach (['packageKey', 'subPackageKey', 'controllerName', 'actionName', 'argumentName', 'private'] as $setting) {
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

    /**
     * @param string $propertyName
     * @param JsonApi\ExposeProperty $annotation
     * @param string $type
     * @param array $settings
     * @return array
     */
    protected function applyAnnotationBasedConfigurationForProperty($propertyName, $annotation, $type, array $settings) {
        $targetType = TypeHandling::parseType($type);
        $isCollection = (bool)$targetType['elementType'];
        $elementType = $isCollection ? $targetType['elementType'] : $targetType['type'];
        $isSimpleType = TypeHandling::isSimpleType($elementType);

        if ($annotation->exposeAsAttribute) {
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