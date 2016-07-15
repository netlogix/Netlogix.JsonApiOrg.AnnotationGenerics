<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\Common\Collections\Collection;
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
		return in_array($offset, $settings['attributesToBeApiExposed']) || array_key_exists($offset,
			$settings['relationshipsToBeApiExposed']);
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 * @throws PropertyNotAccessibleException
	 */
	public function offsetGet($offset)
	{
		if (!$this->offsetExists($offset)) {
			throw new PropertyNotAccessibleException('The property "' . $offset . '" on the subject was not accessible.',
				1463495379);
		}
		$getterMethodName = 'get' . ucfirst($offset);
		if (is_callable([$this, $getterMethodName])) {
			return $this->{$getterMethodName}();
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
		$oldValue = $this->offsetGet($offset);
		if ($oldValue === $value) {
			return;
		}
		if (is_object($oldValue) && $oldValue instanceof \DateTime && is_string($value)) {
			if ($oldValue->format('U') === (new \DateTime($value))->format('U')) {
				return;
			}
		}
		if (is_object($oldValue) && $oldValue instanceof Collection && is_object($value) && $value instanceof Collection) {
			if ($oldValue->toArray() == $value->toArray()) {
				return;
			}
		}
		throw new \InvalidArgumentException('The property "' . $offset . '"" is not to be set.', 1463495464);
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
		throw new \InvalidArgumentException('The property "' . $offset . '"" is not to be unset.', 1463495475);
	}

	/**
	 * @param $offset
	 * @return mixed
	 * @throws PropertyNotAccessibleException
	 */
	public function __get($offset)
	{
		try {
			return $this->offsetGet($offset);
		} catch (PropertyNotAccessibleException $e) {
			if (is_callable('parent::__get')) {
				return parent::__get($offset);
			}
			throw $e;
		}
	}
}