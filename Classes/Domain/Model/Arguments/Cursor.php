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
use Doctrine\Common\Collections\Expr\Expression;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class Cursor
{
    const PAGE_NUMBER = 0;
    const PAGE_SIZE = 1;
    const TOTAL_NUMBER_OF_RESULTS = 2;
    const DIRECTION = 3;
    const ORDER_FIELDS = 4;
    const ORDER_DIRECTIONS = 5;
    const ORDER_VALUES = 6;

    const URL_SAFE_CHARACTERS_MAP = [
        '+' => '-',
        '/' => '_',
        '=' => '.',
    ];

    /**
     * @var int
     */
    protected $pageNumber = 0;

    /**
     * @var int
     */
    protected $pageSize = 0;

    /**
     * @var int
     */
    protected $totalNumberOfResults = 0;

    /**
     * @var int
     */
    protected $direction = 1;

    /**
     * @var string[]
     */
    protected $orderings = [];

    /**
     * @var string[]
     */
    protected $item = [];

    /**
     * @param string $string
     */
    public function __construct(string $string)
    {
        $data = self::decrypt($string);

        $this->pageNumber = $data[self::PAGE_NUMBER];
        $this->pageSize = $data[self::PAGE_SIZE];
        $this->totalNumberOfResults = $data[self::TOTAL_NUMBER_OF_RESULTS];
        $this->direction = $data[self::DIRECTION];
        $this->orderings = array_combine($data[self::ORDER_FIELDS], $data[self::ORDER_DIRECTIONS]);
        $this->item = $data[self::ORDER_VALUES];
    }

    public function __toString()
    {
        return self::encrypt([
            self::PAGE_NUMBER => $this->pageNumber,
            self::PAGE_SIZE => $this->pageSize,
            self::TOTAL_NUMBER_OF_RESULTS => $this->totalNumberOfResults,
            self::DIRECTION => $this->direction,
            self::ORDER_FIELDS => array_keys($this->orderings),
            self::ORDER_DIRECTIONS => array_values($this->orderings),
            self::ORDER_VALUES => $this->item
        ]);
    }

    public static function create(): Cursor
    {
        $template = sprintf('O:%d:"%s":0:{}', strlen(Cursor::class), Cursor::class);
        return unserialize($template);
    }

    public function withPageNumber(int $pageNumber): Cursor
    {
        $new = clone $this;
        $new->pageNumber = $pageNumber;
        return $new;
    }

    public function withPageSize(int $pageSize): Cursor
    {
        $new = clone $this;
        $new->pageSize = $pageSize;
        return $new;
    }

    public function withTotalNumberOfResults(int $totalNumberOfResults): Cursor
    {
        $new = clone $this;
        $new->totalNumberOfResults = $totalNumberOfResults;
        return $new;
    }

    public function withDirection(int $direction): Cursor
    {
        $new = clone $this;
        $new->direction = $direction <=> 0 ?: 1;
        return $new;
    }

    public function withOrderings(array $orderings): Cursor
    {
        $new = clone $this;
        $new->orderings = $orderings;
        return $new;
    }

    public function withItem(string ... $item): Cursor
    {
        $new = clone $this;
        $new->item = $item;
        return $new;
    }

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getTotalNumberOfResults(): int
    {
        return $this->totalNumberOfResults;
    }

    public function getDirection(): int
    {
        return $this->direction;
    }

    public function isValid(): bool
    {
        if (!$this->orderings) {
            return false;
        }
        if (count($this->orderings) !== count($this->item)) {
            return false;
        }
        return true;
    }

    public function getItemExpression(): Expression
    {
        $relevantFieldComparisons = $this->getRelevantFieldsFromItem();
        $criteria = Criteria::create();
        $expr = Criteria::expr();
        while ($relevantFieldComparisons) {
            $current = array_pop($relevantFieldComparisons);
            $criteria->orWhere(
                ($current['direction'] === Criteria::ASC xor $this->direction === 1)
                    ? $expr->lt($current['field'], $current['value'])
                    : $expr->gt($current['field'], $current['value'])
            );
            $next = current($relevantFieldComparisons);
            if ($next) {
                $criteria->andWhere(
                    $expr->eq($next['field'], $next['value'])
                );
            }
        }
        return $criteria->getWhereExpression();
    }

    protected function getRelevantFieldsFromItem()
    {
        $compare = [];
        foreach ($this->orderings as $field => $direction) {
            $compare[] = ['field' => $field, 'direction' => $direction];
        }
        foreach ($this->item as $position => $value) {
            $compare[$position]['value'] = $value;
        }
        return $compare;
    }

    protected static function encrypt(array $data): string
    {
        ksort($data);
        return
            str_replace(
                array_keys(self::URL_SAFE_CHARACTERS_MAP),
                array_values(self::URL_SAFE_CHARACTERS_MAP),
                base64_encode(
                    gzdeflate(
                        json_encode(
                            $data
                        )
                    )
                )
            );
    }

    protected static function decrypt(string $encrypted): array
    {
        return
            json_decode(
                gzinflate(
                    base64_decode(
                        str_replace(
                            array_values(self::URL_SAFE_CHARACTERS_MAP),
                            array_keys(self::URL_SAFE_CHARACTERS_MAP),
                            $encrypted
                        )
                    )
                ),
                true
            );
    }
}