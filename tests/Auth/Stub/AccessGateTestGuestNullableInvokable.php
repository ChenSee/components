<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Auth\Stub;

use SwooleTW\Hyperf\Auth\Contracts\Authenticatable;

class AccessGateTestGuestNullableInvokable
{
    public static $calledMethod;

    public function __invoke(?Authenticatable $user)
    {
        static::$calledMethod = 'Nullable __invoke was called';

        return true;
    }
}
