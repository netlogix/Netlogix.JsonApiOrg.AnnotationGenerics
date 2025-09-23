<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Controller;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Cache\Exception;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\NoMatchingRouteException;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\Exception\UnknownObjectException;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Utility\Exception\InvalidTypeException;
use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;
use Netlogix\JsonApiOrg\View\JsonView;

use function array_filter;
use function ksort;

/**
 * @Flow\Scope("singleton")
 */
class EndpointDiscoveryController extends ActionController
{
    /**
     * @var VariableFrontend
     */
    protected $resultsCache;

    /**
     * @var array
     */
    protected $supportedMediaTypes = [
        'application/vnd.api+json',
        'application/json',
        'text/html'
    ];

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = [
        'json' => JsonView::class
    ];

    /**
     * @var array
     * @Flow\InjectConfiguration(package="Netlogix.JsonApiOrg", path="endpointDiscovery.additionalLinks")
     */
    protected $additionalLinks = [];

    /**
     * @var ReflectionService
     * @Flow\Inject
     */
    protected $reflectionService;

    /**
     * @var ObjectManagerInterface
     * @Flow\Inject
     */
    protected $objectManager;

    /**
     * @var PackageManager
     * @Flow\Inject
     */
    protected $packageManager;

    /**
     * @var ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @var ResourceMapper
     * @Flow\Inject
     */
    protected $resourceMapper;

    /**
     * @var array
     */
    protected $resultTemplate = [
        'meta' => [
            'api-version' => [],
        ],
        'links' => []
    ];

    /**
     * @var array<string>
     */
    protected $packageKeysTemplate = ['Netlogix.JsonApiOrg', 'Netlogix.JsonApiOrg.AnnotationGenerics'];

    /**
     * @param string $packageKey
     * @throws Exception
     * @throws InvalidTypeException
     * @throws MissingActionNameException
     */
    public function indexAction(string $packageKey = '')
    {
        $cacheIdentifier = $this->getCacheIdentifier($packageKey);
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
                foreach ($result['meta']['api-version'] as $key => $link) {
                    if (in_array($key, $this->packageKeysTemplate)) {
                        continue;
                    }
                    if (stripos($key . '.', $packageKey . '.') !== 0) {
                        unset($result['meta']['api-version'][$key]);
                    }
                }
            }
            $this->resultsCache->set($cacheIdentifier, $result);
        }

        $this->view->assign('value', $result);
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

        foreach ($this->reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class) as $className) {

            $type = null;
            $uri = null;
            try {
                $resource = $this->getDummyObject($className);
                $configuration = $this->configurationProvider->getSettingsForType($resource);
                if ($configuration->private) {
                    continue;
                }
                $type = $configuration->typeName;
                $apiVersion = $configuration->apiVersion;
                $versionedType = $type . PHP_EOL . ($apiVersion ?? '');
                $uri = $this->buildUriForDummyResource($resource);
            } catch (FormatNotSupportedException $e) {
            } catch (NoMatchingRouteException $e) {
            } catch (UnknownObjectException $e) {
            }

            if (!$type || !$uri) {
                continue;
            }

            $result['links'][$versionedType] = [
                'href' => $uri,
                'meta' => array_filter(
                    [
                        'type' => 'resourceUri',
                        'resourceType' => $type,
                        'apiVersion' => $apiVersion,
                        'packageKey' => $configuration->getModelPackageKey()
                    ],
                    fn($field) => $field !== null,
                )
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

        usort($result['links'], function ($a, $b) {
            return $a['meta']['resourceType'] <=> $b['meta']['resourceType'];
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

    protected function getCacheIdentifier(string $packageKey): string
    {
        $request = $this->request;
        assert($request instanceof ActionRequest);
        $uri = $request
            ->getHttpRequest()
            ->getUri()
            ->withQuery('')
            ->__toString();
        return md5($packageKey . $uri);
    }
}
