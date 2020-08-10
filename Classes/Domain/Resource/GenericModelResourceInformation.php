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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\ObjectAccess;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Netlogix\JsonApiOrg\Resource\Information\LinksAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\MetaAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformation;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;
use Netlogix\JsonApiOrg\Schema\Relationships;

/**
 * @Flow\Scope("singleton")
 */
class GenericModelResourceInformation extends ResourceInformation implements ResourceInformationInterface, LinksAwareResourceInformationInterface, MetaAwareResourceInformationInterface
{
    /**
     * @var int
     */
    protected $priority = 10;

    /**
     * @var ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @var ExposableTypeMapInterface
     * @Flow\Inject
     */
    protected $map;

    /**
     * @var string
     */
    protected $resourceClassName = GenericModelResource::class;

    /**
     * @var string
     */
    protected $payloadClassName = GenericModelInterface::class;

    /**
     * @param mixed $resource
     * @return array
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     */
    public function getResourceControllerArguments($resource): array
    {
        static $resultCache = null;
        if ($resultCache === null) {
            $resultCache = new \SplObjectStorage();
        }

        if ($resultCache->contains($resource)) {
            return $resultCache->offsetGet($resource);
        }

        $settings = $this->configurationProvider->getSettingsForType($resource);
        $result = [
            $settings['argumentName'] => $resource,
        ];
        $type = TypeHandling::getTypeForValue($resource);
        $typeString = (string)$this->map->getType($type);
        if (preg_match('%^(?<subPackage>[^/]+)/(?<resourceType>.+)$%', (string)$typeString)) {
            list($result['subPackage'], $result['resourceType']) = explode('/', $typeString, 2);
        }

        $resultCache->offsetSet($resource, $result);
        return $result;
    }

    public function getLinksForPayload($payload): array
    {
        return [];
    }

    public function getLinksForRelationship($payload, $relationshipName, $relationshipType = null): array
    {
        $result = [];
        $relationshipType = $relationshipType ?: $this->getResource($payload)->getRelationshipsToBeApiExposed()[$relationshipName];
        if ($relationshipType === Relationships::RELATIONSHIP_TYPE_COLLECTION) {
            try {
                $result['first'] = $this->getPublicRelatedUri($payload, $relationshipName);
                $arguments = $result['first']->getArguments();
                $arguments['page'] = [
                    'number' => 0,
                    'size' => 25,
                ];
                $result['first']->setQuery(http_build_query($arguments));
                $result['first'] = (string)$result['first'];
            } catch (NoMatchingRouteException $e) {

            }
        }
        return $result;
    }

    public function getMetaForPayload($payload): array
    {
        return [];
    }

    public function getMetaForRelationship($payload, $relationshipName, $relationshipType = null)
    {
        $result = [];
        $relationshipType = $relationshipType ?: $this->getResource($payload)->getRelationshipsToBeApiExposed()[$relationshipName];
        if ($relationshipType === Relationships::RELATIONSHIP_TYPE_COLLECTION) {
            try {
                $data = ObjectAccess::getProperty($payload, $relationshipName);
                if ($data !== null && $data !== false) {
                    $result['page'] = [
                        'total' => count($data),
                    ];
                }
            } catch (\Exception $e) {
            }

        }
        return $result;
    }

    /**
     * @param mixed $resource
     * @param string $relationshipName
     * @return array
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     */
    protected function getRelationshipControllerArguments($resource, $relationshipName)
    {
        $result = $this->getResourceControllerArguments($resource);
        $result['relationshipName'] = $relationshipName;
        return $result;
    }

    /**
     * @param mixed $resource
     * @param string $controllerActionName
     * @param array $controllerArguments
     * @return Uri
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    protected function getPublicUri($resource, $controllerActionName, array $controllerArguments = array()): Uri
    {
        $settings = $this->configurationProvider->getSettingsForType($resource);

        $uriBuilder = $this->resourceMapper->getControllerContext()->getUriBuilder();

        $uriBuilder
            ->reset()
            ->setFormat($this->format)
            ->setCreateAbsoluteUri(true);

        $controllerArguments = array_merge($this->getResourceControllerArguments($resource), $controllerArguments);

        $uri = $uriBuilder->uriFor(
            $controllerActionName,
            $controllerArguments,
            $settings['controllerName'],
            $settings['packageKey'],
            $settings['subPackageKey']
        );

        return new Uri($uri);
    }

}