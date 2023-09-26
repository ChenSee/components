<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Auth\Stub;

class AccessGateTestPolicyWithAllPermissions
{
    public function edit($user, AccessGateTestDummy $dummy)
    {
        return true;
    }

    public function update($user, AccessGateTestDummy $dummy)
    {
        return true;
    }
}
