<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Resource;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\Domain\Dto\AbstractResource;
use Netlogix\JsonApiOrg\Schema\Relationships;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;
use TYPO3\Flow\Annotations as Flow;

class GenericModelResource extends AbstractResource
{
    /**
     * @var ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @var GenericModelInterface
     */
    protected $payload;

    /**
     * Load attributes and relationships to be api exposed
     */
    public function initializeObject()
    {
        $settings = $this->configurationProvider->getSettingsForType($this->getPayload());
        $this->attributesToBeApiExposed = $settings['attributesToBeApiExposed'];
        $this->relationshipsToBeApiExposed = $settings['relationshipsToBeApiExposed'];
    }

    /**
     * @param GenericModelInterface $payload
     * @param ResourceInformationInterface $resourceInformation
     */
    public function __construct(GenericModelInterface $payload, ResourceInformationInterface $resourceInformation = NULL)
    {
        parent::__construct($payload, $resourceInformation);
    }

    /**
     * @param string $propertyName
     * @return mixed
     * @throws \TYPO3\Flow\Reflection\Exception\PropertyNotAccessibleException
     */
    public function getPayloadProperty($propertyName)
    {
        $result = parent::getPayloadProperty($propertyName);
        if (is_object($result) && $result instanceof \DateTime) {
            return $result->format(\DateTime::W3C);
        } else {
            return $result;
        }
    }

    /**
     * @return GenericModelInterface
     */
    public function getPayload()
    {
        return parent::getPayload();
    }

}