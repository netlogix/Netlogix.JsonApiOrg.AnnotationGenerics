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
     * @param int $number
     * @param int $size
     */
    public function __construct(int $number = null, int $size = null)
    {
        if (!is_null($number) && $number >= 0) {
            $this->number = $number;
        }
        if (!is_null($size) && $size >= 0) {
            $this->size = $size;
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

    public function markAsInvalid()
    {
        $this->valid = false;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}