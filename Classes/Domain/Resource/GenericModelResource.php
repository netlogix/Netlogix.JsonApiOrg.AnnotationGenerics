<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Resource;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use DateTime;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\Exception\PropertyNotAccessibleException;
use Neos\Utility\ObjectAccess;
use Netlogix\JsonApiOrg\AnnotationGenerics\Cache\ExposedValueObjectCache;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ExposedValueObjectInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\JsonApiIdentifier;
use Netlogix\JsonApiOrg\Domain\Dto\AbstractResource;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;

class GenericModelResource extends AbstractResource
{

    /**
     * @var ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @var ExposedValueObjectCache
     * @Flow\Inject
     */
    protected $exposedValueObjectCache;

    protected $identityAttributes = [];

    /**
     * @var GenericModelInterface
     */
    protected $payload;

    /**
     * @param GenericModelInterface $payload
     * @param ResourceInformationInterface $resourceInformation
     */
    public function __construct(
        GenericModelInterface $payload,
        ResourceInformationInterface $resourceInformation = null
    ) {
        parent::__construct($payload, $resourceInformation);
    }

    /**
     * @throws InvalidTypeException
     */
    public function initializeObject()
    {
        $settings = $this->configurationProvider->getSettingsForType($this->getPayload());
        $this->attributesToBeApiExposed = $settings->attributesToBeApiExposed;
        ksort($this->attributesToBeApiExposed);
        $this->relationshipsToBeApiExposed = $settings->relationshipsToBeApiExposed;
        ksort($this->relationshipsToBeApiExposed);
        $this->identityAttributes = $settings->identityAttributes;
        if ($this->payload instanceof ExposedValueObjectInterface) {
            $this->exposedValueObjectCache->set($this->getId(), $this->payload);
        }
    }

    /**
     * @param string $propertyName
     * @return mixed
     * @throws PropertyNotAccessibleException
     */
    public function getPayloadProperty($propertyName)
    {
        $result = parent::getPayloadProperty($propertyName);
        if (is_object($result) && $result instanceof DateTime) {
            return $result->format(\DateTimeInterface::W3C);
        } else {
            return $result;
        }
    }

    public function getPayload(): GenericModelInterface
    {
        return parent::getPayload();
    }

    public function getId(): string
    {
        if (!$this->identityAttributes) {
            return (string)parent::getId();
        }
        $payload = $this->getPayload();
        return implode(
            '|',
            array_map(
                function ($identityAttribute) use ($payload) {
                    $identity = ObjectAccess::getProperty($payload, $identityAttribute);
                    if ($identity instanceof JsonApiIdentifier) {
                        return (string)$identity->toString();
                    }
                    if (is_object($identity) && !$identity instanceof \Stringable) {
                        new \InvalidArgumentException('Using object as identifier without implementing Stringable interface', 1623823320);
                    }
                    return (string)$identity;
                },
                $this->identityAttributes
            )
        );
    }

}