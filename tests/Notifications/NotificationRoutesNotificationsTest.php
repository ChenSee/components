<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Notifications;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use InvalidArgumentException;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleTW\Hyperf\Notifications\AnonymousNotifiable;
use SwooleTW\Hyperf\Notifications\Contracts\Dispatcher;
use SwooleTW\Hyperf\Notifications\RoutesNotifications;

/**
 * @internal
 * @coversNothing
 */
class NotificationRoutesNotificationsTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testNotificationCanBeDispatched()
    {
        $container = $this->getContainer();
        $factory = m::mock(Dispatcher::class);
        $container->set(Dispatcher::class, $factory);
        $notifiable = new RoutesNotificationsTestInstance();
        $instance = new stdClass();
        $factory->shouldReceive('send')->with($notifiable, $instance);

        $notifiable->notify($instance);
    }

    public function testNotificationCanBeSentNow()
    {
        $container = $this->getContainer();
        $factory = m::mock(Dispatcher::class);
        $container->set(Dispatcher::class, $factory);
        $notifiable = new RoutesNotificationsTestInstance();
        $instance = new stdClass();
        $factory->shouldReceive('sendNow')->with($notifiable, $instance, null);

        $notifiable->notifyNow($instance);
    }

    public function testNotificationOptionRouting()
    {
        $instance = new RoutesNotificationsTestInstance();
        $this->assertSame('bar', $instance->routeNotificationFor('foo'));
        $this->assertSame('taylor@laravel.com', $instance->routeNotificationFor('mail'));
    }

    public function testOnDemandNotificationsCannotUseDatabaseChannel()
    {
        $this->expectExceptionObject(
            new InvalidArgumentException('The database channel does not support on-demand notifications.')
        );

        (new AnonymousNotifiable())->route('database', 'foo');
    }

    protected function getContainer(): Container
    {
        $container = new Container(
            new DefinitionSource([])
        );

        ApplicationContext::setContainer($container);

        return $container;
    }
}

class RoutesNotificationsTestInstance
{
    use RoutesNotifications;

    protected $email = 'taylor@laravel.com';

    public function routeNotificationForFoo()
    {
        return 'bar';
    }
}
