<?php

declare(strict_types=1);

namespace LaravelHyperf\Tests\Event\Hyperf\Event;

use Hyperf\Event\Stoppable;
use Psr\EventDispatcher\StoppableEventInterface;

class Alpha implements StoppableEventInterface
{
    use Stoppable;
}
