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

use Doctrine\Common\Collections\Selectable;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Argument;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\Exception\RequiredArgumentMissingException;
use Neos\Flow\ObjectManagement\Exception\UnknownObjectException;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Utility\Arrays;
use Neos\Utility\Exception\PropertyNotAccessibleException;
use Neos\Utility\ObjectAccess;
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine\ExtraLazyPersistentCollection;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments as RequestArgument;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ReadModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\WriteModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository\GenericModelRepositoryInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Resource\Information\ExposableTypeMap;
use Netlogix\JsonApiOrg\Controller\ApiController;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Netlogix\JsonApiOrg\Schema\ResourceInterface;
use Netlogix\JsonApiOrg\Schema\TopLevel;

/**
 * An action controller dealing with jsonapi.org data structures.
 *
 * @Flow\Scope("singleton")
 */
class GenericModelController extends ApiController
{
    /**
     * @var ExposableTypeMapInterface
     * @Flow\Inject
     */
    protected $exposableTypeMap;

    /**
     * This action is empty but exists to fetch CORS OPTIONS requests
     */
    public function indexAction()
    {
    }

    /**
     * @param string $resourceType
     * @param RequestArgument\Sorting $sort
     * @param RequestArgument\Filter $filter
     * @param RequestArgument\Page $page
     * @return false|string|null
     * @throws FormatNotSupportedException
     */
    public function listAction(
        string $resourceType,
        RequestArgument\Sorting $sort = null,
        RequestArgument\Filter $filter = null,
        RequestArgument\Page $page = null,
        string $apiVersion = ExposableTypeMapInterface::NEXT_VERSION
    ) {
        try {
            $repository = $this->getRepositoryForResourceType($resourceType, $apiVersion);
            if (class_exists('Tideways\Profiler')) {
                \Tideways\Profiler::setTransactionName(
                    sprintf('%s::listAction', $repository->getEntityClassName())
                );
            }
        } catch (UnknownObjectException $e) {
            $this->response->setStatusCode(400);
            $this->response->setContentType(current($this->supportedMediaTypes));
            $result = [
                'errors' => [
                    'code' => 400,
                    'title' => 'The resource type "' . strtolower($resourceType) . '" is not an aggregate root.',
                ]
            ];
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $result = $repository->getSelectable();

        $topLevel = $this->createTopLevelOfCollection(
            $result,
            $sort,
            $filter,
            $page
        );

        $this->view->assign('value', $topLevel);
    }

    /**
     * /**
     * @param $apiVersion string
     */
    public function showAction(ReadModelInterface $resource)
    {
        if (class_exists('Tideways\Profiler')) {
            \Tideways\Profiler::setTransactionName(
                sprintf('%s::showAction', get_class($resource))
            );
        }
        $topLevel = $this->relationshipIterator->createTopLevel($resource);
        $this->view->assign('value', $topLevel);
    }

    /**
     * @param WriteModelInterface $resource
     * @param string $resourceType
     * @throws FormatNotSupportedException
     * @throws UnknownObjectException
     */
    public function createAction(WriteModelInterface $resource, string $resourceType = '')
    {
        if (class_exists('Tideways\Profiler')) {
            \Tideways\Profiler::setTransactionName(
                sprintf('%s::createAction', get_class($resource))
            );
        }
        $this->getRepositoryForResourceType($resourceType)->add($resource);
        $topLevel = $this->relationshipIterator->createTopLevel($resource);
        $this->view->assign('value', $topLevel);
    }

    /**
     * @param ReadModelInterface $resource
     * @param string $relationshipName
     * @throws PropertyNotAccessibleException
     */
    public function showRelationshipAction(ReadModelInterface $resource, string $relationshipName)
    {
        if (class_exists('Tideways\Profiler')) {
            \Tideways\Profiler::setTransactionName(
                sprintf('%s::showRelationshipAction', get_class($resource))
            );
        }
        $resourceResource = $this->findResourceResource($resource);
        $relationship = ObjectAccess::getProperty(
            $resourceResource->getRelationships(),
            $relationshipName
        );
        $this->view->assign('value', $relationship);
    }

    /**
     * @param ReadModelInterface $resource
     * @param string $relationshipName
     * @param RequestArgument\Sorting $sort
     * @param RequestArgument\Filter $filter
     * @param RequestArgument\Page $page
     */
    public function showRelatedAction(
        ReadModelInterface $resource,
        string $relationshipName,
        RequestArgument\Sorting $sort = null,
        RequestArgument\Filter $filter = null,
        RequestArgument\Page $page = null
    ) {
        if (class_exists('Tideways\Profiler')) {
            \Tideways\Profiler::setTransactionName(
                sprintf('%s::showRelatedAction', get_class($resource))
            );
        }
        $resourceResource = $this->findResourceResource($resource);
        $relationship = $resourceResource->getPayloadProperty($relationshipName);

        if ($relationship instanceof ExtraLazyPersistentCollection) {
            $topLevel = $this->createTopLevelOfCollection(
                $relationship,
                $sort,
                $filter,
                $page
            );
        } else {
            $topLevel = $this->relationshipIterator->createTopLevel($relationship);
        }

        $this->view->assign('value', $topLevel);
    }

    protected function createTopLevelOfCollection(
        Selectable $result,
        RequestArgument\Sorting $sort = null,
        RequestArgument\Filter $filter = null,
        RequestArgument\Page $page = null
    ) {
        if ($sort) {
            $result = $result->matching($sort->getCriteria());
        }

        if ($filter) {
            $result = $result->matching($filter->getCriteria());
        }

        if ($page) {
            $limitedResult = $result->matching($page->getCriteria());
        } else {
            $limitedResult = $result;
        }

        $topLevel = $this->relationshipIterator->createTopLevel($limitedResult);
        return $this->applyPaginationMetaToTopLevel($topLevel, count($result), count($limitedResult), $page);
    }

    protected function initializeListAction()
    {
        $this->allowAllPropertiesForArguments('sort');
        $this->allowAllPropertiesForArguments('filter');
        $this->allowAllPropertiesForArguments('page');
    }

    protected function initializeShowRelatedAction()
    {
        $this->allowAllPropertiesForArguments('sort');
        $this->allowAllPropertiesForArguments('filter');
        $this->allowAllPropertiesForArguments('page');
    }

    protected function allowAllPropertiesForArguments(string $argumentName)
    {
        $propertyMappingConfiguration = $this->arguments[$argumentName]->getPropertyMappingConfiguration();
        assert($propertyMappingConfiguration instanceof PropertyMappingConfiguration);

        $propertyMappingConfiguration
            ->allowAllProperties();

        $propertyMappingConfiguration
            ->forProperty(PropertyMappingConfiguration::PROPERTY_PATH_PLACEHOLDER)
            ->allowAllProperties();
    }

    /**
     * @param string $resourceType
     * @param string $propertyName
     * @return string
     */
    protected function getModelClassNameForResourceTypeProperty(
        string $resourceType,
        string $apiVersion,
        string $propertyName
    ): string {
        try {
            $type = TypeHandling::parseType(
                (string)$this->exposableTypeMap
                    ->getPropertyType(
                        typeName: strtolower($resourceType),
                        apiVersion: $apiVersion,
                        propertyName: strtolower($propertyName)
                    )
            );
            return $type['elementType'] ?: $type['type'];
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string $resourceType
     * @param string $apiVersion
     * @return GenericModelRepositoryInterface
     * @throws FormatNotSupportedException
     * @throws UnknownObjectException
     */
    protected function getRepositoryForResourceType(
        string $resourceType,
        string $apiVersion
    ): GenericModelRepositoryInterface {
        $class = $this
            ->exposableTypeMap
            ->getExposableTypeByTypeName($resourceType, $apiVersion)
            ->className;
        $parentClasses = array_merge([$class], class_parents($class));
        foreach ($parentClasses as $modelCandidate) {
            $repositoryCandidate = str_replace(
                    '\\Domain\\Model\\',
                    '\\Domain\\Repository\\',
                    $modelCandidate
                ) . 'Repository';
            if (class_exists($repositoryCandidate)) {
                return $this->objectManager->get($repositoryCandidate);
            }
        }
        throw new UnknownObjectException('No Repository found for class "' . $class . '".', 1264589155);
    }

    /**
     * @throws FormatNotSupportedException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws NoSuchArgumentException
     * @throws PropertyNotAccessibleException
     */
    protected function initializeActionMethodArguments(Arguments $arguments)
    {
        parent::initializeActionMethodArguments($arguments);
        $this->remapActionArguments();
    }

    /**
     * Overrules the controller argument dataType
     *
     * @throws FormatNotSupportedException
     * @throws InvalidArgumentNameException
     * @throws InvalidArgumentTypeException
     * @throws NoSuchArgumentException
     * @throws PropertyNotAccessibleException
     */
    protected function remapActionArguments()
    {
        if (!$this->request->hasArgument('subPackage')) {
            return;
        }
        if (!$this->request->hasArgument('resourceType')) {
            return;
        }

        $exposableType = $this
            ->exposableTypeMap
            ->getExposableTypeByTypeName(
                typeName: $this->request->getArgument('subPackage') . '/' . $this->request->getArgument('resourceType'),
                apiVersion: $this->request->hasArgument('apiVersion')
                    ? $this->request->getArgument('apiVersion')
                    : ExposableTypeMapInterface::NEXT_VERSION
            );

        $this->request->setArgument('resourceType', $exposableType->typeName);

        $relationshipClassName = $this->arguments->hasArgument('relationshipName')
            ? $this->getModelClassNameForResourceTypeProperty(
                resourceType: $exposableType->typeName,
                apiVersion: $exposableType->apiVersion ?? ExposableTypeMapInterface::NEXT_VERSION,
                propertyName: $this->request->getArgument('relationshipName')
            )
            : null;

        $this->remapActionArgument(
            'resource',
            $exposableType->className
        );

        $this->remapActionArgument(
            'sort',
            str_replace(
                '\\Model\\',
                '\\Repository\\Sorting\\',
                $relationshipClassName ?: $exposableType->className
            ) . 'Sorting'
        );

        $this->remapActionArgument(
            'filter',
            str_replace(
                '\\Model\\',
                '\\Repository\\Filter\\',
                $relationshipClassName ?: $exposableType->className
            ) . 'Filter',
            []
        );
    }

    protected function remapActionArgument(string $argumentName, string $modelClassName, $default = null)
    {
        if (!$this->arguments->hasArgument($argumentName)) {
            return;
        }

        if (false === class_exists($modelClassName)) {
            return;
        }

        if (null !== $default && false === $this->request->hasArgument($argumentName)) {
            $this->request->setArgument($argumentName, $default);
        }

        $argumentTemplate = $this->arguments->getArgument($argumentName);
        $newArgument = $this->objectManager->get(
            get_class($argumentTemplate),
            $argumentTemplate->getName(),
            $modelClassName
        );
        assert($newArgument instanceof Argument);

        $this->arguments[$argumentName] = $this->cloneActionArgument($argumentTemplate, $newArgument);
    }

    protected function cloneActionArgument($argumentTemplate, Argument $newArgument)
    {
        foreach (ObjectAccess::getSettablePropertyNames($newArgument) as $propertyName) {
            $propertyValue = ObjectAccess::getProperty($argumentTemplate, $propertyName);
            if ($propertyValue !== ObjectAccess::getProperty($newArgument, $propertyName)) {
                ObjectAccess::setProperty($newArgument, $propertyName, $propertyValue);
            }
        }
        return $newArgument;
    }

    protected function findResourceResource(GenericModelInterface $resource): ResourceInterface
    {
        $resourceInformation = $this->resourceMapper->findResourceInformation($resource);
        return $resourceInformation->getResource($resource);
    }

    protected function applyPaginationMetaToTopLevel(
        TopLevel $topLevel,
        int $resultCount,
        int $limitedResultCount,
        RequestArgument\Page $page = null
    ): TopLevel {
        $meta = $topLevel->getMeta();
        $links = $topLevel->getLinks();

        $meta['page.total'] = $resultCount;

        if (!$page || !$page->isValid()) {
            return $topLevel;
        }

        $pageMeta = $page->getMeta($resultCount, $limitedResultCount);
        foreach ($pageMeta as $key => $value) {
            $meta['page.' . $key] = $value;
        }

        $request = $this->controllerContext->getRequest();
        assert($request instanceof ActionRequest);

        $baseUri = new Uri((string)$request->getHttpRequest()->getUri());

        $withPageNumber = fn (int $pageNumber) => (string)UriHelper::uriWithAdditionalQueryParameters($baseUri, [
            'page' => [
                'size' => $page->getSize(),
                'number' => $pageNumber,
            ]
        ]);

        $links['current'] = $withPageNumber($pageMeta['current']);
        $links['first'] = $withPageNumber(0);
        $links['last'] = $withPageNumber($pageMeta['last-page']);
        $links['next'] = $pageMeta['current'] < $pageMeta['last-page']
            ? $withPageNumber($pageMeta['current'] + 1)
            : null;
        $links['prev'] = $pageMeta['current'] > 0 ? $withPageNumber($pageMeta['current'] - 1) : null;

        return $topLevel;
    }

    protected function mapRequestArgumentsToControllerArguments(ActionRequest $request, Arguments $arguments)
    {
        $apiVersion = $request->hasArgument('apiVersion')
            ? ($request->getArgument('apiVersion') ?: null)
            : null;

        ExposableTypeMap::forceApiVersion($apiVersion, function () use ($request) {
            foreach ($this->arguments as $argument) {
                assert($argument instanceof Argument);
                $argumentName = $argument->getName();
                if ($argument->getMapRequestBody()) {
                    $body = $request->getHttpRequest()->getParsedBody();
                    if (is_array($body)) {
                        $body = Arrays::arrayMergeRecursiveOverrule(
                            $body,
                            $request->getHttpRequest()->getUploadedFiles()
                        );
                    }
                    if ($argumentName === 'resource' && array_keys($body) === ['resource']) {
                        // Backwards compatibility for old-style resource requests
                        $body = $body['resource'];
                    }

                    $argument->setValue($body);
                } elseif ($request->hasArgument($argumentName)) {
                    $argument->setValue($request->getArgument($argumentName));
                } elseif ($argument->isRequired()) {
                    throw new RequiredArgumentMissingException(
                        'Required argument "' . $argumentName . '" is not set.',
                        1298012500
                    );
                }
            }
        });
    }

}
