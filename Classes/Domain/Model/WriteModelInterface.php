<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

/**
 * This interface doesn't provide any methods. It's sole purpose is to prevent
 * read-only model objects to be created by the createAction().
 */
interface WriteModelInterface extends GenericModelInterface
{

}