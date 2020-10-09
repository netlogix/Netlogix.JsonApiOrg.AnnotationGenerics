<?php
declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics\Cache;

use Neos\Flow\Annotations as Flow;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\ExposedValueObjectInterface;

/**
 * @Flow\Scope("singleton")
 */
class ExposedValueObjectCache
{

    /**
     * @var array
     */
    private $entries = [];

    /**
     * @param string $entryIdentifier
     * @param ExposedValueObjectInterface $data
     */
    public function set(string $entryIdentifier, ExposedValueObjectInterface $data)
    {
        $className = get_class($data);
        if (!array_key_exists($className, $this->entries)) {
            $this->entries[$className] = [];
        }
        $this->entries[$className][$entryIdentifier] = $data;
    }

    /**
     * @param string $entryIdentifier
     * @param string $className
     * @return ExposedValueObjectInterface|null
     */
    public function get(string $entryIdentifier, string $className)
    {
        return $this->entries[$className][$entryIdentifier] ?? null;
    }

    /**
     * @param string $entryIdentifier
     * @param string $className
     * @return bool
     */
    public function has(string $entryIdentifier, string $className)
    {
        return isset($this->entries[$className][$entryIdentifier]);
    }
}