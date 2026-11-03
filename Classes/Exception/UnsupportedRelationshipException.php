<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Exception;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Exception;

/**
 * A RelationshipAwareFilter was asked for a relationship it does not know. Status 500: the route
 * exists, the filter lacks a case.
 */
class UnsupportedRelationshipException extends Exception
{
    public static function forRelatedResourcesOf(object $parent, string $relationshipName): self
    {
        return new self(
            sprintf('The filter does not support the relationship "%s" of "%s".', $relationshipName, $parent::class),
            1793028096
        );
    }
}
