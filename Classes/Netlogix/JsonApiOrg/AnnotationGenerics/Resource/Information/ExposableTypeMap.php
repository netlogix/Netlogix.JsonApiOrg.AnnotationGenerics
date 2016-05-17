<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Resource\Information;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Reflection\ReflectionService;

/**
 * @Flow\Scope("singleton")
 */
class ExposableTypeMap extends \Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMap implements ExposableTypeMapInterface {

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

    const PATTERN = '%^(?<vendor>[^\\\\]+)\\\\(?<package>[^\\\\]+)\\\\(?<subpackage>.+)?\\\\domain\\\\(?<type>model|command)\\\\(?<flat>.*)$%i';

    /**
     * All "ExposeType" objects are initialized automatically
     */
    public function initializeObject()
    {
        foreach ($this->reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class) as $className) {
            /** @var JsonApi\ExposeType $annotation */
            foreach ($this->reflectionService->getClassAnnotations($className, JsonApi\ExposeType::class) as $annotation) {
                $type = $annotation->typeName;
                if (!$type) {
                    $typeComponents = preg_split('%\\\\Domain\\\\(Model|Command)\\\\%i', $className, 2);
                    $typeComponents[0] = explode('\\', $typeComponents[0]);
                    while ($typeComponents[0] && !$this->packageManager->isPackageActive(join('.', $typeComponents[0]))) {
                        unset($typeComponents[0][count($typeComponents[0]) - 1]);
                    }
                    $type = strtolower(end($typeComponents[0]) . '/' . str_replace('\\', '.', $typeComponents[1]));
                }
                $this->oneToOneTypeToClassMap[$className] = $type;
            }
        }
        parent::initializeObject();
    }
}