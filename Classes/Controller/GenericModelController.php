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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Argument;
use Neos\Flow\Mvc\Exception\InvalidArgumentNameException;
use Neos\Flow\Mvc\Exception\InvalidArgumentTypeException;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\ObjectManagement\Exception\UnknownObjectException;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Property\Exception\FormatNotSupportedException;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Utility\Exception\PropertyNotAccessibleException;
use Neos\Utility\ObjectAccess;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments\Page;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ReadModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\WriteModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository\GenericModelRepositoryInterface;
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
     * @param array $filter
     * @param string $resourceType
     * @param Page|null $page
     * @return false|string
     * @throws FormatNotSupportedException
     */
    public function listAction(array $filter = [], string $resourceType = '', Page $page = null)
    {
        try {
            $repository = $this->getRepositoryForResourceType($resourceType);
        } catch (UnknownObjectException $e) {
            $this->response->setStatus(400);
            $this->response->setHeader('Content-Type', current($this->supportedMediaTypes));
            $result = [
                'errors' => [
                    'code' => 400,
                    'title' => 'The resource type "' . strtolower($resourceType) . '" is not an aggregate root.',
                ]
            ];
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $result = $repository->findByFilter($filter);
        $limitedResult = $this->applyPaginationToCollection($result, $page);

        $topLevel = $this->relationshipIterator->createTopLevel($limitedResult);
        $topLevel = $this->applyPaginationMetaToTopLevel($topLevel, $page, $result, $limitedResult);

        $this->view->assign('value', $topLevel);
    }

    /**
     * @param ReadModelInterface $resource
     */
    public function showAction(ReadModelInterface $resource)
    {
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
        $resourceResource = $this->findResourceResource($resource);
        $relationship = \Neos\Utility\ObjectAccess::getProperty($resourceResource->getRelationships(),
            $relationshipName);
        $this->view->assign('value', $relationship);
    }

    /**
     * @param ReadModelInterface $resource
     * @param string $relationshipName
     * @param Page $page
     */
    public function showRelatedAction(ReadModelInterface $resource, string $relationshipName, Page $page = null)
    {
        $resourceResource = $this->findResourceResource($resource);
        $relationship = $resourceResource->getPayloadProperty($relationshipName);
        $limitedRelationship = $this->applyPaginationToCollection($relationship, $page);

        $topLevel = $this->relationshipIterator->createTopLevel($limitedRelationship);
        $topLevel = $this->applyPaginationMetaToTopLevel($topLevel, $page, $relationship, $limitedRelationship);

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
        $this->getRepositoryForResourceType($resourceType)->add($resource);
        $topLevel = $this->relationshipIterator->createTopLevel($resource);
        $this->view->assign('value', $topLevel);
    }

    protected function initializeListAction()
    {
        $propertyMappingConfiguration = $this->arguments['page']->getPropertyMappingConfiguration();
        assert($propertyMappingConfiguration instanceof PropertyMappingConfiguration);
        $propertyMappingConfiguration->allowAllProperties();
    }

    protected function initializeShowRelatedAction()
    {
        $propertyMappingConfiguration = $this->arguments['page']->getPropertyMappingConfiguration();
        assert($propertyMappingConfiguration instanceof PropertyMappingConfiguration);
        $propertyMappingConfiguration->allowAllProperties();
    }

    /**
     * @param string $resourceType
     * @return string
     * @throws FormatNotSupportedException
     */
    protected function getModelClassNameForResourceType(string $resourceType): string
    {
        return $this->exposableTypeMap->getClassName(strtolower($resourceType));
    }

    /**
     * @param string $resourceType
     * @return GenericModelRepositoryInterface
     * @throws FormatNotSupportedException
     * @throws UnknownObjectException
     */
    protected function getRepositoryForResourceType(string $resourceType): GenericModelRepositoryInterface
    {
        $class = $this->getModelClassNameForResourceType($resourceType);
        $parentClasses = array_merge([$class], class_parents($class));
        foreach ($parentClasses as $modelCandidate) {
            $repositoryCandidate = str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\',
                    $modelCandidate) . 'Repository';
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
    protected function initializeActionMethodArguments()
    {
        parent::initializeActionMethodArguments();
        $this->determineResourceArgumentType();
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
    protected function determineResourceArgumentType()
    {
        if (!$this->request->hasArgument('subPackage')) {
            return;
        }
        if (!$this->request->hasArgument('resourceType')) {
            return;
        }
        $typeValue = ucfirst($this->request->getArgument('subPackage')) . '/' . ucfirst($this->request->getArgument('resourceType'));
        $this->request->setArgument('resourceType', $typeValue);

        if (!$this->arguments->hasArgument('resource')) {
            return;
        }

        $argumentTemplate = $this->arguments->getArgument('resource');
        /** @var Argument $newArgument */
        $newArgument = $this->objectManager->get(get_class($argumentTemplate), $argumentTemplate->getName(),
            $this->getModelClassNameForResourceType($typeValue));
        foreach (ObjectAccess::getSettablePropertyNames($newArgument) as $propertyName) {
            $propertyValue = ObjectAccess::getProperty($argumentTemplate, $propertyName);
            if ($propertyValue !== ObjectAccess::getProperty($newArgument, $propertyName)) {
                ObjectAccess::setProperty($newArgument, $propertyName, $propertyValue);
            }
        }
        $this->arguments['resource'] = $newArgument;
    }

    protected function findResourceResource(GenericModelInterface $resource): ResourceInterface
    {
        $resourceInformation = $this->resourceMapper->findResourceInformation($resource);
        return $resourceInformation->getResource($resource);
    }

    protected function applyPaginationToCollection($objects, Page $page = null)
    {
        if (!is_object($page)) {
            return $objects;
        }

        if (is_object($objects) && $objects instanceof QueryResultInterface) {
            $query = clone $objects->getQuery();
            $query->setLimit($page->getSize());

            if ($page->getOffset()) {
                $query->setOffset($page->getOffset());
            }
            return $query->execute();
        }
        if (is_object($objects) && $objects instanceof \ArrayObject) {
            $objects = $objects->getArrayCopy();
        } elseif (is_object($objects) && is_callable([$objects, 'toArray'])) {
            $objects = $objects->toArray();
        }

        if (is_array($objects)) {
            return array_slice((array)$objects, $page->getNumber(), $page->getSize());
        } else {
            $page->markAsInvalid();
            return $objects;
        }
    }

    protected function applyPaginationMetaToTopLevel(TopLevel $topLevel, Page $page = null, $result = [], $limitedResult = [])
    {
        if (!is_object($page) || !$page->isValid()) {
            return $topLevel;
        }

        $count = count($result);
        $limitedCount = count($limitedResult);

        if ($count === $limitedCount || !$limitedCount) {
            return $topLevel;
        }

        $paginationMeta = [
            'current' => $page->getNumber(),
            'per-page' => $page->getSize(),
            'from' => $page->getOffset(),
            'to' => $page->getOffset() + $limitedCount - 1,
            'total' => $count,
            'last-page' => ceil($count / $page->getSize()) - 1

        ];
        $topLevel->getMeta()->offsetSet('page', $paginationMeta);

        $links = $topLevel->getLinks();

        /** @var ActionRequest $request */
        $request = $this->controllerContext->getRequest();
        $uri = new Uri((string)$request->getHttpRequest()->getUri());
        $arguments = $uri->getArguments();

        $arguments['page'] = [
            'size' => $page->getSize(),
            'number' => $page->getNumber()
        ];
        $uri->setQuery(http_build_query($arguments));
        $links['current'] = (string)$uri;

        $arguments['page']['number'] = 0;
        $uri->setQuery(http_build_query($arguments));
        $links['first'] = (string)$uri;

        $arguments['page']['number'] = $paginationMeta['last-page'];
        $uri->setQuery(http_build_query($arguments));
        $links['last'] = (string)$uri;

        if ($paginationMeta['current'] < $paginationMeta['last-page']) {
            $arguments['page']['number'] = $page->getNumber() + 1;
            $uri->setQuery(http_build_query($arguments));
            $links['next'] = (string)$uri;
        } else {
            $links['next'] = null;
        }

        if ($paginationMeta['current'] > 0) {
            $arguments['page']['number'] = $page->getNumber() - 1;
            $uri->setQuery(http_build_query($arguments));
            $links['prev'] = (string)$uri;
        } else {
            $links['prev'] = null;
        }

        return $topLevel;
    }
}