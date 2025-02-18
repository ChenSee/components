<?php

declare(strict_types=1);

namespace LaravelHyperf\Tests\Broadcasting;

use Hyperf\Contract\ConfigInterface;
use InvalidArgumentException;
use LaravelHyperf\Broadcasting\BroadcastEvent;
use LaravelHyperf\Broadcasting\BroadcastManager;
use LaravelHyperf\Broadcasting\Channel;
use LaravelHyperf\Broadcasting\Contracts\Factory as BroadcastingFactoryContract;
use LaravelHyperf\Broadcasting\Contracts\ShouldBeUnique;
use LaravelHyperf\Broadcasting\Contracts\ShouldBroadcast;
use LaravelHyperf\Broadcasting\Contracts\ShouldBroadcastNow;
use LaravelHyperf\Broadcasting\UniqueBroadcastEvent;
use LaravelHyperf\Bus\Contracts\Dispatcher as BusDispatcherContract;
use LaravelHyperf\Bus\Contracts\QueueingDispatcher;
use LaravelHyperf\Cache\Contracts\Factory as Cache;
use LaravelHyperf\Container\DefinitionSource;
use LaravelHyperf\Context\ApplicationContext;
use LaravelHyperf\Foundation\Application;
use LaravelHyperf\Queue\Contracts\Factory as QueueFactoryContract;
use LaravelHyperf\Support\Facades\Broadcast;
use LaravelHyperf\Support\Facades\Bus;
use LaravelHyperf\Support\Facades\Facade;
use LaravelHyperf\Support\Facades\Queue;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @coversNothing
 */
class BroadcastManagerTest extends TestCase
{
    protected Application $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Application(
            new DefinitionSource([
                BusDispatcherContract::class => fn () => m::mock(QueueingDispatcher::class),
                ConfigInterface::class => fn () => m::mock(ConfigInterface::class),
                QueueFactoryContract::class => fn () => m::mock(QueueFactoryContract::class),
                BroadcastingFactoryContract::class => fn ($container) => new BroadcastManager($container),
            ]),
            'bath_path',
        );

        ApplicationContext::setContainer($this->container);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();

        Facade::clearResolvedInstances();
    }

    public function testEventCanBeBroadcastNow()
    {
        Bus::fake();
        Queue::fake();

        Broadcast::queue(new TestEventNow());

        Bus::assertDispatched(BroadcastEvent::class);
        Queue::assertNotPushed(BroadcastEvent::class);
    }

    public function testEventsCanBeBroadcast()
    {
        Bus::fake();
        Queue::fake();

        Broadcast::queue(new TestEvent());

        Bus::assertNotDispatched(BroadcastEvent::class);
        Queue::assertPushed(BroadcastEvent::class);
    }

    public function testUniqueEventsCanBeBroadcast()
    {
        Bus::fake();
        Queue::fake();

        $lockKey = 'laravel_unique_job:' . UniqueBroadcastEvent::class . ':' . TestEventUnique::class;
        $cache = m::mock(Cache::class);
        $cache->shouldReceive('lock')->with($lockKey, 0)->andReturnSelf();
        $cache->shouldReceive('get')->andReturn(true);
        $this->container->bind(Cache::class, fn () => $cache);

        Broadcast::queue(new TestEventUnique());

        Bus::assertNotDispatched(UniqueBroadcastEvent::class);
        Queue::assertPushed(UniqueBroadcastEvent::class);
    }

    public function testThrowExceptionWhenUnknownStoreIsUsed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Broadcast connection [alien_connection] is not defined.');

        $config = m::mock(ContainerInterface::class);
        $config->shouldReceive('get')->with('broadcasting.connections.alien_connection')->andReturn(null);

        $app = m::mock(ContainerInterface::class);
        $app->shouldReceive('get')->with(ConfigInterface::class)->andReturn($config);

        $broadcastManager = new BroadcastManager($app);

        $broadcastManager->connection('alien_connection');
    }
}

class TestEvent implements ShouldBroadcast
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel[]|string[]
     */
    public function broadcastOn(): array
    {
        return [];
    }
}

class TestEventNow implements ShouldBroadcastNow
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel[]|string[]
     */
    public function broadcastOn(): array
    {
        return [];
    }
}

class TestEventUnique implements ShouldBroadcast, ShouldBeUnique
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel[]|string[]
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
