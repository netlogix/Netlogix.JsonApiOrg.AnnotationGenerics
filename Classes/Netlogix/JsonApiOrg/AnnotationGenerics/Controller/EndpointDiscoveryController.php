<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Controller;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Annotations as JsonApi;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\Resource\Information\ResourceMapper;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Property\Exception\FormatNotSupportedException;
use TYPO3\Flow\Reflection\ReflectionService;

/**
 * Endpoint Discovery
 *
 * @Flow\Scope("singleton")
 */
class EndpointDiscoveryController extends ActionController
{
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
     * @var PackageManagerInterface
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
     * @return string
     */
    public function indexAction()
    {
        $result = $this->getResultJson();

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * @param string $packageKey
     * @return string
     */
    public function showAction($packageKey)
    {
        $result = $this->getResultJson();
        foreach ($result['links'] as $key => $link) {

        }

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode($result, JSON_PRETTY_PRINT);
    }

    /**
     * @return array
     */
    protected function getResultJson()
    {
        $result = $this->resultTemplate;
        $packageKeys = ['Netlogix.JsonApiOrg', 'Netlogix.JsonApiOrg.AnnotationGenerics'];

        foreach ($this->reflectionService->getClassNamesByAnnotation(JsonApi\ExposeType::class) as $className)
        {
            $packageKey = str_replace('\\', '.', current(preg_split('%\\\\(Domain|Model)\\\\%i', $className)));
            $packageKeys[$packageKey] = $packageKey;

            $type = null;
            $uri = null;
            try {
                $resource = $this->getDummyObject($className);
                $type = $this->resourceMapper->getDataIdentifierForPayload($resource)['type'];
                $uri = $this->buildUriForDummyResource($resource);
            } catch (FormatNotSupportedException $e) {}

            if (!$type) {
                continue;
            }

            $result['links'][$type] = [
                'href' => $uri,
                'meta' => [
                    'type' => 'resourceUri',
                    'resourceType' => $type,
                ],
            ];
        }

        foreach ($packageKeys as $packageKey) {
            $result['meta']['api-version'][$packageKey] = $this->packageManager->getPackage($packageKey)->getPackageMetaData()->getVersion();
        }

        sort($result['links']);
        return $result;
    }

    /**
     * @param $className
     * @return mixed
     */
    protected function getDummyObject($className)
    {
        return unserialize('O:' . strlen($className) . ':"' . $className . '":0:{};');
    }

    /**
     * @param mixed $resource
     * @return \TYPO3\Flow\Http\Uri
     */
    protected function buildUriForDummyResource($resource)
    {
        $settings = $this->configurationProvider->getSettingsForType($resource);

        $resourceInformation = $this->resourceMapper->findResourceInformation($resource);
        $controllerArguments = $resourceInformation->getResourceControllerArguments($resource);
        unset($controllerArguments[$settings['argumentName']]);

        $uriBuilder = $this->getControllerContext()->getUriBuilder();
        $uriBuilder
            ->reset()
            ->setFormat('json')
            ->setCreateAbsoluteUri(true);

        return $uriBuilder->uriFor(
            'index',
            $controllerArguments,
            $settings['controllerName'],
            $settings['packageKey'],
            $settings['subPackageKey']
        );
    }

}