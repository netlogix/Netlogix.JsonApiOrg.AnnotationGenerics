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

use Athleta\Personen\Domain\Model\Person;
use Doctrine\Common\Collections\AbstractLazyCollection;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\ObjectAccess;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\Resource\Information\ExposableType;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Netlogix\JsonApiOrg\Resource\Information\LinksAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\MetaAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformation;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;
use Netlogix\JsonApiOrg\Schema\Relationships;
use Psr\Http\Message\UriInterface;

use function array_filter;

/**
 * @Flow\Scope("singleton")
 */
class GenericModelResourceInformation extends ResourceInformation implements ResourceInformationInterface,
                                                                             LinksAwareResourceInformationInterface,
                                                                             MetaAwareResourceInformationInterface
{
    private const TYPE_NAME_PATTERN = '%^(?<subPackage>[^/]+)/(?<resourceType>.+)$%';

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
     * @return array<string, mixed>
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     */
    public function getResourceControllerArguments(mixed $resource): array
    {
        static $resultCache = null;
        if ($resultCache === null) {
            $resultCache = new \SplObjectStorage();
        }

        if ($resultCache->contains($resource)) {
            return $resultCache->offsetGet($resource);
        }


        $configuration = $this
            ->configurationProvider
            ->getSettingsForType($resource);
        $exposable = $this
            ->exposableTypeMap
            ->getExposableTypeByClassIdentifier($configuration->className);

        $argumentName = $configuration->argumentName;
        $result = [
            $argumentName => $resource,
            ... $this->getRequestArgumentPointer($exposable),
        ];
        $result = array_filter($result, fn($value) => $value !== null);

        $resultCache->offsetSet($resource, $result);
        return $result;
    }

    public function getRequestArgumentPointer(ExposableType $exposableType): array
    {
        $arguments = [];
        if (preg_match(self::TYPE_NAME_PATTERN, (string)$exposableType->typeName, $matches)) {
            $arguments = array_intersect_key($matches, ['subPackage' => true, 'resourceType' => true]);
        }
        if ($exposableType->apiVersion) {
            $arguments['apiVersion'] = $exposableType->apiVersion;
        }
        return $arguments;
    }

    public function getLinksForPayload($payload): array
    {
        return [];
    }

    public function getLinksForRelationship($payload, $relationshipName, $relationshipType = null): array
    {
        $result = [];
        $relationshipType = $relationshipType
            ?: $this->getResource($payload)->getRelationshipsToBeApiExposed()[$relationshipName];
        if ($relationshipType === Relationships::RELATIONSHIP_TYPE_COLLECTION) {
            try {
                $result['first'] = $this->getPublicRelatedUri($payload, $relationshipName);
                $arguments = UriHelper::parseQueryIntoArguments($result['first']);
                $arguments['page'] = [
                    'number' => 0,
                    'size' => 25,
                ];
                $result['first'] = $result['first']->withQuery(http_build_query($arguments));
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

    public function getMetaForRelationship($payload, $relationshipName, $relationshipType = null, $included = false)
    {
        $result = [];
        $relationshipType = $relationshipType
            ?: $this->getResource($payload)->getRelationshipsToBeApiExposed()[$relationshipName];
        if ($relationshipType === Relationships::RELATIONSHIP_TYPE_COLLECTION) {
            try {
                $data = ObjectAccess::getProperty($payload, $relationshipName);
                if ($data !== null && $data !== false) {
                    if ($included && $data instanceof AbstractLazyCollection) {
                        // Initialize collection to prevent multiple queries (One for count and one for the data)
                        $data = $data->toArray();
                    }
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
     * @return array<string, mixed>
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     */
    protected function getRelationshipControllerArguments(mixed $resource, string $relationshipName): array
    {
        $result = $this->getResourceControllerArguments($resource);
        $result['relationshipName'] = $relationshipName;
        return $result;
    }

    /**
     * @param array<mixed, mixed> $controllerArguments
     * @throws FormatNotSupportedException
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    protected function getPublicUri(
        mixed $resource,
        string $controllerActionName,
        array $controllerArguments = []
    ): UriInterface {
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
            $settings->requestControllerName,
            $settings->requestPackageKey,
            $settings->requestSubPackageKey
        );

        return new Uri($uri);
    }

}