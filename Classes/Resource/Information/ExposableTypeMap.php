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
                foreach ($this->reflectionService->getPropertyNamesByAnnotation($className,
                    JsonApi\ExposeProperty::class) as $propertyName) {
                    try {
                        $this->registerKnownPropertyType($type, $propertyName,
                            $this->reflectionService->getPropertyTagValues($className, $propertyName, 'var')[0] ?: '');
                    } catch (\Exception $e) {
                    }
                }
                foreach ($this->reflectionService->getMethodsAnnotatedWith($className,
                    JsonApi\ExposeProperty::class) as $methodName) {
                    try {
                        $this->registerKnownPropertyType($type, $propertyName,
                            $this->reflectionService->getMethodTagsValues($className, $methodName)['return'][0] ?: '');
                    } catch (\Exception $e) {
                    }
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