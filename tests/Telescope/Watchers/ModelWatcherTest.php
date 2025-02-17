<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Telescope\Watchers;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Stringable\Str;
use SwooleTW\Hyperf\Database\Eloquent\Model;
use SwooleTW\Hyperf\Telescope\EntryType;
use SwooleTW\Hyperf\Telescope\Telescope;
use SwooleTW\Hyperf\Telescope\Watchers\ModelWatcher;
use SwooleTW\Hyperf\Tests\Telescope\FeatureTestCase;

/**
 * @internal
 * @coversNothing
 */
class ModelWatcherTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->get(ConfigInterface::class)
            ->set('telescope.watchers', [
                ModelWatcher::class => [
                    'enabled' => true,
                    'events' => [
                        \Hyperf\Database\Model\Events\Created::class,
                        \Hyperf\Database\Model\Events\Updated::class,
                        \Hyperf\Database\Model\Events\Retrieved::class,
                    ],
                    'hydrations' => true,
                ],
            ]);

        $this->startTelescope();
    }

    public function testModelWatcherRegistersEntry()
    {
        Telescope::withoutRecording(function () {
            $this->createUsersTable();
        });

        UserEloquent::query()
            ->create([
                'name' => 'Telescope',
                'email' => 'telescope@laravel.com',
                'password' => 1,
            ]);

        $entry = $this->loadTelescopeEntries()->first();

        $this->assertSame(EntryType::MODEL, $entry->type);
        $this->assertSame('created', $entry->content['action']);
        $this->assertSame(UserEloquent::class . ':1', $entry->content['model']);
    }

    public function testModelWatcherCanRestrictEvents()
    {
        Telescope::withoutRecording(function () {
            $this->createUsersTable();
        });

        $user = UserEloquent::query()
            ->create([
                'name' => 'Telescope',
                'email' => 'telescope@laravel.com',
                'password' => 1,
            ]);

        $user->delete();

        $entries = $this->loadTelescopeEntries();
        $entry = $entries->last();

        $this->assertCount(1, $entries);
        $this->assertSame(EntryType::MODEL, $entry->type);
        $this->assertSame('created', $entry->content['action']);
        $this->assertSame(UserEloquent::class . ':1', $entry->content['model']);
    }

    public function testModelWatcherRegistersHydrationEntry()
    {
        Telescope::withoutRecording(function () {
            $this->createUsersTable();
        });

        Telescope::stopRecording();
        $this->createUser();
        $this->createUser();
        $this->createUser();

        Telescope::startRecording();
        UserEloquent::all();
        Telescope::stopRecording();

        $entry = $this->loadTelescopeEntries()->first();

        $this->assertSame(EntryType::MODEL, $entry->type);
        $this->assertSame(3, $entry->content['count']);
        $this->assertSame(UserEloquent::class, $entry->content['model']);
        $this->assertCount(1, $this->loadTelescopeEntries());
    }

    protected function createUser()
    {
        UserEloquent::create([
            'name' => 'Telescope',
            'email' => Str::random(),
            'password' => 1,
        ]);
    }
}

class UserEloquent extends Model
{
    protected ?string $table = 'users';

    protected array $guarded = [];
}
