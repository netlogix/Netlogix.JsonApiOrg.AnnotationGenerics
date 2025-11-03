<?php

declare(strict_types=1);

namespace Netlogix\JsonApiOrg\AnnotationGenerics;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Monitor\FileMonitor;
use Neos\Flow\ObjectManagement\CompileTimeObjectManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\ObjectManagement\Proxy\Compiler;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Flow\SignalSlot\Dispatcher;
use Netlogix\JsonApiOrg\AnnotationGenerics\Resource\Information\ExposableTypeMap;

final class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap): void
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        assert($dispatcher instanceof Dispatcher);

        /**
         * @see Compiler::compiledClasses()
         * @see FileMonitor::emitFilesHaveChanged()
         * @see ExposableTypeMap::flush()
         */
        $dispatcher->connect(
            signalClassName: FileMonitor::class,
            signalName: 'filesHaveChanged',
            slotClassNameOrObject: fn () => static::flushExposableTypeMap($bootstrap->getObjectManager())
        );
    }

    private function flushExposableTypeMap(ObjectManagerInterface $objectManager): void
    {
        if ($objectManager instanceof CompileTimeObjectManager) {
            return;
        }
        $cache = $objectManager->get(ExposableTypeMap::CACHE);
        assert($cache instanceof VariableFrontend);
        $cache->flush();
    }
}
