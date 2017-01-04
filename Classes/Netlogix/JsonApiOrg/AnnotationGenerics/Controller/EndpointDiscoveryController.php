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
use Netlogix\JsonApiOrg\View\JsonView;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Property\Exception\FormatNotSupportedException;
use TYPO3\Flow\Reflection\ReflectionService;

/**
 * @Flow\Scope("singleton")
 */
class EndpointDiscoveryController extends ActionController
{
    /**
     * @var array
     */
    protected $supportedMediaTypes = array(
        'application/vnd.api+json',
        'application/json',
        'text/html'
    );

    /**
     * @var array
     */
    protected $viewFormatToObjectNameMap = array(
        'json' => JsonView::class
    );

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
     * @var array<string>
     */
    protected $packageKeysTemplate = ['Netlogix.JsonApiOrg', 'Netlogix.JsonApiOrg.AnnotationGenerics'];

    /**
     * @param string $packageKey
     * @return string
     */
    public function indexAction($packageKey = null)
    {
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

        $this->view->assign('value', $result);
    }

    /**
     * @return array
     */
    protected function getResultJson()
    {
        $result = $this->resultTemplate;
        $packageKeys = $this->packageKeysTemplate;

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
                    'packageKey' => $packageKey,
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
     * @return string
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