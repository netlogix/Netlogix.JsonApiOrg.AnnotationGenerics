<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\LazyCriteriaCollection;
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
    ): ExtraLazyPersistentCollection {
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

        return new ExtraLazyPersistentCollection(
            $initializer
        );
    }

    public static function createFromEntityPersister(EntityPersister $entityPersister): ExtraLazyPersistentCollection
    {
        $initializer = function (Criteria $criteria) use ($entityPersister): Collection {
            return new LazyCriteriaCollection(
                $entityPersister,
                $criteria
            );
        };

        return new ExtraLazyPersistentCollection(
            $initializer
        );
    }

    public function matching(Criteria $criteria): ExtraLazyPersistentCollection
    {
        return new self(
            $this->initializer,
            $criteria,
            ... $this->criteria
        );
    }


    protected function getCriteria(): Criteria
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

        return new Criteria(
            $cloneData['getWhereExpression']
                ? Criteria::expr()->andX(... $cloneData['getWhereExpression'])
                : null,
            array_shift($cloneData['getOrderings']),
            array_shift($cloneData['getFirstResult']),
            array_shift($cloneData['getMaxResults'])
        );
    }

    protected function doInitialize()
    {
        $this->collection = ($this->initializer)($this->getCriteria());
    }
}