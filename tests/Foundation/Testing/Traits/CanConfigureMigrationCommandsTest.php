<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Foundation\Testing\Traits;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SwooleTW\Hyperf\Foundation\Testing\Traits\CanConfigureMigrationCommands;
use SwooleTW\Hyperf\Tests\Foundation\Concerns\HasMockedApplication;

/**
 * @internal
 * @coversNothing
 */
class CanConfigureMigrationCommandsTest extends TestCase
{
    protected $traitObject;

    protected function setUp(): void
    {
        $this->traitObject = new CanConfigureMigrationCommandsTestMockClass();
    }

    private function __reflectAndSetupAccessibleForProtectedTraitMethod($methodName)
    {
        return new ReflectionMethod(
            get_class($this->traitObject),
            $methodName
        );
    }

    public function testMigrateFreshUsingDefault(): void
    {
        $migrateFreshUsingReflection = $this->__reflectAndSetupAccessibleForProtectedTraitMethod('migrateFreshUsing');

        $expected = [
            '--drop-views' => false,
            '--seed' => false,
            '--database' => 'default',
        ];

        $this->assertEquals($expected, $migrateFreshUsingReflection->invoke($this->traitObject));
    }

    public function testMigrateFreshUsingWithPropertySets(): void
    {
        $migrateFreshUsingReflection = $this->__reflectAndSetupAccessibleForProtectedTraitMethod('migrateFreshUsing');

        $expected = [
            '--drop-views' => true,
            '--seed' => false,
            '--database' => 'default',
        ];

        $this->traitObject->dropViews = true;

        $this->assertEquals($expected, $migrateFreshUsingReflection->invoke($this->traitObject));

        $expected = [
            '--drop-views' => false,
            '--seed' => false,
            '--database' => 'default',
        ];

        $this->traitObject->dropViews = false;

        $this->assertEquals($expected, $migrateFreshUsingReflection->invoke($this->traitObject));
    }

    protected function getConfig(array $config = []): Config
    {
        return new Config(array_merge([
            'database' => [
                'default' => 'default',
            ],
        ], $config));
    }
}

class CanConfigureMigrationCommandsTestMockClass
{
    use CanConfigureMigrationCommands;
    use HasMockedApplication;

    public bool $dropViews = false;

    public $app;

    public function __construct()
    {
        $this->app = $this->getApplication([
            ConfigInterface::class => fn () => $this->getConfig(),
        ]);
    }

    protected function getConfig(array $config = []): Config
    {
        return new Config(array_merge([
            'database' => [
                'default' => 'default',
            ],
        ], $config));
    }
}
