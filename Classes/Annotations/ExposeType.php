<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * A model class which should be available as api resource needs this
 * annotation.
 *
 * @Annotation
 * @Target("CLASS")
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExposeType
{
    /**
     * Usually types are composed by package key and class name. This allows
     * the type to be forced to an alternative value.
     *
     * @var string
     */
    public $typeName = null;

    /**
     * Each exposed object needs to be available through its resource URI, so having
     * a controller in place is mandatory.
     *
     * This is the controller name.
     *
     * @var string
     */
    public $controllerName = null;

    /**
     * Each exposed object needs to be available through its resource URI, so having
     * a controller in place is mandatory.
     *
     * This is the package key.
     *
     * @var string
     */
    public $packageKey;

    /**
     * Each exposed object needs to be available through its resource URI, so having
     * a controller in place is mandatory.
     *
     * This is the sub package key.
     *
     * @var string
     */
    public $subPackageKey = null;

    /**
     * Each exposed object needs to be available through its resource URI, so having
     * a controller in place is mandatory.
     *
     * This is the action name.
     *
     * @var string
     */
    public $actionName = 'index';

    /**
     * Usually the action argument "$resource" is used. To name the input argument more
     * domain specific, this allows renaming.
     *
     * @var string
     */
    public $argumentName = 'resource';

    /**
     * Most exposed objects should be visible in endpoint discovery. But sometimes objects
     * are only available through other objects. This flag hides them in endpoint discovery.
     *
     * @var bool
     */
    public $private = false;

    public function __construct(
        string $packageKey,
        string $typeName = null,
        string $controllerName = null,
        string $subPackageKey = null,
        string $actionName = 'index',
        string $argumentName = 'resource',
        bool $private = false
    ) {
        $this->packageKey = $packageKey;
        $this->typeName = $typeName;
        $this->controllerName = $controllerName;
        $this->subPackageKey = $subPackageKey;
        $this->actionName = $actionName;
        $this->argumentName = $argumentName;
        $this->private = $private;
    }
}
