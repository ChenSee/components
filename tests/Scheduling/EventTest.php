<?php

declare(strict_types=1);

namespace LaravelHyperf\Tests\Scheduling;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Stringable\Str;
use Hyperf\Support\Filesystem\Filesystem;
use LaravelHyperf\Container\Contracts\Container;
use LaravelHyperf\Foundation\Console\Contracts\Kernel as KernelContract;
use LaravelHyperf\Scheduling\Contracts\EventMutex;
use LaravelHyperf\Scheduling\Event;
use LaravelHyperf\Tests\Foundation\Concerns\HasMockedApplication;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 * @coversNothing
 */
class EventTest extends TestCase
{
    use HasMockedApplication;

    protected ?Container $container = null;

    protected function setUp(): void
    {
        parent::setUp();

        ApplicationContext::setContainer(
            $this->container = $this->getApplication()
        );
    }

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function testSendOutputToWithIsNotFile()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -v');

        $event->sendOutputTo($output = 'test.log');
        $filesystem = m::mock(Filesystem::class);
        $filesystem->shouldReceive('isFile')
            ->once()
            ->with($output)
            ->andReturn(false);

        $this->container->set(Filesystem::class, $filesystem);
        $event->writeOutput($this->container);
    }

    public function testSendOutputTo()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -v');

        $event->sendOutputTo($output = 'test.log');

        $kernel = m::mock(KernelContract::class);
        $kernel->shouldReceive('output')
            ->once()
            ->andReturn($result = 'PHP 8.3.17 (cli) (built: Feb 11 2025 22:03:03) (NTS)');

        $filesystem = m::mock(Filesystem::class);
        $filesystem->shouldReceive('isFile')
            ->once()
            ->with($output)
            ->andReturn(true);
        $filesystem->shouldReceive('put')
            ->once()
            ->with($output, $result);

        $this->container->set(KernelContract::class, $kernel);
        $this->container->set(Filesystem::class, $filesystem);

        $event->writeOutput($this->container);
    }

    public function testSendOutputToWithSystemProcess()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -v');
        $event->isSystem = true;

        $event->sendOutputTo($output = 'test.log');

        $process = m::mock(Process::class);
        $process->shouldReceive('getOutput')
            ->once()
            ->andReturn($result = 'PHP 8.3.17 (cli) (built: Feb 11 2025 22:03:03) (NTS)');
        Context::set($key = "scheduling_process:{$event->mutexName()}", $process);

        $filesystem = m::mock(Filesystem::class);
        $filesystem->shouldReceive('put')
            ->once()
            ->with($output, $result);

        $this->container->set(Filesystem::class, $filesystem);

        $event->writeOutput($this->container);

        Context::destroy($key);
    }

    public function testAppendOutput()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -v');

        $event->appendOutputTo($output = 'test.log');

        $kernel = m::mock(KernelContract::class);
        $kernel->shouldReceive('output')
            ->once()
            ->andReturn($result = 'PHP 8.3.17 (cli) (built: Feb 11 2025 22:03:03) (NTS)');

        $filesystem = m::mock(Filesystem::class);
        $filesystem->shouldReceive('isFile')
            ->once()
            ->with($output)
            ->andReturn(true);
        $filesystem->shouldReceive('append')
            ->once()
            ->with($output, $result);

        $this->container->set(KernelContract::class, $kernel);
        $this->container->set(Filesystem::class, $filesystem);

        $event->writeOutput($this->container);
    }

    public function testNextRunDate()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -i');
        $event->dailyAt('10:15');

        $this->assertSame('10:15:00', $event->nextRunDate()->toTimeString());
    }

    public function testCustomMutexName()
    {
        $event = new Event(m::mock(EventMutex::class), 'php -i');
        $event->description('Fancy command description');

        $this->assertSame('framework' . DIRECTORY_SEPARATOR . 'schedule-eeb46c93d45e928d62aaf684d727e213b7094822', $event->mutexName());

        $event->createMutexNameUsing(function (Event $event) {
            return Str::slug($event->description);
        });

        $this->assertSame('fancy-command-description', $event->mutexName());
    }
}
