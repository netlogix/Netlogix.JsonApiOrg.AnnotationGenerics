<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Neos\Flow\Annotations as Flow;

/**
 * A model class which should be available as an api resource needs this
 * annotation.
 *
 * @Annotation
 * @Target("CLASS")
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
#[Flow\Proxy(false)]
final class ExposeType
{
    public function __construct(
        /**
         * Usually types are composed by package key and class name.
         * Changing this allows the type to be forced to an alternative value.
         */
        public readonly ?string $typeName = null,

        /**
         * Each exposed object needs to be available through its resource URI, so having
         * a controller in place is mandatory.
         *
         * This is the package key.
         */
        public readonly ?string $packageKey = null,

        /**
         * Each exposed object needs to be available through its resource URI, so having
         * a controller in place is mandatory.
         *
         * This is the controller name.
         */
        public readonly ?string $controllerName = null,

        /**
         * Each exposed object needs to be available through its resource URI, so having
         * a controller in place is mandatory.
         *
         * This is the subpackage key.
         */
        public readonly ?string $subPackageKey = null,

        /**
         * Each exposed object needs to be available through its resource URI, so having
         * a controller in place is mandatory.
         *
         * This is the action name.
         */
        public readonly ?string $actionName = null,

        /**
         * Usually the action argument "$resource" is used. To name the input argument more
         * domain specific, this allows renaming.
         */
        public readonly ?string $argumentName = null,

        /**
         * Most exposed objects should be visible in endpoint discovery. But sometimes objects
         * are only available through other objects. This flag hides them in endpoint discovery.
         */
        public readonly ?bool $private = null
    ) {
    }

    public function toArray(bool $skipNull = true): array
    {
        $result = [
            'typeName' => $this->typeName,
            'packageKey' => $this->packageKey,
            'controllerName' => $this->controllerName,
            'subPackageKey' => $this->subPackageKey,
            'actionName' => $this->actionName,
            'argumentName' => $this->argumentName,
            'private' => $this->private,
        ];
        if ($skipNull) {
            $result = array_filter($result, fn($v) => $v !== null);
        }
        return $result;
    }
}
