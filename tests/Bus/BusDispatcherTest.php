<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Bus;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SwooleTW\Hyperf\Bus\Dispatcher;
use SwooleTW\Hyperf\Bus\Queueable;
use SwooleTW\Hyperf\Container\Contracts\Container;
use SwooleTW\Hyperf\Queue\Contracts\Queue;
use SwooleTW\Hyperf\Queue\Contracts\ShouldQueue;
use SwooleTW\Hyperf\Queue\InteractsWithQueue;

/**
 * @internal
 * @coversNothing
 */
class BusDispatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testCommandsThatShouldQueueIsQueued()
    {
        $container = m::mock(ContainerInterface::class);
        $dispatcher = new Dispatcher($container, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $dispatcher->dispatch(m::mock(ShouldQueue::class));
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomHandler()
    {
        $container = m::mock(ContainerInterface::class);
        $dispatcher = new Dispatcher($container, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestCustomQueueCommand());
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomQueueAndDelay()
    {
        $container = m::mock(ContainerInterface::class);
        $dispatcher = new Dispatcher($container, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('laterOn')->once()->with('foo', 10, m::type(BusDispatcherTestSpecificQueueAndDelayCommand::class));

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestSpecificQueueAndDelayCommand());
    }

    public function testDispatchNowShouldNeverQueue()
    {
        $container = m::mock(Container::class);
        $container->shouldReceive('call')->once();
        $mock = m::mock(Queue::class);
        $mock->shouldReceive('push')->never();
        $dispatcher = new Dispatcher($container, function () use ($mock) {
            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherBasicCommand());
    }

    public function testDispatcherCanDispatchStandAloneHandler()
    {
        $container = m::mock(Container::class);
        $container->shouldReceive('get')
            ->with(StandAloneHandler::class)
            ->once()
            ->andReturn(new StandAloneHandler());
        $mock = m::mock(Queue::class);
        $dispatcher = new Dispatcher($container, function () use ($mock) {
            return $mock;
        });

        $dispatcher->map([StandAloneCommand::class => StandAloneHandler::class]);

        $response = $dispatcher->dispatch(new StandAloneCommand());

        $this->assertInstanceOf(StandAloneCommand::class, $response);
    }

    public function testOnConnectionOnJobWhenDispatching()
    {
        $container = m::mock(ContainerInterface::class);
        $dispatcher = new Dispatcher($container, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $job = (new ShouldNotBeDispatched())->onConnection('null');

        $dispatcher->dispatch($job);
    }
}

class BusInjectionStub
{
}

class BusDispatcherBasicCommand
{
    public $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function handle(BusInjectionStub $stub)
    {
    }
}

class BusDispatcherTestCustomQueueCommand implements ShouldQueue
{
    public function queue($queue, $command)
    {
        $queue->push($command);
    }
}

class BusDispatcherTestSpecificQueueAndDelayCommand implements ShouldQueue
{
    public $queue = 'foo';

    public $delay = 10;
}

class StandAloneCommand
{
}

class StandAloneHandler
{
    public function handle(StandAloneCommand $command)
    {
        return $command;
    }
}

class ShouldNotBeDispatched implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function handle()
    {
        throw new RuntimeException('This should not be run');
    }
}
