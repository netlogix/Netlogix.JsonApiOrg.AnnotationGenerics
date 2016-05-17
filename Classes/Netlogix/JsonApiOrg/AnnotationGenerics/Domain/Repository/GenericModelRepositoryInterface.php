<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\QueryResultInterface;
use TYPO3\Flow\Persistence\RepositoryInterface;

interface GenericModelRepositoryInterface extends RepositoryInterface
{
    /**
     * Takes several key/value pairs where every key targets a property path and
     * the corresponding value the required value.
     *
     * @param array $filter
     * @return QueryResultInterface
     */
    public function findByFilter(array $filter = []);
}