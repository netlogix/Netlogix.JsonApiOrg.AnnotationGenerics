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

/**
 * Optional: required filters belong here, not into the constructor. The constructor runs during
 * property mapping, before a related route had the chance to complete the filter.
 */
interface ValidatingFilter extends Filter
{
    /**
     * Throws if the filter is incomplete; the exception decides the status code.
     */
    public function validate(): void;
}
