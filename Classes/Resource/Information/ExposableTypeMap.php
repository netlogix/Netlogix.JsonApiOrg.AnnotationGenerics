<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Resource\Information;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Collections\Collection;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\Resource\Information\ExposableType;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMap as BaseExposableTypeMap;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Psr\Log\LoggerInterface;

use function array_values;

/**
 * @Flow\Scope("singleton")
 */
class ExposableTypeMap extends BaseExposableTypeMap implements ExposableTypeMapInterface
{

    const PATTERN = '%^(?<vendor>[^\\\\]+)\\\\(?<package>[^\\\\]+)\\\\(?<subpackage>.+)?\\\\domain\\\\(?<type>model|command)\\\\(?<flat>.*)$%i';

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var LoggerInterface
     * @Flow\Inject(name="Neos.Flow:SystemLogger")
     */
    protected $psrSystemLoggerInterface;

    /**
     * All "ExposeType" objects are initialized automatically
     */
    public function initializeObject()
    {
        list(
            'exposableTypes' => $exposableTypes,
            'classNameToPropertyNamesMap' => $classNameToPropertyNamesMap,
            'classNameToMethodNamesMap' => $classNameToMethodNamesMap,
            ) = static::collectKnownTypes($this->objectManager);

        foreach ($exposableTypes as $exposableType) {
            $this->registerExposableType($exposableType);
        }

        foreach ($classNameToPropertyNamesMap as $className => $properties) {
            $exposableType = $this->getExposableTypeByClassIdentifier($className);
            foreach ($properties as $propertyName => $propertyVarType) {
                try {
                    $this->registerKnownPropertyType(
                        exposableType: $exposableType,
                        propertyName: $propertyName,
                        varType: $propertyVarType
                    );
                } catch (\Exception $e) {
                    $this->psrSystemLoggerInterface->error('Could not register known property type for type', [
                        'className' => $className,
                        'propertyName' => $propertyName,
                        'propertyVarType' => $propertyVarType,
                    ]);
                }
            }
        }

        foreach ($classNameToMethodNamesMap as $className => $methods) {
            $exposableType = $this->getExposableTypeByClassIdentifier($className);
            foreach ($methods as $methodName => $methodVarType) {
                try {
                    $this->registerKnownPropertyType(
                        exposableType: $exposableType,
                        propertyName: $methodName,
                        varType: $methodVarType
                    );
                } catch (\Exception $e) {
                    $this->psrSystemLoggerInterface->error('Could not register known property type for type', [
                        'className' => $className,
                        'methodName' => $methodName,
                        'methodVarType' => $methodVarType,
                    ]);
                }
            }
        }
    }

    protected function registerKnownPropertyType(ExposableType $exposableType, string $propertyName, string $varType)
    {
        $varType = TypeHandling::parseType($varType);
        $isCollection = (bool)$varType['elementType'];
        $elementType = $isCollection ? $varType['elementType'] : $varType['type'];

        if ($isCollection) {
            $this->registerExposableTypeProperty($exposableType, $propertyName, 'array<' . $elementType . '>');
        } else {
            $this->registerExposableTypeProperty($exposableType, $propertyName, $elementType);
        }
    }

    protected static function guessTypeNameFromClassName(PackageManager $packageManager, string $className): string
    {
        $typeComponents = preg_split('%\\\\Domain\\\\(Model|Command)\\\\%i', $className, 2);
        $typeComponents[0] = explode('\\', $typeComponents[0]);
        while ($typeComponents[0] && !$packageManager->isPackageAvailable(
                join(
                    '.',
                    $typeComponents[0]
                )
            )) {
            unset($typeComponents[0][count($typeComponents[0]) - 1]);
        }
        return strtolower(end($typeComponents[0]) . '/' . str_replace('\\', '.', $typeComponents[1]));
    }

