<?php

declare(strict_types=1);

namespace LaravelHyperf\Tests\Core;

use Hyperf\Database\Model\Events\Created;
use Hyperf\Database\Model\Events\Updated;
use Hyperf\Database\Model\Model;
use InvalidArgumentException;
use LaravelHyperf\Database\Eloquent\ModelListener;
use LaravelHyperf\Database\Eloquent\ObserverManager;
use LaravelHyperf\Tests\TestCase;
use Mockery as m;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @coversNothing
 */
class ObserverManagerTest extends TestCase
{
    public function testRegisterWithInvalidObserver()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to find observer: Observer');

        $this->getObserverManager()
            ->register(ObserverUser::class, 'Observer');
    }

    public function testRegister()
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->with(UserObserver::class)
            ->once()
            ->andReturn($userObserver = new UserObserver());

        $listener = m::mock(ModelListener::class);
        $listener->shouldReceive('getModelEvents')
            ->once()
            ->andReturn([
                'created' => Created::class,
                'updated' => Updated::class,
            ]);
        $listener->shouldReceive('register')
            ->once()
            ->with(ObserverUser::class, 'created', m::type('callable'));

        $manager = $this->getObserverManager($container, $listener);
        $manager->register(ObserverUser::class, UserObserver::class);

        $this->assertSame(
            [$userObserver],
            $manager->getObservers(ObserverUser::class)
        );

        $this->assertSame(
            [],
            $manager->getObservers(ObserverUser::class, 'updated')
        );
    }

    protected function getObserverManager(?ContainerInterface $container = null, ?ModelListener $listener = null): ObserverManager
    {
        return new ObserverManager(
            $container ?? m::mock(ContainerInterface::class),
            $listener ?? m::mock(ModelListener::class)
        );
    }
}

class ObserverUser extends Model
{
}

class UserObserver
{
    public function created(User $user)
    {
    }
}
