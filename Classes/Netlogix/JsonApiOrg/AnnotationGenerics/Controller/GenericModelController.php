<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Controller;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\WriteModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ReadModelInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository\GenericModelRepositoryInterface;
use Netlogix\JsonApiOrg\Controller\ApiController;
use Netlogix\JsonApiOrg\Resource\Information\ExposableTypeMapInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\Exception\UnknownObjectException;

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
     * @return string|null
     */
    public function listAction(array $filter = [], $resourceType = '')
    {
        try {
            $repository = $this->getRepositoryForResourceType($resourceType);
        } catch (UnknownObjectException $e)  {
            $this->response->setStatus(400);
            $this->response->setHeader('Content-Type', current($this->supportedMediaTypes));
            $result = ['errors' => [
                'code' => 400,
                'title' => 'The resource type "' . strtolower($resourceType) . '" is not an aggregate root.',
            ]];
            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $result = $repository->findByFilter($filter);
        $topLevel = $this->relationshipIterator->createTopLevel($result);
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
     */
    public function showRelationshipAction(ReadModelInterface $resource, $relationshipName)
    {
        $resourceResource = $this->findResourceResource($resource);
        $relationship = \Neos\Utility\ObjectAccess::getProperty($resourceResource->getRelationships(), $relationshipName);
        $this->view->assign('value', $relationship);
    }

    /**
     * @param ReadModelInterface $resource
     * @param string $relationshipName
     */
    public function showRelatedAction(ReadModelInterface $resource, $relationshipName)
    {
        $resourceResource = $this->findResourceResource($resource);
        $relationship = $resourceResource->getPayloadProperty($relationshipName);
        $topLevel = $this->relationshipIterator->createTopLevel($relationship);
        $this->view->assign('value', $topLevel);
    }

    /**
     * @param WriteModelInterface $resource
     * @param string $resourceType
     */
    public function createAction(WriteModelInterface $resource, $resourceType = '')
    {
        $this->getRepositoryForResourceType($resourceType)->add($resource);
        $topLevel = $this->relationshipIterator->createTopLevel($resource);
        $this->view->assign('value', $topLevel);
    }

    /**
     * Returns the model class name for the given type
     *
     * @param $resourceType
     * @return string|null
     */
    protected function getModelClassNameForResourceType($resourceType)
    {
        return $this->exposableTypeMap->getClassName(strtolower($resourceType));
    }

    /**
     * Creates the repository responsible for the given type
     *
     * @param string $resourceType
     * @return GenericModelRepositoryInterface
     * @throws UnknownObjectException
     */
    protected function getRepositoryForResourceType($resourceType)
    {
        $class = $this->getModelClassNameForResourceType($resourceType);
        $parentClasses = array_merge([$class], class_parents($class));
        foreach ($parentClasses as $modelCandidate) {
            $repositoryCandidate = str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\', $modelCandidate) . 'Repository';
            if (class_exists($repositoryCandidate)) {
                return $this->objectManager->get($repositoryCandidate);
            }
        }
        throw new UnknownObjectException('No Repository found for class "' . $class . '".', 1264589155);
    }

    /**
     * @inheritdoc
     */
    protected function mapRequestArgumentsToControllerArguments()
    {
        $this->determineResourceArgumentType();
        parent::mapRequestArgumentsToControllerArguments();
    }

    /**
     * Overrules the controller argument dataType
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
        $resourceArgument = $this->arguments->getArgument('resource');
        $resourceArgument->setDataType($this->getModelClassNameForResourceType($typeValue));
    }

    /**
     * @param $resource
     * @return \Netlogix\JsonApiOrg\Schema\ResourceInterface
     */
    protected function findResourceResource($resource)
    {
        $resourceInformation = $this->resourceMapper->findResourceInformation($resource);
        return $resourceInformation->getResource($resource);
    }
}