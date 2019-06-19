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

use Doctrine\Common\Collections\Selectable;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Persistence\RepositoryInterface;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\Arguments\Page;

interface GenericModelRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Selectable
     */
    public function getSelectable(): Selectable;
}