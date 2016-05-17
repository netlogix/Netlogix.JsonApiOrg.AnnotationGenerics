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
use TYPO3\Flow\Annotations as Flow;

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
     */
    public function listAction(array $filter = [], $resourceType = '')
    {
        $result = $this->getRepositoryForResourceType($resourceType)->findByFilter($filter);
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
        $relationship = \TYPO3\Flow\Reflection\ObjectAccess::getProperty($resourceResource->getRelationships(), $relationshipName);
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
     */
    public function createAction(WriteModelInterface $resource)
    {
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
     * Creates the repository reponsible for the given type
     *
     * @param string $resourceType
     * @return GenericModelRepositoryInterface
     */
    protected function getRepositoryForResourceType($resourceType)
    {
        $repositoryClassName = str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\', $this->getModelClassNameForResourceType($resourceType)) . 'Repository';
        return $this->objectManager->get($repositoryClassName);
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