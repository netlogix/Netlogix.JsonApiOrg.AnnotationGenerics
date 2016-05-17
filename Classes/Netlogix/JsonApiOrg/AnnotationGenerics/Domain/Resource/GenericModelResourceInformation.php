<?php
namespace Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Resource;

/*
 * This file is part of the Netlogix.JsonApiOrg.AnnotationGenerics package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Netlogix\JsonApiOrg\AnnotationGenerics\Configuration\ConfigurationProvider;
use Netlogix\JsonApiOrg\AnnotationGenerics\Domain\Model\GenericModelInterface;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformation;
use Netlogix\JsonApiOrg\Resource\Information\ResourceInformationInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Http\Uri;
use TYPO3\Flow\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class GenericModelResourceInformation extends ResourceInformation implements ResourceInformationInterface
{
    const DOMAIN_MODEL_PATTERN = '%(?<vendor>[^\\\\]+)\\\\(?<package>.*)\\\\(?<subPackage>[^\\\\]+)\\\\Domain\\\\(Model|Command)\\\\(?<resourceType>[^\\\\]+)%i';

    /**
     * @var int
     */
    protected $priority = 10;

    /**
     * @var ConfigurationProvider
     * @Flow\Inject
     */
    protected $configurationProvider;

    /**
     * @var
     */
    protected $resourceClassName = GenericModelResource::class;

    /**
     * @var
     */
    protected $payloadClassName = GenericModelInterface::class;

    /**
     * @param mixed $resource
     * @return array
     */
    public function getResourceControllerArguments($resource)
    {
        $settings = $this->configurationProvider->getSettingsForType($resource);
        $result = [
            $settings['argumentName'] => $resource,
        ];
        $type = TypeHandling::getTypeForValue($resource);
        if (preg_match(self::DOMAIN_MODEL_PATTERN, $type, $matches)) {
            $result['subPackage'] = [];
            foreach (explode('\\', $matches['subPackage']) as $subPackage) {
                $result['subPackage'][] = lcfirst($subPackage);
            }
            $result['subPackage'] = join('.', $result['subPackage']);
            $result['resourceType'] = lcfirst($matches['resourceType']);
        }
        return $result;
    }

    /**
     * @return array
     */
    protected function getRelationshipControllerArguments($resource, $relationshipName)
    {
        $result = $this->getResourceControllerArguments($resource);
        $result['relationshipName'] = $relationshipName;
        return $result;
    }

    /**
     * @param mixed $resource
     * @param string $controllerActionName
     * @param array $controllerArguments
     * @return Uri
     */
    protected function getPublicUri($resource, $controllerActionName, array $controllerArguments = array())
    {
        $settings = $this->configurationProvider->getSettingsForType($resource);

        $uriBuilder = $this->resourceMapper->getControllerContext()->getUriBuilder();

        $uriBuilder
            ->reset()
            ->setFormat($this->format)
            ->setCreateAbsoluteUri(true);

        $controllerArguments = array_merge($this->getResourceControllerArguments($resource), $controllerArguments);

        $uri = $uriBuilder->uriFor(
            $controllerActionName,
            $controllerArguments,
            $settings['controllerName'],
            $settings['packageKey'],
            $settings['subPackageKey']
        );

        return new Uri($uri);
    }
}