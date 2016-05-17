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
use Netlogix\JsonApiOrg\Schema\Relationships;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Utility\Exception\InvalidTypeException;
use TYPO3\Flow\Utility\TypeHandling;

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
        'controllerName' => '',
        'packageKey' => '',
        'subPackageKey' => null,
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
            $targetType = TypeHandling::parseType($this->reflectionService->getPropertyTagValues($type, $propertyName, 'var')[0]);
            if ($annotation->exposeAsAttribute) {
                $settings['attributesToBeApiExposed'][$propertyName] = $propertyName;

            } elseif ($targetType['type'] === 'array' && !TypeHandling::isSimpleType($targetType['elementType'])) {
                $settings['relationshipsToBeApiExposed'][$propertyName] = Relationships::RELATIONSHIP_TYPE_COLLECTION;

            } elseif (TypeHandling::isSimpleType($targetType['type'])) {
                $settings['attributesToBeApiExposed'][$propertyName] = $propertyName;

            } else {
                $settings['relationshipsToBeApiExposed'][$propertyName] = Relationships::RELATIONSHIP_TYPE_SINGLE;

            }
        }

        /** @var JsonApi\ExposeType $annotation */
        foreach ($this->reflectionService->getClassAnnotations($type, JsonApi\ExposeType::class) as $annotation) {
            foreach (['packageKey', 'subPackageKey', 'controllerName', 'argumentName'] as $setting) {
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
}