    /**
     * This method is compiled statically, as the ReflectionService should not be used in Production context.
     * The cached variant of the ReflectionService is missing at least the "methods annotated with".
     *
     * @Flow\CompileStatic
     * @param ObjectManagerInterface $objectManager
     * @return ExposableType[]
     */
    protected static function collectKnownTypes(ObjectManagerInterface $objectManager): array
    {
        $exposableTypes = [];
        $classNameToPropertyNamesMap = [];
        $classNameToMethodNamesMap = [];

        $reflectionService = $objectManager->get(ReflectionService::class);
        $packageManager = $objectManager->get(PackageManager::class);
        $configurationProvider = $objectManager->get(ConfigurationProvider::class);

        $exposedTypes = $reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class);
        foreach ($exposedTypes as $className) {
            $configuration = $configurationProvider->getSettingsForType($className);
            $annotations = $reflectionService->getClassAnnotations($className, JsonApi\ExposeType::class);

            foreach ($annotations as $annotation) {
                assert($annotation instanceof JsonApi\ExposeType);

                $exposableType = new ExposableType(
                    className: $configuration->className,
                    typeName: $annotation->typeName ?: self::guessTypeNameFromClassName($packageManager, $className),
                    apiVersion: $annotation->apiVersion ?: ExposableTypeMapInterface::NEXT_VERSION,
                    replaces: $annotation->replaces
                );

                $exposableTypes[$exposableType->className] = $exposableType;
                $classNameToPropertyNamesMap[$exposableType->className] = [];
                $classNameToMethodNamesMap[$exposableType->className] = [];

                $propertyNames = $reflectionService
                    ->getPropertyNamesByAnnotation($exposableType->className, JsonApi\ExposeProperty::class);
                array_walk(
                    $propertyNames,
                    function (string $propertyName) use (
                        $exposableType,
                        $reflectionService,
                        &
                        $classNameToPropertyNamesMap
                    ) {
                        $propertyTagValues = $reflectionService->getPropertyTagValues(
                            $exposableType->className,
                            $propertyName,
                            'var'
                        );
                        if (array_key_exists(0, $propertyTagValues)) {
                            $typeName = $propertyTagValues[0] ?: '';
                        } else {
                            $typeName = $reflectionService->getPropertyType($exposableType->className, $propertyName);
                        }
                        $classNameToPropertyNamesMap[$exposableType->className][$propertyName] = $typeName;
                    }
                );

                $methodNames = $reflectionService
                    ->getMethodsAnnotatedWith($exposableType->className, JsonApi\ExposeProperty::class);
                array_walk(
                    $methodNames,
                    function (string $methodName) use (
                        $exposableType,
                        $reflectionService,
                        &$classNameToMethodNamesMap
                    ) {
                        $methodNameLength = strlen($methodName);
                        if ($methodNameLength > 2 && substr($methodName, 0, 2) === 'is') {
                            $propertyName = lcfirst(substr($methodName, 2));
                        } elseif ($methodNameLength > 3 && (($methodNamePrefix = substr(
                                    $methodName,
                                    0,
                                    3
                                )) === 'get' || $methodNamePrefix === 'has')) {
                            $propertyName = lcfirst(substr($methodName, 3));
                        } else {
                            return;
                        }

                        $declaredReturnType = $reflectionService->getMethodDeclaredReturnType(
                            $exposableType->className,
                            $methodName
                        );
                        if (is_subclass_of($declaredReturnType, Collection::class) || $declaredReturnType === null) {
                            $declaredReturnType = $reflectionService->getMethodTagsValues(
                                $exposableType->className,
                                $methodName
                            )['return'][0] ?: '';
                        }
                        $classNameToMethodNamesMap[$exposableType->className][$propertyName] = $declaredReturnType;
                    }
                );

                $methodNames = $reflectionService
                    ->getMethodsAnnotatedWith($exposableType->className, JsonApi\ExposeCollection::class);
                array_walk(
                    $methodNames,
                    function (string $methodName) use (
                        $exposableType,
                        $reflectionService,
                        &$classNameToMethodNamesMap
                    ) {
                        $methodNameLength = strlen($methodName);
                        if ($methodNameLength > 2 && substr($methodName, 0, 2) === 'is') {
                            $propertyName = lcfirst(substr($methodName, 2));
                        } elseif ($methodNameLength > 3 && (($methodNamePrefix = substr(
                                    $methodName,
                                    0,
                                    3
                                )) === 'get' || $methodNamePrefix === 'has')) {
                            $propertyName = lcfirst(substr($methodName, 3));
                        } else {
                            return;
                        }

                        $annotation = $reflectionService->getMethodAnnotation(
                            $exposableType->className,
                            $methodName,
                            JsonApi\ExposeCollection::class
                        );
                        $declaredReturnType = $reflectionService->getMethodDeclaredReturnType(
                            $exposableType->className,
                            $methodName
                        );
                        $classNameToMethodNamesMap[$exposableType->className][$propertyName] = sprintf(
                            '%s<%s>',
                            $declaredReturnType,
                            $annotation->targetType
                        );
                    }
                );
            }
        }

        return [
            'exposableTypes' => array_values($exposableTypes),
            'classNameToPropertyNamesMap' => $classNameToPropertyNamesMap,
            'classNameToMethodNamesMap' => $classNameToMethodNamesMap,
        ];
    }

}