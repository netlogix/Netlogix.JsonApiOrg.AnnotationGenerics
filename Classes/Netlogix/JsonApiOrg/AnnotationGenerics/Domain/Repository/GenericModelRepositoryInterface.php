<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\RepositoryInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments\Page;

interface GenericModelRepositoryInterface extends RepositoryInterface
{
    /**
     * Takes several key/value pairs where every key targets a property path and
     * the corresponding value the required value.
     *
     * @param array $filter
     * @param Page $page
     * @return QueryResultInterface
     */
    public function findByFilter(array $filter = [], Page $page = null);
}