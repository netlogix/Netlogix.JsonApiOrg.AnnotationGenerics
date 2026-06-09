<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Arrays;

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
    /**
     * Default values for list-action arguments, keyed by argument name
     * (page/filter/sort). Mirrors the URL-arg structure a client would send.
     * Two notations are accepted and may be mixed within one defaults array:
     *   - Nested form:    ['page' => ['size' => 250], 'filter' => ['name' => 'sv']]
     *   - Dot-path form:  ['page.size' => 250, 'filter.name' => 'sv']
     * The constructor normalises the value to the nested form, so the property
     * and every downstream consumer (Configuration, GenericModelController, …)
     * see only nested arrays.
     * At runtime Flow's PropertyMapper converts each value to the resource-type-
     * specific concrete instance (e.g. VereinFilter), same as for client-supplied
     * query parameters.
     *
     * Array form is required because Flow's proxy compiler dumps object-valued
     * attribute arguments via var_export() → Class::__set_state(...), which is
     * not a valid PHP constant expression for an attribute.
     */
    public readonly ?array $defaults;

    public function __construct(
        public readonly ?string $typeName = null,

        public readonly ?string $requestPackageKey = null,

        public readonly ?string $requestControllerName = null,

        public readonly ?string $requestSubPackageKey = null,

        public readonly ?string $requestActionName = null,

        public readonly ?string $requestArgumentName = null,

        public readonly ?bool $private = null,

        public readonly ?string $apiVersion = null,

        /**
         * Allow for similar type names and api versions, as long as the
         * one to be used publicly is known.
         */
        public readonly ?string $replaces = null,

        ?array $defaults = null
    ) {
        $this->defaults = self::normalizeDefaults($defaults);
    }

    public function toArray(bool $skipNull = true): array
    {
        $result = [
            'typeName' => $this->typeName,
            'requestPackageKey' => $this->requestPackageKey,
            'requestControllerName' => $this->requestControllerName,
            'requestSubPackageKey' => $this->requestSubPackageKey,
            'requestActionName' => $this->requestActionName,
            'requestArgumentName' => $this->requestArgumentName,
            'private' => $this->private,
            'apiVersion' => $this->apiVersion,
            'replaces' => $this->replaces,
            'defaults' => $this->defaults,
        ];
        if ($skipNull) {
            $result = array_filter($result, fn($v) => $v !== null);
        }
        return $result;
    }

    /**
     * Accepts a mixed-form defaults array and produces a pure nested array.
     * - Top-level keys containing '.' are expanded as dot-paths via Arrays::setValueByPath.
     * - Top-level array values for already-present keys are deep-merged via
     *   arrayMergeRecursiveOverrule so nested + dot-path entries combine cleanly.
     * - Later entries in the source array win at the leaf they touch.
     */
    private static function normalizeDefaults(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $result = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && str_contains($key, '.')) {
                $result = Arrays::setValueByPath($result, $key, $value);
                continue;
            }
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = Arrays::arrayMergeRecursiveOverrule($result[$key], $value);
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
