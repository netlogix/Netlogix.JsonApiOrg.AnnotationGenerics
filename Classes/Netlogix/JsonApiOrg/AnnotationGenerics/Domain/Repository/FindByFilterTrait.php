<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Persistence\QueryInterface;
use TYPO3\Flow\Persistence\QueryResultInterface;

trait FindByFilterTrait
{
    /**
     * Takes several key/value pairs where every key targets a property path and
     * the corresponding value the required value.
     *
     * @param array $filter
     * @return QueryResultInterface
     */
    public function findByFilter(array $filter = [])
    {
        /** @var QueryInterface $query */
        $query = $this->createQuery();

        $logicalAnd = [];
        $constraint = $query->getConstraint();
        if ($constraint) {
            $logicalAnd[] = $constraint;
        }

        foreach ($filter as $propertyPath => $value) {
            $logicalAnd[] = $this->addPropertyFilterConstraint($query, $propertyPath, $value);
        }

        if (count($logicalAnd)) {
            $query->matching($query->logicalAnd($logicalAnd));
        }

        return $query->execute();
    }

    /**
     * @param QueryInterface $query
     * @param string $propertyPath
     * @param $value
     * @return object
     */
    protected function addPropertyFilterConstraint(QueryInterface $query, $propertyPath, $value)
    {
        return $query->equals($propertyPath, $value);
    }
}