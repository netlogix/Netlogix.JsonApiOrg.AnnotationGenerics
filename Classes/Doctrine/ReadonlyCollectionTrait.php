<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Doctrine;

trait ReadonlyCollectionTrait
{
    final public function clear()
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }

    final public function add($element)
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }

    final public function remove($key)
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }

    final public function removeElement($element)
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }

    final public function offsetSet($offset, $value)
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }

    final public function offsetUnset($offset)
    {
        throw new \LogicException('ReadOnly Collection: ' . __METHOD__ . '() is not allowed');
    }
}