<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Bus;

use Hyperf\Collection\Collection;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SwooleTW\Hyperf\Bus\Batch;
use SwooleTW\Hyperf\Bus\Batchable;
use SwooleTW\Hyperf\Bus\Contracts\BatchRepository;
use SwooleTW\Hyperf\Bus\PendingBatch;

/**
 * @internal
 * @coversNothing
 */
class BusPendingBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testPendingBatchMayBeConfiguredAndDispatched()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->once();

        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $pendingBatch = $pendingBatch->before(function () {
        })->progress(function () {
        })->then(function () {
        })->catch(function () {
        })->allowFailures()->onConnection('test-connection')->onQueue('test-queue')->withOption('extra-option', 123);

        $this->assertSame('test-connection', $pendingBatch->connection());
        $this->assertSame('test-queue', $pendingBatch->queue());
        $this->assertCount(1, $pendingBatch->beforeCallbacks());
        $this->assertCount(1, $pendingBatch->progressCallbacks());
        $this->assertCount(1, $pendingBatch->thenCallbacks());
        $this->assertCount(1, $pendingBatch->catchCallbacks());
        $this->assertArrayHasKey('extra-option', $pendingBatch->options);
        $this->assertSame(123, $pendingBatch->options['extra-option']);

        $repository = m::mock(BatchRepository::class);
        $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = m::mock(Batch::class));
        $batch->shouldReceive('add')->once()->with(m::type(Collection::class))->andReturn($batch = m::mock(Batch::class));

        $container->set(BatchRepository::class, $repository);

        $pendingBatch->dispatch();
    }

    public function testBatchIsDeletedFromStorageIfExceptionThrownDuringBatching()
    {
        $this->expectException(RuntimeException::class);

        $container = $this->getContainer();

        $job = new class {};

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $repository = m::mock(BatchRepository::class);

        $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = m::mock(Batch::class));

        $batch->id = 'test-id';

        $batch->shouldReceive('add')->once()->andReturnUsing(function () {
            throw new RuntimeException('Failed to add jobs...');
        });

        $repository->shouldReceive('delete')->once()->with('test-id');

        $container->set(BatchRepository::class, $repository);

        $pendingBatch->dispatch();
    }

    public function testBatchIsDispatchedWhenDispatchifIsTrue()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->once();
        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $repository = m::mock(BatchRepository::class);
        $repository->shouldReceive('store')->once()->andReturn($batch = m::mock(Batch::class));
        $batch->shouldReceive('add')->once()->andReturn($batch = m::mock(Batch::class));

        $container->set(BatchRepository::class, $repository);

        $result = $pendingBatch->dispatchIf(true);

        $this->assertInstanceOf(Batch::class, $result);
    }

    public function testBatchIsNotDispatchedWhenDispatchifIsFalse()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldNotReceive('dispatch');
        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $repository = m::mock(BatchRepository::class);
        $container->set(BatchRepository::class, $repository);

        $result = $pendingBatch->dispatchIf(false);

        $this->assertNull($result);
    }

    public function testBatchIsDispatchedWhenDispatchUnlessIsFalse()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->once();
        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $repository = m::mock(BatchRepository::class);
        $repository->shouldReceive('store')->once()->andReturn($batch = m::mock(Batch::class));
        $batch->shouldReceive('add')->once()->andReturn($batch = m::mock(Batch::class));

        $container->set(BatchRepository::class, $repository);

        $result = $pendingBatch->dispatchUnless(false);

        $this->assertInstanceOf(Batch::class, $result);
    }

    public function testBatchIsNotDispatchedWhenDispatchUnlessIsTrue()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldNotReceive('dispatch');
        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $repository = m::mock(BatchRepository::class);
        $container->set(BatchRepository::class, $repository);

        $result = $pendingBatch->dispatchUnless(true);

        $this->assertNull($result);
    }

    public function testBatchBeforeEventIsCalled()
    {
        $container = $this->getContainer();

        $eventDispatcher = m::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch')->once();

        $container->set(EventDispatcherInterface::class, $eventDispatcher);

        $job = new class {
            use Batchable;
        };

        $beforeCalled = false;

        $pendingBatch = new PendingBatch($container, new Collection([$job]));

        $pendingBatch = $pendingBatch->before(function () use (&$beforeCalled) {
            $beforeCalled = true;
        })->onConnection('test-connection')->onQueue('test-queue');

        $repository = m::mock(BatchRepository::class);
        $repository->shouldReceive('store')->once()->with($pendingBatch)->andReturn($batch = m::mock(Batch::class));
        $batch->shouldReceive('add')->once()->with(m::type(Collection::class))->andReturn($batch = m::mock(Batch::class));

        $container->set(BatchRepository::class, $repository);

        $pendingBatch->dispatch();

        $this->assertTrue($beforeCalled);
    }

    protected function getContainer(array $bindings = []): Container
    {
        return new Container(
            new DefinitionSource($bindings)
        );
    }
}
