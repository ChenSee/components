<?php

declare(strict_types=1);

namespace Hypervel\Redis;

use Closure;
use Hyperf\Redis\Redis as HyperfRedis;
use Hyperf\Redis\RedisProxy;
use Hypervel\Context\ApplicationContext;

class Redis extends HyperfRedis
{
    /**
     * Get a Redis connection by name.
     */
    public function connection(string $name = 'default'): RedisProxy
    {
        return ApplicationContext::getContainer()
            ->get(RedisFactory::class)
            ->get($name);
    }

    /**
     * Subscribe to a set of given channels for messages.
     */
    public function subscribe(array|string $channels, Closure $callback): void
    {
        $this->connection()
            ->subscribe($channels, $callback);
    }

    /**
     * Subscribe to a set of given channels with wildcards.
     */
    public function psubscribe(array|string $channels, Closure $callback): void
    {
        $this->connection()
            ->psubscribe($channels, $callback);
    }
}
