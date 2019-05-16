<?php

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

/*
 * This interface doesn't provide any methods. It's sole purpose is to prevent
 * write-only model objects to be shown and searched by the showAction() and
 * showRelationshipAction().
 */

interface ReadModelInterface extends GenericModelInterface
{

}