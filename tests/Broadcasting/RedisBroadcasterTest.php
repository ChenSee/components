<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Broadcasting;

use Hyperf\HttpServer\Request;
use Hyperf\Redis\RedisFactory;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SwooleTW\Hyperf\Auth\AuthManager;
use SwooleTW\Hyperf\Broadcasting\Broadcasters\RedisBroadcaster;
use SwooleTW\Hyperf\HttpMessage\Exceptions\AccessDeniedHttpException;
use SwooleTW\Hyperf\Support\Facades\Facade;
use SwooleTW\Hyperf\Tests\Foundation\Concerns\HasMockedApplication;

/**
 * @internal
 * @coversNothing
 */
class RedisBroadcasterTest extends TestCase
{
    use HasMockedApplication;

    protected RedisBroadcaster $broadcaster;

    protected ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = m::mock(ContainerInterface::class);
        $factory = m::mock(RedisFactory::class);
        $this->broadcaster = m::mock(RedisBroadcaster::class, [$this->container, $factory])->makePartial();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();

        Facade::clearResolvedInstances();
    }

    public function testAuthCallValidAuthenticationResponseWithPrivateChannelWhenCallbackReturnTrue()
    {
        $this->broadcaster->channel('test', function () {
            return true;
        });

        $this->broadcaster->shouldReceive('validAuthenticationResponse')->once();

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('private-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPrivateChannelWhenCallbackReturnFalse()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return false;
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('private-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPrivateChannelWhenRequestUserNotFound()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return true;
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithoutUserForChannel('private-test')
        );
    }

    public function testAuthCallValidAuthenticationResponseWithPresenceChannelWhenCallbackReturnAnArray()
    {
        $returnData = [1, 2, 3, 4];
        $this->broadcaster->channel('test', function () use ($returnData) {
            return $returnData;
        });

        $this->broadcaster->shouldReceive('validAuthenticationResponse')
            ->once();

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('presence-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPresenceChannelWhenCallbackReturnNull()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithUserForChannel('presence-test')
        );
    }

    public function testAuthThrowAccessDeniedHttpExceptionWithPresenceChannelWhenRequestUserNotFound()
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->broadcaster->channel('test', function () {
            return [1, 2, 3, 4];
        });

        $this->broadcaster->auth(
            $this->getMockRequestWithoutUserForChannel('presence-test')
        );
    }

    public function testValidAuthenticationResponseWithPrivateChannel()
    {
        $request = $this->getMockRequestWithUserForChannel('private-test');

        $this->assertEquals(
            json_encode(true),
            $this->broadcaster->validAuthenticationResponse($request, true)
        );
    }

    public function testValidAuthenticationResponseWithPresenceChannel()
    {
        $request = $this->getMockRequestWithUserForChannel('presence-test');

        $this->assertEquals(
            json_encode([
                'channel_data' => [
                    'user_id' => 42,
                    'user_info' => [
                        'a' => 'b',
                        'c' => 'd',
                    ],
                ],
            ]),
            $this->broadcaster->validAuthenticationResponse($request, [
                'a' => 'b',
                'c' => 'd',
            ])
        );
    }

    protected function getMockRequestWithUserForChannel(string $channel): Request
    {
        $request = m::mock(Request::class);
        $request->shouldReceive('input')->with('channel_name')->andReturn($channel);

        $user = m::mock('User');
        $user->shouldReceive('getAuthIdentifier')->andReturn(42);

        $authManager = m::mock(AuthManager::class);
        $authManager->shouldReceive('user')->andReturn($user);

        $this->container->shouldReceive('get')
            ->with(AuthManager::class)
            ->andReturn($authManager);

        return $request;
    }

    protected function getMockRequestWithoutUserForChannel(string $channel): Request
    {
        $request = m::mock(Request::class);
        $request->shouldReceive('input')->with('channel_name')->andReturn($channel);

        $authManager = m::mock(AuthManager::class);
        $authManager->shouldReceive('user')->andReturn(null);

        $this->container->shouldReceive('get')
            ->with(AuthManager::class)
            ->andReturn($authManager);

        return $request;
    }
}
