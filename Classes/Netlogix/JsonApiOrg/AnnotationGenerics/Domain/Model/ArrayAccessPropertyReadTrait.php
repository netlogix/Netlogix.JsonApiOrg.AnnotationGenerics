<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3\Flow\Utility\TypeHandling;

trait ArrayAccessPropertyReadTrait
{
    /**
     * @var \Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $settings = $this->configurationProvider->getSettingsForType(TypeHandling::getTypeForValue($this));
        return in_array($offset, $settings['attributesToBeApiExposed']) || array_key_exists($offset, $settings['relationshipsToBeApiExposed']);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws PropertyNotAccessibleException
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new PropertyNotAccessibleException('The property "' . $offset . '" on the subject was not accessible.', 1463495379);
        }
        return $this->{$offset};
    }

    /**
     * @param $offset
     * @param $value
     * @throws PropertyNotAccessibleException
     */
    public function offsetSet($offset, $value)
    {
        if ($this->offsetGet($offset) === $value) {
            return;
        }
        throw new \InvalidArgumentException('The property " . $offset . " is not to be set.', 1463495464);
    }

    /**
     * @param $offset
     * @throws PropertyNotAccessibleException
     */
    public function offsetUnset($offset)
    {
        if (!$this->offsetGet($offset)) {
            return;
        }
        throw new \InvalidArgumentException('The property " . $offset . " is not to be set.', 1463495475);
    }
}