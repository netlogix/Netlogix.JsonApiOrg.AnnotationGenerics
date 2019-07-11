<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\LazyCriteriaCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ExtraLazyPersistentCollection extends AbstractLazyCollection implements Selectable, Collection
{
    use ReadonlyCollectionTrait;

    protected $criteria = [];

    private $initializer;

    protected function __construct(callable $initializer, Criteria ... $criteria)
    {
        $this->criteria = $criteria;
        $this->initializer = $initializer;
    }

    public static function createFromCollection(Collection $collection
    ): self {
        if (!$collection instanceof Selectable) {
            throw new \LogicException(
                sprintf(
                    'Extra lazy persistent collections need to be both, Collections and Selectables. %s given.',
                    get_class($collection)
                ),
                1561999403
            );
        }
        $initializer = function (Criteria $criteria) use ($collection): Collection {
            return $collection->matching($criteria);
        };

        $collection = new self(
            $initializer
        );

        if ($collection instanceof PersistentCollection) {
            return self::addDefaultOrdering($collection, $collection->getTypeClass());
        }

        return $collection;
    }

    public static function createFromEntityPersister(EntityPersister $entityPersister): self
    {
        $initializer = function (Criteria $criteria) use ($entityPersister): Collection {
            return new LazyCriteriaCollection(
                $entityPersister,
                $criteria
            );
        };

        $collection = new self(
            $initializer
        );

        return self::addDefaultOrdering($collection, $entityPersister->getClassMetadata());
    }

    public function matching(Criteria $criteria): Collection
    {
        return new self(
            $this->initializer,
            $criteria,
            ... $this->criteria
        );
    }

    public function revertOrdering(): self
    {
        $criteria = $this->getCriteria();
        $ordering = $criteria->getOrderings();
        $reverse = array_map(
            function (string $ordering) {
                return $ordering === Criteria::ASC ? Criteria::DESC : Criteria::ASC;
            },
            $ordering
        );
        return new self(
            $this->initializer,
            $criteria->orderBy($reverse)
        );
    }

    public function getCriteria(): Criteria
    {
        $cloneData = [
            'getWhereExpression' => [],
            'getOrderings' => [],
            'getFirstResult' => [],
            'getMaxResults' => [],
        ];

        foreach (array_keys($cloneData) as $getter) {
            foreach ($this->criteria as $merge) {
                $cloneData[$getter][] = $merge->{$getter}();
            }
            $cloneData[$getter] = array_filter($cloneData[$getter]);
        }

        $where = $cloneData['getWhereExpression']
            ? Criteria::expr()->andX(... $cloneData['getWhereExpression'])
            : null;

        $orderings = array_reduce(
            array_reverse($cloneData['getOrderings']),
            function (array $last, array $next) {
                return $next + array_diff_key($last, $next);
            },
            []
        );

        return new Criteria(
            $where,
            $orderings,
            array_shift($cloneData['getFirstResult']),
            array_shift($cloneData['getMaxResults'])
        );
    }

    protected function doInitialize()
    {
        $this->collection = ($this->initializer)($this->getCriteria());
    }

    protected static function addDefaultOrdering(self $collection, ClassMetadata $metadata)
    {
        $identifierFieldNames = $metadata->getIdentifierFieldNames();
        $defaultOrdering = array_fill_keys($identifierFieldNames, Criteria::ASC);

        return $collection
            ->matching(
                Criteria::create()
                    ->orderBy($defaultOrdering)
            );
    }
}