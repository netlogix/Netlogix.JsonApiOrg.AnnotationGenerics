<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Repository;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;
use Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine\ExtraLazyPersistentCollection;

/**
 * @property-read string $entityClassName
 */
trait SelectableRepository
{
    /**
     * @Flow\Inject(lazy=false)
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $entityManager;

    public function getSelectable(): Selectable
    {
        $selectable = ExtraLazyPersistentCollection::createFromEntityPersister(
            $this
                ->entityManager
                ->getUnitOfWork()
                ->getEntityPersister((string)$this->entityClassName)
        );

        if (($this instanceof Repository) && $this->defaultOrderings) {
            $selectable = $selectable
                ->matching(
                    Criteria::create()
                        ->orderBy($this->defaultOrderings)
                );
        }

        return $selectable;
    }
}