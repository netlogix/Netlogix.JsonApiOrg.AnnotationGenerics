<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\RepositoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Utility\ObjectAccess;
use Neos\Utility\TypeHandling;
use Doctrine\ORM\Mapping as ORM;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments\Page;

/**
 * @var $this RepositoryInterface|FindByFilterTrait
 */
trait FindByFilterTrait
{
    /**
     * @var \Neos\Flow\Property\TypeConverter\ObjectConverter
     * @Flow\Inject
     */
    protected $objectConverter;

    /**
     * @var \Neos\Flow\Property\PropertyMapper
     * @Flow\Inject
     */
    protected $propertyMapper;

    /**
     * @var \Neos\Flow\Reflection\ReflectionService
     * @Flow\Inject
     */
    protected $reflectionService;

    /**
     * Takes several key/value pairs where every key targets a property path and
     * the corresponding value the required value.
     *
     * @param array $filter
     * @param Page $page
     * @return QueryResultInterface
     */
    public function findByFilter(array $filter = [], Page $page = null)
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

        if ($page) {
            $query->setLimit($page->getSize());
            if ($page->getOffset()) {
                $query->setOffset($page->getOffset());
            }
        }

        return $query->execute();
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
    protected function addPropertyFilterConstraint(QueryInterface $query, $propertyPath, $value)
    {
        if ($propertyPath === '__identity') {
            $entityClassName = $this->getEntityClassName();
            if (property_exists($entityClassName, 'Persistence_Object_Identifier')) {
                return $query->equals('Persistence_Object_Identifier', $value);
            } else {
                foreach ($this->reflectionService->getPropertyNamesByAnnotation($entityClassName, ORM\Id::class) as $propertyName) {
                    return $query->equals($propertyName, $value);
                }
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
                case 'DateTime':
                    return $this->addFilterConstraintForDateTimeProperty($query, $propertyPath, $value);

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
     * @param mixed $value
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
     * Numeric filters means the target property is of type float or integer.
     * Input are either nummbers or arrays of numbers. Both might be not
     * actual numbers but strings of numbers, prefixed with a compare operator.
     *
     * /resource?filter[number]=100
     * /resource?filter[number][]=gt 100&filter[number][]=lt 200
     *
     * @param QueryInterface $query
     * @param string $propertyPath
     * @param array <string> $value
     * @return object
     */
    protected function addFilterConstraintForNumericProperty(QueryInterface $query, $propertyPath, $value)
    {
        $constraints = [];
        $values = (array)$value;

        foreach ($values as $constraintValue) {
            if (preg_match('%^\\s*(?<operator>eq|ne|gt|ge|lt|le)\\s*(?<value>\\d+(\\.\\d+)?)\\s*$%i', $constraintValue,
                $matches)) {
                $operator = $matches['operator'];
                $constraintValue = (float)$matches['value'];
            } else {
                $operator = 'eq';
                $constraintValue = (float)$constraintValue;
            }
            switch ($operator) {
                case 'eq':
                    $constraints[] = $query->equals($propertyPath, $constraintValue);
                    break;
                case 'ne':
                    $constraints[] = $query->logicalNot($query->equals($propertyPath, $constraintValue));
                    break;
                case 'gt':
                    $constraints[] = $query->greaterThan($propertyPath, $constraintValue);
                    break;
                case 'ge':
                    $constraints[] = $query->greaterThanOrEqual($propertyPath, $constraintValue);
                    break;
                case 'lt':
                    $constraints[] = $query->lessThan($propertyPath, $constraintValue);
                    break;
                case 'le':
                    $constraints[] = $query->lessThanOrEqual($propertyPath, $constraintValue);
                    break;
            }
        }
        if ($constraints) {
            return $query->logicalAnd($constraints);
        } else {
            return $query->equals($propertyPath, $value);
        }
    }

    /**
     * Date type filters means the target property is of type datetime.
     * Input is a string that can be used to construct a DateTime object,
     * prefixed by a compare operator.
     *
     * /resource?filter[date]=gt now
     * /resource?filter[date][]=gt -1 week&filter[date][]=lt +1 week
     *
     * @param QueryInterface $query
     * @param string $propertyPath
     * @param array <string> $value
     * @return object
     */
    protected function addFilterConstraintForDateTimeProperty(QueryInterface $query, $propertyPath, $value)
    {
        $constraints = [];
        $values = (array)$value;

        foreach ($values as $constraintValue) {
            if (preg_match('%^(?<operator>eq|gt|ge|lt|le)\\s*(?<value>.*)$%i', $constraintValue, $matches)) {
                $operator = $matches['operator'];
                $constraintValue = new \DateTime($matches['value']);
            } else {
                $operator = 'eq';
                $constraintValue = new \DateTime($constraintValue);
            }
            switch (strtolower($operator)) {
                case 'eq':
                    $constraints[] = $query->equals($propertyPath, $constraintValue);
                    break;
                case 'gt':
                    $constraints[] = $query->greaterThan($propertyPath, $constraintValue);
                    break;
                case 'ge':
                    $constraints[] = $query->greaterThanOrEqual($propertyPath, $constraintValue);
                    break;
                case 'lt':
                    $constraints[] = $query->lessThan($propertyPath, $constraintValue);
                    break;
                case 'le':
                    $constraints[] = $query->lessThanOrEqual($propertyPath, $constraintValue);
                    break;
            }
        }
        if ($constraints) {
            return $query->logicalAnd($constraints);
        } else {
            return $query->equals($propertyPath, $value);
        }
    }

    /**
     * @param QueryInterface $query
     * @param string $propertyPath
     * @param string $value
     * @return object
     */
    protected function addLikeFilterConstraintForProperty(QueryInterface $query, $propertyPath, $value) {
        $searchValue = trim($value, '*');
        $constraints = [
            $query->equals($propertyPath, $searchValue, false),
        ];
        if (substr($value, 0, 1) === '*') {
            $constraints[] = $query->like($propertyPath, '%' . $searchValue, false);
        }
        if (substr($value, -1) === '*') {
            $constraints[] = $query->like($propertyPath, $searchValue . '%', false);
        }
        if (substr($value, 0, 1) === '*' && substr($value, -1) === '*') {
            $constraints[] = $query->like($propertyPath, '%' . $searchValue . '%', false);
        }
        return $query->logicalOr($constraints);
    }
}