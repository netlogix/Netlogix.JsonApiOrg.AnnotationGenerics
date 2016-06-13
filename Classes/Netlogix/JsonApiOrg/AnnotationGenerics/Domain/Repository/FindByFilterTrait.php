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
use TYPO3\Flow\Persistence\RepositoryInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Property\PropertyMappingConfiguration;
use TYPO3\Flow\Utility\TypeHandling;

/**
 * @var $this RepositoryInterface|FindByFilterTrait
 */
trait FindByFilterTrait
{
    /**
     * @var \TYPO3\Flow\Property\TypeConverter\ObjectConverter
     * @Flow\Inject
     */
    protected $objectConverter;

    /**
     * @var \TYPO3\Flow\Property\PropertyMapper
     * @Flow\Inject
     */
    protected $propertyMapper;

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

    protected function addPropertyFilterConstraint(QueryInterface $query, $propertyPath, $value)
    {
        if ($propertyPath === '__identity') {
            $entityClassName = $this->getEntityClassName();
            if (property_exists($entityClassName, 'Persistence_Object_Identifier')) {
                return $query->equals('Persistence_Object_Identifier', $value);
            }
        }

        if (strpos($propertyPath, '.') === false) {
            $type = TypeHandling::parseType($this->objectConverter->getTypeOfChildProperty(
                $this->getEntityClassName(),
                $propertyPath,
                new PropertyMappingConfiguration()));
            switch ($type['type']) {
                case 'boolean':
                    return $this->addFilterConstraintForBooleanProperty($query, $propertyPath, $value);
                case 'integer':
                case 'float':
                    return $this->addFilterConstraintForNumericProperty($query, $propertyPath, $value);
                    break;

            }
        }

        return $query->equals($propertyPath, $value);
    }

    /**
     * The strings "true" and "false" are trated as the coresponding boolean values.
     * The "0" number is treated as boolean false.
     * Every other input is handled as "true".
     *
     * @param QueryInterface $query
     * @param $propertyPath
     * @param $value
     * @return object
     */
    protected function addFilterConstraintForBooleanProperty(QueryInterface $query, $propertyPath, $value)
    {
        if ((string)$value === (string)(int)$value) {
            return $query->equals($propertyPath, (bool)$value);
        } elseif (strtolower($value) === 'false') {
            return $query->equals($propertyPath, false);
        } else {
            return $query->equals($propertyPath, true);
        }
    }

    /**
     * See: http://discuss.jsonapi.org/t/share-propose-a-filtering-strategy/257
     * See: http://docs.oasis-open.org/odata/odata/v4.0/odata-v4.0-part2-url-conventions.html
     *
     * The whole world of OData comparison might be way beyond the level of power we want to
     * expose, so I'm against adding this "as is".
     *
     * But as long as the target is a property of the resource and not a property of a relationship
     * (which means it targets "price", not "categories.price") I guess providing basic support for
     * numeric comparison such as "eq" (default), "ne", "gt", "ge", "lt" and "le" should be part
     * of this library.
     *
     * @param QueryInterface $query
     * @param string $propertyPath
     * @param $value
     * @return object
     */
    protected function addFilterConstraintForNumericProperty(QueryInterface $query, $propertyPath, $value)
    {
        if (preg_match('%^\\s*(?<operator>eq|ne|gt|ge|lt|le)\\s*(?<value>\\d+(\\.\\d+)?)\\s*$%i', $value, $matches)) {
            $operator = $matches['operator'];
            $value = (float)$matches['value'];
        } else {
            $operator = 'eq';
            $value = (float)$value;
        }

        switch ($operator) {
            case 'eq':
                return $query->equals($propertyPath, $value);
            case 'ne':
                return $query->logicalNot($query->equals($propertyPath, $value));
            case 'gt':
                return $query->greaterThan($propertyPath, $value);
            case 'ge':
                return $query->greaterThanOrEqual($propertyPath, $value);
            case 'lt':
                return $query->lessThan($propertyPath, $value);
            case 'le':
                return $query->lessThanOrEqual($propertyPath, $value);
        }
    }
}