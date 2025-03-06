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
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class ExposableTypeMap extends \Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMap implements ExposableTypeMapInterface
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
            $oneToOneTypeToClassMap,
            $classNameToPropertyNamesMap,
            $classNamesToMethodNamesMap
        ) = static::collectKnownTypes($this->objectManager);

        $this->oneToOneTypeToClassMap = array_merge($this->oneToOneTypeToClassMap, $oneToOneTypeToClassMap);

        foreach ($classNameToPropertyNamesMap as $className => $properties) {
            foreach ($properties as $propertyName => $propertyVarType)
                try {
                    $this->registerKnownPropertyType(
                        $oneToOneTypeToClassMap[$className],
                        $propertyName,
                        $propertyVarType
                    );
                } catch (\Exception $e) {
                    $this->psrSystemLoggerInterface->error('Could not register known property type for type', [
                        'className' => $className,
                        'propertyName' => $propertyName,
                        'propertyVarType' => $propertyVarType,
                    ]);
                }
        }

        foreach ($classNamesToMethodNamesMap as $className => $methods) {
            foreach ($methods as $methodName => $methodVarType) {
                try {
                    $this->registerKnownPropertyType(
                        $oneToOneTypeToClassMap[$className],
                        $methodName,
                        $methodVarType
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

        parent::initializeObject();
    }

    protected function registerKnownPropertyType(string $typeName, string $propertyName, string $varType)
    {
        if (!$typeName || !$propertyName || !$varType) {
            return;
        }

        $typeNameAndType = strtolower($typeName . '->' . $propertyName);

        $varType = TypeHandling::parseType($varType);
        $isCollection = (bool)$varType['elementType'];
        $elementType = $isCollection ? $varType['elementType'] : $varType['type'];

        if ($isCollection) {
            $this->typeAndPropertyNameToClassIdentifierMap[$typeNameAndType] = 'array<' . $elementType . '>';
        } else {
            $this->typeAndPropertyNameToClassIdentifierMap[$typeNameAndType] = $elementType;
        }
    }

    /**
     * This method is compiled statically, as the ReflectionService should not be used in Production context.
     * The cached variant of the ReflectionService is missing at least the "methods annotated with".
     *
     * @Flow\CompileStatic
     * @param ObjectManagerInterface $objectManager
     * @return array
     */
    protected static function collectKnownTypes(ObjectManagerInterface $objectManager): array
    {
        $oneToOneTypeToClassMap = [];
        $classNameToPropertyNamesMap = [];
        $classNameToMethodNamesMap = [];

        $reflectionService = $objectManager->get(ReflectionService::class);
        $packageManager = $objectManager->get(PackageManager::class);

        $exposedTypes = $reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class);
        foreach ($exposedTypes as $className) {
            $annotations = $reflectionService->getClassAnnotations($className, JsonApi\ExposeType::class);

            foreach ($annotations as $annotation) {
                assert($annotation instanceof JsonApi\ExposeType);
                $type = $annotation->typeName;

                if (!$type) {
                    $typeComponents = preg_split('%\\\\Domain\\\\(Model|Command)\\\\%i', $className, 2);
                    $typeComponents[0] = explode('\\', $typeComponents[0]);
                    while ($typeComponents[0] && !$packageManager->isPackageAvailable(join('.',
                            $typeComponents[0]))) {
                        unset($typeComponents[0][count($typeComponents[0]) - 1]);
                    }
                    $type = strtolower(end($typeComponents[0]) . '/' . str_replace('\\', '.', $typeComponents[1]));
                }
                $oneToOneTypeToClassMap[$className] = $type;
                $classNameToPropertyNamesMap[$className] = [];
                $classNameToMethodNamesMap[$className] = [];

                $propertyNames = $reflectionService
                    ->getPropertyNamesByAnnotation($className, JsonApi\ExposeProperty::class);
                array_walk(
                    $propertyNames,
                    function (string $propertyName) use ($className, $reflectionService, &$classNameToPropertyNamesMap) {
                        $propertyTagValues = $reflectionService->getPropertyTagValues($className, $propertyName, 'var');
                        if (array_key_exists(0, $propertyTagValues)) {
                            $typeName = $propertyTagValues[0] ?: '';
                        } else {
                            $typeName = $reflectionService->getPropertyType($className, $propertyName);
                        }
                        $classNameToPropertyNamesMap[$className][$propertyName] = $typeName;
                    }
                );

                $methodNames = $reflectionService
                    ->getMethodsAnnotatedWith($className, JsonApi\ExposeProperty::class);
                array_walk(
                    $methodNames,
                    function (string $methodName) use ($className, $reflectionService, &$classNameToMethodNamesMap) {
                        $methodNameLength = strlen($methodName);
                        if ($methodNameLength > 2 && substr($methodName, 0, 2) === 'is') {
                            $propertyName = lcfirst(substr($methodName, 2));
                        } elseif ($methodNameLength > 3 && (($methodNamePrefix = substr($methodName, 0, 3)) === 'get' || $methodNamePrefix === 'has')) {
                            $propertyName = lcfirst(substr($methodName, 3));
                        } else {
                            return;
                        }

                        $declaredReturnType = $reflectionService->getMethodDeclaredReturnType($className, $methodName);
                        if (is_subclass_of($declaredReturnType, Collection::class) || $declaredReturnType === null) {
                            $declaredReturnType = $reflectionService->getMethodTagsValues($className, $methodName)['return'][0] ?: '';
                        }
                        $classNameToMethodNamesMap[$className][$propertyName] = $declaredReturnType;
                    }
                );

                $methodNames = $reflectionService
                    ->getMethodsAnnotatedWith($className, JsonApi\ExposeCollection::class);
                array_walk(
                    $methodNames,
                    function (string $methodName) use ($className, $reflectionService, &$classNameToMethodNamesMap) {
                        $methodNameLength = strlen($methodName);
                        if ($methodNameLength > 2 && substr($methodName, 0, 2) === 'is') {
                            $propertyName = lcfirst(substr($methodName, 2));
                        } elseif ($methodNameLength > 3 && (($methodNamePrefix = substr($methodName, 0, 3)) === 'get' || $methodNamePrefix === 'has')) {
                            $propertyName = lcfirst(substr($methodName, 3));
                        } else {
                            return;
                        }

                        $annotation = $reflectionService->getMethodAnnotation($className, $methodName, JsonApi\ExposeCollection::class);
                        $declaredReturnType = $reflectionService->getMethodDeclaredReturnType($className, $methodName);
                        $classNameToMethodNamesMap[$className][$propertyName] = sprintf('%s<%s>', $declaredReturnType, $annotation->targetType);
                    }
                );
            }
        }

        return [$oneToOneTypeToClassMap, $classNameToPropertyNamesMap, $classNameToMethodNamesMap];
    }

}