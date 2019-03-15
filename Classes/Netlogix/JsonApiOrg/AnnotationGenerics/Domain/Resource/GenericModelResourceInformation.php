<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Resource;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Mvc\Exception\NoMatchingRouteException;
use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Controller\GenericModelController;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\Resource\Information\LinksAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\MetaAwareResourceInformationInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformation;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;
use Netlogix\JsonApiOrg\Schema\Relationships;
use TYPO3\Flow\Reflection\ObjectAccess;
use TYPO3\Flow\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class GenericModelResourceInformation extends ResourceInformation implements ResourceInformationInterface, LinksAwareResourceInformationInterface, MetaAwareResourceInformationInterface
{
    const DOMAIN_MODEL_PATTERN = '%(?<vendor>[^\\\\]+)\\\\(?<package>.*)\\\\(?<subPackage>[^\\\\]+)\\\\Domain\\\\(Model|Command)\\\\(?<resourceType>.+)$%i';

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
     * @var
     */
    protected $resourceClassName = GenericModelResource::class;

    /**
     * @var
     */
    protected $payloadClassName = GenericModelInterface::class;

    /**
     * @param object $resource
     * @return array
     */
    public function getResourceControllerArguments($resource)
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
        $controllerClassName = str_replace('.', '\\', $settings['packageKey'] . '\\Controller\\' . $settings['controllerName'] . 'Controller');
        if (preg_match(self::DOMAIN_MODEL_PATTERN, $type, $matches) && class_exists($controllerClassName) && is_subclass_of($controllerClassName, GenericModelController::class)) {
            $result['subPackage'] = [];
            foreach (explode('\\', $matches['subPackage']) as $subPackage) {
                $result['subPackage'][] = lcfirst($subPackage);
            }
            $result['subPackage'] = join('.', $result['subPackage']);
            $result['resourceType'] = [];
            foreach (explode('\\', $matches['resourceType']) as $resourceType) {
                $result['resourceType'][] = lcfirst($resourceType);
            }
            $result['resourceType'] = join('.', $result['resourceType']);
        }

        $resultCache->offsetSet($resource, $result);
        return $result;
    }

    /**
     * @param object $resource
     * @param string $relationshipName
     * @return array
     */
    protected function getRelationshipControllerArguments($resource, $relationshipName)
    {
        $result = $this->getResourceControllerArguments($resource);
        $result['relationshipName'] = $relationshipName;
        return $result;
    }

    /**
     * @param object $resource
     * @param string $controllerActionName
     * @param array $controllerArguments
     * @return Uri
     */
    protected function getPublicUri($resource, $controllerActionName, array $controllerArguments = array())
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

    public function getLinksForPayload($payload)
    {
        return [];
    }

    public function getLinksForRelationship($payload, $relationshipName, $relationshipType = null)
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

    public function getMetaForPayload($payload)
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

}