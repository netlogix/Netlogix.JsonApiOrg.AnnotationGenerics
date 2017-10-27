<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;

interface WriteModelInterface extends GenericModelInterface
{
    public function execute();

}