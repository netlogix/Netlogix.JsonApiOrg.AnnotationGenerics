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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\PackageManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;

/**
 * @Flow\Scope("singleton")
 */
class ExposableTypeMap extends \Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMap implements ExposableTypeMapInterface
{

    const PATTERN = '%^(?<vendor>[^\\\\]+)\\\\(?<package>[^\\\\]+)\\\\(?<subpackage>.+)?\\\\domain\\\\(?<type>model|command)\\\\(?<flat>.*)$%i';
    /**
     * @var ReflectionService
     * @Flow\Inject
     */
    public $reflectionService;
    /**
     * @var PackageManagerInterface
     * @Flow\Inject
     */
    protected $packageManager;

    /**
     * All "ExposeType" objects are initialized automatically
     */
    public function initializeObject()
    {
        foreach ($this->reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class) as $className) {
            foreach ($this->reflectionService->getClassAnnotations($className,
                JsonApi\ExposeType::class) as $annotation) {
                assert($annotation instanceof JsonApi\ExposeType);
                $type = $annotation->typeName;
                if (!$type) {
                    $typeComponents = preg_split('%\\\\Domain\\\\(Model|Command)\\\\%i', $className, 2);
                    $typeComponents[0] = explode('\\', $typeComponents[0]);
                    while ($typeComponents[0] && !$this->packageManager->isPackageAvailable(join('.',
                            $typeComponents[0]))) {
                        unset($typeComponents[0][count($typeComponents[0]) - 1]);
                    }
                    $type = strtolower(end($typeComponents[0]) . '/' . str_replace('\\', '.', $typeComponents[1]));
                }
                $this->oneToOneTypeToClassMap[$className] = $type;

                $propertyNames = $this
                    ->reflectionService
                    ->getPropertyNamesByAnnotation($className, JsonApi\ExposeProperty::class);
                array_walk(
                    $propertyNames,
                    function (string $propertyName) use ($type, $className) {
                        try {
                            $this->registerKnownPropertyType(
                                $type,
                                $propertyName,
                                $this
                                    ->reflectionService
                                    ->getPropertyTagValues($className, $propertyName, 'var')[0]
                                    ?: ''
                            );
                        } catch (\Exception $e) {
                        }
                    }
                );

                $methodNames = $this
                    ->reflectionService
                    ->getMethodsAnnotatedWith($className, JsonApi\ExposeProperty::class);
                array_walk(
                    $methodNames,
                    function (string $methodName) use ($type, $className) {
                        $methodNameLength = strlen($methodName);
                        if ($methodNameLength > 2 && substr($methodName, 0, 2) === 'is') {
                            $propertyName = lcfirst(substr($methodName, 2));
                        } elseif ($methodNameLength > 3 && (($methodNamePrefix = substr($methodName, 0, 3)) === 'get' || $methodNamePrefix === 'has')) {
                            $propertyName = lcfirst(substr($methodName, 3));
                        } else {
                            return;
                        }
                        try {
                            $returnType = $this->reflectionService->getMethodDeclaredReturnType($className, $methodName)
                                ?? $this->reflectionService->getMethodTagsValues($className, $methodName)['return'][0];

                            $this->registerKnownPropertyType(
                                $type,
                                $propertyName,
                                $returnType ?: ''
                            );
                        } catch (\Exception $e) {
                        }
                    }
                );

            }
        }
        parent::initializeObject();
    }

    protected function registerKnownPropertyType(string $typeName, string $propertyName, string $varType)
    {
        if (!$typeName || !$propertyName || !$varType) {
            return;
        }

        $typeNameAndType = $typeName . '->' . $propertyName;

        $varType = TypeHandling::parseType($varType);
        $isCollection = (bool)$varType['elementType'];
        $elementType = $isCollection ? $varType['elementType'] : $varType['type'];

        if ($isCollection) {
            $this->typeAndPropertyNameToClassIdentifierMap[$typeNameAndType] = 'array<' . $elementType . '>';
        } else {
            $this->typeAndPropertyNameToClassIdentifierMap[$typeNameAndType] = $elementType;
        }
    }
}