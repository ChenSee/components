<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\ObjectPool;

use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Mockery;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;
use SwooleTW\Hyperf\Foundation\Testing\Concerns\RunTestsInCoroutine;
use SwooleTW\Hyperf\Tests\ObjectPool\Stub\FooPool;
use SwooleTW\Hyperf\Tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ObjectPoolTest extends TestCase
{
    use RunTestsInCoroutine;

    public function testPoolFlush()
    {
        $container = $this->getContainer();
        $pool = new FooPool($container, []);

        $objects = [];
        for ($i = 0; $i < 3; ++$i) {
            $objects[] = $pool->get();
        }

        foreach ($objects as $object) {
            $pool->release($object);
        }

        $pool->flush();
        $this->assertSame(1, $pool->getObjectNumberInPool());
        $this->assertSame(1, $pool->getCurrentObjectNumber());
    }

    public function testPoolFlushOne()
    {
        $container = $this->getContainer();
        $pool = new FooPool($container, []);

        $objects = [];
        for ($i = 0; $i < 3; ++$i) {
            $objects[] = $pool->get();
        }

        foreach ($objects as $object) {
            $pool->release($object);
        }

        $callbackCount = 0;
        $pool->setDestroyCallback(function () use (&$callbackCount) {
            ++$callbackCount;
        });

        $pool->flushOne(true);
        $this->assertSame(2, $pool->getObjectNumberInPool());
        $this->assertSame(2, $pool->getCurrentObjectNumber());

        $pool->flushOne(true);
        $this->assertSame(1, $pool->getObjectNumberInPool());
        $this->assertSame(1, $pool->getCurrentObjectNumber());

        $pool->flushOne(true);
        $this->assertSame(1, $pool->getObjectNumberInPool());
        $this->assertSame(1, $pool->getCurrentObjectNumber());

        $this->assertSame(2, $callbackCount);
    }

    public function testGetObjectOverWaitTimeout()
    {
        $container = $this->getContainer();
        $pool = new FooPool($container, [
            'min_objects' => 1,
            'max_objects' => 1,
            'wait_timeout' => 0.0001,
        ]);

        Coroutine::create(function () use ($pool) {
            $pool->get();

            $exception = new stdClass();
            try {
                $pool->get();
            } catch (RuntimeException $e) {
                $exception = $e;
            }

            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame('Object pool exhausted. Cannot create new object before wait_timeout.', $exception->getMessage());
        });
    }

    public function testGetStats()
    {
        $container = $this->getContainer();
        $pool = new FooPool($container, []);

        $pool->get();
        $pool->get();

        $pool->release($pool->get());

        $this->assertSame([
            'current_objects' => 3,
            'objects_in_pool' => 1,
        ], $pool->getStats());
    }

    protected function getContainer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        return $container;
    }
}
