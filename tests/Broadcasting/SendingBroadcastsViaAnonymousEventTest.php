<?php

declare(strict_types=1);

namespace LaravelHyperf\Tests\Broadcasting;

use Hyperf\HttpServer\Contract\RequestInterface;
use LaravelHyperf\Broadcasting\AnonymousEvent;
use LaravelHyperf\Broadcasting\BroadcastManager;
use LaravelHyperf\Broadcasting\Contracts\Factory as BroadcastingFactoryContract;
use LaravelHyperf\Broadcasting\PresenceChannel;
use LaravelHyperf\Broadcasting\PrivateChannel;
use LaravelHyperf\Container\DefinitionSource;
use LaravelHyperf\Context\ApplicationContext;
use LaravelHyperf\Foundation\Application;
use LaravelHyperf\Support\Facades\Broadcast;
use LaravelHyperf\Support\Facades\Event;
use LaravelHyperf\Support\Facades\Facade;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

/**
 * @internal
 * @coversNothing
 */
class SendingBroadcastsViaAnonymousEventTest extends TestCase
{
    protected Application $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Application(
            new DefinitionSource([
                EventDispatcherInterface::class => fn () => m::mock(EventDispatcherInterface::class),
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

    public function testBroadcastIsSent()
    {
        Event::fake();

        Broadcast::on('test-channel')
            ->with(['some' => 'data'])
            ->as('test-event')
            ->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return (new ReflectionClass($event))->getProperty('connection')->getValue($event) === null
                && $event->broadcastOn() === ['test-channel']
                && $event->broadcastAs() === 'test-event'
                && $event->broadcastWith() === ['some' => 'data'];
        });
    }

    public function testBroadcastIsSentNow()
    {
        Event::fake();

        Broadcast::on('test-channel')
            ->with(['some' => 'data'])
            ->as('test-event')
            ->sendNow();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return (new ReflectionClass($event))->getProperty('connection')->getValue($event) === null
                && $event->shouldBroadcastNow();
        });
    }

    public function testDefaultNameIsSet()
    {
        Event::fake();

        Broadcast::on('test-channel')
            ->with(['some' => 'data'])
            ->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return $event->broadcastAs() === 'AnonymousEvent';
        });
    }

    public function testDefaultPayloadIsSet()
    {
        Event::fake();

        Broadcast::on('test-channel')->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return $event->broadcastWith() === [];
        });
    }

    public function testSendToMultipleChannels()
    {
        Event::fake();

        Broadcast::on([
            'test-channel',
            new PrivateChannel('test-channel'),
            'presence-test-channel',
        ])->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            [$one, $two, $three] = $event->broadcastOn();

            return $one === 'test-channel'
                && $two instanceof PrivateChannel
                && $two->name === 'private-test-channel'
                && $three === 'presence-test-channel';
        });
    }

    public function testSendViaANonDefaultConnection()
    {
        Event::fake();

        Broadcast::on('test-channel')
            ->via('pusher')
            ->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return (new ReflectionClass($event))->getProperty('connection')->getValue($event) === 'pusher';
        });
    }

    public function testSendToOthersOnly()
    {
        Event::fake();

        $request = m::mock(RequestInterface::class);
        $request->shouldReceive('header')->with('X-Socket-ID')->andReturn('12345');
        $this->container->set(RequestInterface::class, $request);

        Broadcast::on('test-channel')->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return $event->socket === null;
        });

        Broadcast::on('test-channel')
            ->toOthers()
            ->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            return $event->socket = '12345';
        });
    }

    public function testSendToPrivateChannel()
    {
        Event::fake();

        Broadcast::private('test-channel')->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            $channel = $event->broadcastOn()[0];

            return $channel instanceof PrivateChannel && $channel->name === 'private-test-channel';
        });
    }

    public function testSendToPresenceChannel()
    {
        Event::fake();

        Broadcast::presence('test-channel')->send();

        Event::assertDispatched(AnonymousEvent::class, function ($event) {
            $channel = $event->broadcastOn()[0];

            return $channel instanceof PresenceChannel && $channel->name === 'presence-test-channel';
        });
    }
}
