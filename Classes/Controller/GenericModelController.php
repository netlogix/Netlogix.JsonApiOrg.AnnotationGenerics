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
use Neos\Utility\TypeHandling;
use Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine\ExtraLazyPersistentCollection;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments as RequestArgument;
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
        RequestArgument\Page $page = null
    ) {
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

        $result = $repository->getSelectable();

        if ($sort) {
            $result = $result->matching($sort->getCriteria());
        }
        assert($result instanceof ExtraLazyPersistentCollection);

        if ($filter) {
            $result = $result->matching($filter->getCriteria());
        }
        assert($result instanceof ExtraLazyPersistentCollection);

        if ($page) {
            $limitedResult = $result->matching($page->getCriteria());
        } else {
            $limitedResult = $result;
        }
        assert($limitedResult instanceof ExtraLazyPersistentCollection);

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
        $relationship = ObjectAccess::getProperty($resourceResource->getRelationships(),
            $relationshipName);
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
        $resourceResource = $this->findResourceResource($resource);
        $relationship = $resourceResource->getPayloadProperty($relationshipName);

        if ($sort && $relationship instanceof Selectable) {
            $relationship = $relationship->matching($sort->getCriteria());
        }
        assert($relationship instanceof ExtraLazyPersistentCollection);

        if ($filter && $relationship instanceof Selectable) {
            $relationship = $relationship->matching($filter->getCriteria());
        }
        assert($relationship instanceof ExtraLazyPersistentCollection);

        if ($page) {
            $limitedRelationship = $relationship->matching($page->getCriteria());
        } else {
            $limitedRelationship = $relationship;
        }
        assert($limitedRelationship instanceof ExtraLazyPersistentCollection);

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
     * @return string
     * @throws FormatNotSupportedException
     */
    protected function getModelClassNameForResourceType(string $resourceType): string
    {
        return $this->exposableTypeMap->getClassName(strtolower($resourceType));
    }

    /**
     * @param string $resourceType
     * @param string $propertyName
     * @return string
     */
    protected function getModelClassNameForResourceTypeProperty(string $resourceType, string $propertyName): string
    {
        try {
            $type = TypeHandling::parseType(
                (string)$this->exposableTypeMap->getClassNameForProperty(
                    strtolower($resourceType),
                    strtolower($propertyName)
                )
            );
            return $type['elementType'] ?: $type['type'];
        } catch (\Exception $e) {
            return '';
        }
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
        $typeValue = ucfirst($this->request->getArgument('subPackage')) . '/' . ucfirst($this->request->getArgument('resourceType'));
        $this->request->setArgument('resourceType', $typeValue);

        $modelClassName = $this->getModelClassNameForResourceType($typeValue);

        $relationshipClassName = $this->arguments->hasArgument('relationshipName')
            ? $this->getModelClassNameForResourceTypeProperty(
                $typeValue,
                $this->request->getArgument('relationshipName')
            )
            : null;

        $this->remapResourceActionArgument($modelClassName);
        $this->remapSortingActionArgument($relationshipClassName ?: $modelClassName);
        $this->remapFilterActionArgument($relationshipClassName ?: $modelClassName);
    }

    /**
     * @param string $modelClassName
     * @throws NoSuchArgumentException
     * @throws PropertyNotAccessibleException
     */
    protected function remapResourceActionArgument(string $modelClassName)
    {
        if (!$this->arguments->hasArgument('resource')) {
            return;
        }

        $argumentTemplate = $this->arguments->getArgument('resource');
        $newArgument = $this->objectManager->get(
            get_class($argumentTemplate),
            $argumentTemplate->getName(),
            $modelClassName
        );

        $this->arguments['resource'] = $newArgument;
    }

    protected function remapSortingActionArgument(string $modelClassName)
    {
        if (!$this->arguments->hasArgument('sort')) {
            return;
        }

        $filterClassName = str_replace('\\Model\\', '\\Repository\\Sorting\\', $modelClassName) . 'Sorting';

        $argumentTemplate = $this->arguments->getArgument('sort');
        $newArgument = $this->objectManager->get(
            get_class($argumentTemplate),
            $argumentTemplate->getName(),
            $filterClassName
        );

        $this->arguments['sort'] = $this->cloneActionArgument(
            $argumentTemplate,
            $newArgument
        );
    }

    protected function remapFilterActionArgument(string $modelClassName)
    {
        if (!$this->arguments->hasArgument('filter')) {
            return;
        }

        $filterClassName = str_replace('\\Model\\', '\\Repository\\Filter\\', $modelClassName) . 'Filter';

        $argumentTemplate = $this->arguments->getArgument('filter');
        $newArgument = $this->objectManager->get(
            get_class($argumentTemplate),
            $argumentTemplate->getName(),
            $filterClassName
        );

        $this->arguments['filter'] = $this->cloneActionArgument(
            $argumentTemplate,
            $newArgument
        );
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
        RequestArgument\Page $page = null,
        $result = [],
        $limitedResult = []
    ) {
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