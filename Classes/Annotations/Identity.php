<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Annotations;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * A model class which should be available as api resource needs this
 * annotation.
 *
 * @Annotation
 * @Target({"METHOD", "PROPERTY"})
 */
#[\Attribute(\Attribute::TARGET_METHOD|\Attribute::TARGET_PROPERTY)]
final class Identity
{
}
