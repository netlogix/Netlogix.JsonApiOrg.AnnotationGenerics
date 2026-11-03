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
 * Optional: a filter without this interface applies unchanged on related routes.
 */
interface RelationshipAwareFilter extends Filter
{
    /**
     * Called on `<type>/<id>/<relationshipName>` before validate(). May return a different Filter
     * class, e.g. a dedicated relationship filter.
     */
    public function forRelatedResourcesOf(object $parent, string $relationshipName): Filter;
}
