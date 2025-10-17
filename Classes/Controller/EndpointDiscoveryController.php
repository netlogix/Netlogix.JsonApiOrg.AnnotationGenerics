<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Controller;

use Neos\Cache\Exception;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\Exception\UnknownObjectException;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Utility\Exception\InvalidTypeException;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;

use function array_filter;
use function in_array;
use function ksort;
use function md5;
use function strtolower;

#[Flow\Scope("singleton")]
class EndpointDiscoveryController extends ActionController
{
    #[Flow\Inject(name: 'Netlogix.JsonApiOrg.AnnotationGenerics:EndpointDiscoveryCache', lazy: false)]
    protected VariableFrontend $resultsCache;

    protected $supportedMediaTypes = [
        'application/vnd.api+json',
        'application/json',
        'text/html',
    ];

    #[Flow\InjectConfiguration(package: 'Netlogix.JsonApiOrg', path: 'endpointDiscovery.additionalLinks')]
    protected array $additionalLinks = [];

    #[Flow\Inject]
    protected PackageManager $packageManager;

    #[Flow\Inject]
    protected ConfigurationProvider $configurationProvider;

    #[Flow\Inject]
    protected ExposableTypeMapInterface $exposableTypeMap;

    #[Flow\Inject]
    protected ResourceMapper $resourceMapper;

    protected array $resultTemplate = [
        'meta' => [
            'api-version' => [],
        ],
        'links' => [],
    ];

    /**
     * @var string[]
     */
    protected array $packageKeysTemplate = ['Netlogix.JsonApiOrg', 'Netlogix.JsonApiOrg.AnnotationGenerics'];

    /**
     * @param string $packageKey
     * @param string $apiVersion Use "all" for all versions, "next" for unversioned or a specific version like "v1"
     * @throws Exception
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    public function indexAction(
        string $packageKey = '',
        string $apiVersion = 'all'
    ): string {
        $cacheIdentifier = $this->getCacheIdentifier($packageKey, $apiVersion);
        if ($this->resultsCache->has($cacheIdentifier)) {
            $result = $this->resultsCache->get($cacheIdentifier);
        } else {
            $result = $this->getResultJson();
            if ($packageKey) {
                foreach ($result['links'] as $key => $link) {
                    if (stripos($link['meta']['packageKey'] . '.', $packageKey . '.') !== 0) {
                        unset($result['links'][$key]);
                    }
                }
            }
            if ($apiVersion && $apiVersion !== 'all') {
                $result['links'] = array_filter(
                    array: $result['links'],
                    callback: function (array $link) use ($apiVersion) {
                        $linkApiVersion = $link['meta']['apiVersion'] ?? ExposableTypeMapInterface::NEXT_VERSION;
                        return strtolower($linkApiVersion) === strtolower($apiVersion);
                    }
                );
            }
            $knownPackageKeys = array_map(
                callback: fn (array $link) => $link['meta']['packageKey'],
                array: $result['links']
            );
            $result['meta']['api-version'] = array_filter(
                array: $result['meta']['api-version'],
                callback: fn (string $packageKey) => in_array($packageKey, $knownPackageKeys) || in_array(
                        $packageKey,
                        $this->packageKeysTemplate
                    ),
                mode: ARRAY_FILTER_USE_KEY
            );

            $this->resultsCache->set($cacheIdentifier, $result);
        }

        $this->response->setContentType('application/json');

        return json_encode($result, \JSON_PRETTY_PRINT);
    }

    /**
     * @return array
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    protected function getResultJson(): array
    {
        $result = $this->resultTemplate;
        $packageKeys = $this->packageKeysTemplate;

        $classes = $this->reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class);
        foreach ($classes as $className) {
            $uri = null;
            try {
                $exposableType = $this->exposableTypeMap->getExposableTypeByClassIdentifier($className);
                $configuration = $this->configurationProvider->getSettingsForType($className);
                if ($configuration->private) {
                    continue;
                }
                $resourceType = $exposableType->getVersionType();

                $uri = $this->buildUriForDummyResource(
                    $this->getDummyObject($className)
                );
            } catch (FormatNotSupportedException $e) {
            } catch (NoMatchingRouteException $e) {
            } catch (UnknownObjectException $e) {
            }

            if (!$resourceType || !$uri) {
                continue;
            }

            $result['links'][$resourceType] = [
                'href' => $uri,
                'meta' => array_filter([
                    'resourceType' => $resourceType,
                    'packageKey' => $configuration->getModelPackageKey(),
                    'apiVersion' => $exposableType->apiVersion !== ExposableTypeMapInterface::NEXT_VERSION ? $exposableType->apiVersion : null,
                    'baseResourceType' => $resourceType !== $exposableType->typeName ? $exposableType->typeName : null,
                    'type' => 'resourceUri',
                ],
                    fn ($field) => $field !== null,),
            ];
            $packageKeys[$configuration->getModelPackageKey()] = $configuration->getModelPackageKey();
        }

        $result['links'] = array_merge($result['links'], $this->additionalLinks);
        ksort($result['links']);

        foreach ($packageKeys as $packageKey) {
            try {
                $installedVersion = $this->packageManager->getPackage($packageKey)->getInstalledVersion() ?? 'local';
                $result['meta']['api-version'][$packageKey] = $installedVersion;
            } catch (\Exception $e) {
            }
        }

        usort($result['links'], function (array $a, array $b): int {
            return ($a['meta']['resourceType'] . PHP_EOL . ($a['meta']['apiVersion'] ?? '')) <=> ($b['meta']['resourceType'] . PHP_EOL . ($b['meta']['apiVersion'] ?? ''));
        });
        ksort($result['meta']['api-version']);
        return $result;
    }

    /**
     * @param string $className
     * @return object
     */
    protected function getDummyObject(string $className)
    {
        return unserialize('O:' . strlen($className) . ':"' . $className . '":0:{}');
    }

    /**
     * @param $resource
     * @return string
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    protected function buildUriForDummyResource($resource): string
    {
        $settings = $this->configurationProvider->getSettingsForType($resource);

        $resourceInformation = $this->resourceMapper->findResourceInformation($resource);

        $controllerArguments = $resourceInformation->getResourceControllerArguments($resource);
        unset($controllerArguments[$settings->argumentName]);

        $uriBuilder = $this->getControllerContext()->getUriBuilder();
        $uriBuilder
            ->reset()
            ->setFormat('json')
            ->setCreateAbsoluteUri(true);

        return $uriBuilder->uriFor(
            $settings->requestActionName,
            $controllerArguments,
            $settings->requestControllerName,
            $settings->requestPackageKey,
            $settings->requestSubPackageKey
        );
    }

    protected function getCacheIdentifier(string $packageKey, string $apiVersion): string
    {
        $request = $this->request;
        assert($request instanceof ActionRequest);
        $uri = $request
            ->getHttpRequest()
            ->getUri()
            ->withQuery('')
            ->__toString();
        return md5($packageKey . $apiVersion . $uri);
    }
}
