<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

interface JsonApiIdentifier
{
    public function toString(): string;
}