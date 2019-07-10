<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Collections\Criteria;
use Neos\Utility\ObjectAccess;

class Page
{
    /**
     * @var int
     */
    protected $number = 0;

    /**
     * @var int
     */
    protected $size = 20;

    /**
     * @var bool
     */
    protected $valid = true;

    /**
     * @var Cursor
     */
    protected $cursor;

    /**
     * @param int $number
     * @param int $size
     * @param Cursor $cursor
     */
    public function __construct(int $number = null, int $size = null, Cursor $cursor = null)
    {
        if (!is_null($number)) {
            $this->number = $number;
        }
        if (!is_null($size)) {
            $this->size = $size;
        }
        if (!is_null($cursor)) {
            $this->cursor = $cursor;
            $this->number = $cursor->getPageNumber();
            $this->size = $cursor->getPageSize();
        }
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->getSize() * $this->getNumber();
    }

    public function isValid(): bool
    {
        return $this->size > 0 && $this->number >= 0;
    }

    public function getCursor(): ?Cursor
    {
        return $this->cursor;
    }

    public function getCriteria(): Criteria
    {
        $cursor = $this->getCursor();
        if ($cursor && $cursor->isValid()) {
            return Criteria::create()
                ->setMaxResults($cursor->getPageSize())
                ->where($cursor->getItemExpression());
        } else {
            return Criteria::create()
                ->setMaxResults($this->getSize())
                ->setFirstResult($this->getOffset());
        }
    }

    public function getMeta(int $totalNumberOfResults, int $limitedNumberOfResults)
    {
        if (!$this->isValid()) {
            return [];
        }

        return [
            'current' => $this->getNumber(),
            'per-page' => $this->getSize(),
            'from' => $this->getOffset(),
            'to' => $this->getOffset() + $limitedNumberOfResults - 1,
            'last-page' => (int)ceil($totalNumberOfResults / $this->getSize()) - 1

        ];
    }

    public function cursorToNextPage(int $totalNumberOfResults, Criteria $criteria, $lastItemOnCurrentPage): Cursor
    {
        return Cursor::create()
            ->withDirection(1)
            ->withPageNumber($this->getNumber() + 1)
            ->withPageSize($this->getSize())
            ->withTotalNumberOfResults($totalNumberOfResults)
            ->withOrderings($criteria->getOrderings())
            ->withItem(
                ...
                $this->extractOrderingAttributes($lastItemOnCurrentPage, $criteria)
            );
    }

    public function cursorToPrevPage(int $totalNumberOfResults, Criteria $criteria, $firstItemOnCurrentPage): Cursor
    {
        return Cursor::create()
            ->withDirection(-1)
            ->withPageNumber($this->getNumber() - 1)
            ->withPageSize($this->getSize())
            ->withTotalNumberOfResults($totalNumberOfResults)
            ->withOrderings($criteria->getOrderings())
            ->withItem(
                ...
                $this->extractOrderingAttributes($firstItemOnCurrentPage, $criteria)
            );
    }

    protected function extractOrderingAttributes($subject, Criteria $criteria)
    {
        return array_map(
            function (string $property) use ($subject) {
                return (string)ObjectAccess::getProperty($subject, $property, true);
            },
            array_keys($criteria->getOrderings())
        );
    }
}