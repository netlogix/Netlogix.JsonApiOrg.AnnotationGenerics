<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use function array_values;
use function class_exists;
use function class_parents;
use function str_replace;

/**
 * Resolves the conventional companion classes of a Domain\Model class:
 * its Filter, Sorting and Repository. The named constructor checks whether
 * each companion actually exists; the readonly properties hold the existing
 * class name or null.
 *
 * Usage: ModelCompanion::forModel($modelClassName)->sortingClassName
 */
#[Flow\Proxy(false)]
final class ModelCompanion
{
    protected function __construct(
        /**
         * @var class-string
         */
        public readonly string  $modelClassName,
        /**
         * @var class-string
         */
        public readonly ?string $filterClassName,
        /**
         * @var class-string
         */
        public readonly ?string $sortingClassName,
        /**
         * @var class-string
         */
        public readonly ?string $repositoryClassName,
    )
    {
    }

    /**
     * @param class-string $modelClassName
     * @return self
     */
    public static function forModel(string $modelClassName): self
    {
        $filterClassName = str_replace('\\Model\\', '\\Repository\\Filter\\', $modelClassName) . 'Filter';
        $sortingClassName = str_replace('\\Model\\', '\\Repository\\Sorting\\', $modelClassName) . 'Sorting';

        return new self(
            modelClassName: $modelClassName,
            filterClassName: class_exists($filterClassName) ? $filterClassName : null,
            sortingClassName: class_exists($sortingClassName) ? $sortingClassName : null,
            repositoryClassName: self::resolveRepositoryClassName($modelClassName),
        );
    }

    /**
     * The Repository is looked up on the model class and its parent classes,
     * mirroring GenericModelController::getRepositoryForResourceType().
     */
    private static function resolveRepositoryClassName(string $modelClassName): ?string
    {
        $modelClassNames = array_values(class_parents($modelClassName) ?: []);
        foreach ([$modelClassName, ... $modelClassNames] as $candidate) {
            $repositoryClassName = str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\', $candidate) . 'Repository';
            if (class_exists($repositoryClassName)) {
                return $repositoryClassName;
            }
        }

        return null;
    }
}
