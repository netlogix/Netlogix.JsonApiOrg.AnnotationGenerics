<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Property\TypeConverter;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;
use Netlogix\JsonApiOrg\AnnotationGenerics\Cache\ExposedValueObjectCache;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ExposedValueObjectInterface;

class ExposedValueObjectConverter extends AbstractTypeConverter
{

    /**
     * @var ExposedValueObjectCache
     * @Flow\Inject
     */
    protected $exposedValueObjectCache;

    protected $sourceTypes = ['string'];

    /**
     * The target type this converter can convert to.
     */
    protected $targetType = ExposedValueObjectInterface::class;

    /**
     * @param string $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return ExposedValueObjectInterface|null
     */
    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        PropertyMappingConfigurationInterface $configuration = null
    ) {
        return $this->exposedValueObjectCache->get($source, $targetType);
    }

}