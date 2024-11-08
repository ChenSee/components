<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Mail;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\ViewEngine\Contract\FactoryInterface as ViewInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SwooleTW\Hyperf\Mail\Contracts\Factory as FactoryContract;
use SwooleTW\Hyperf\Mail\MailManager;
use Symfony\Component\Mailer\Transport\FailoverTransport;

/**
 * @internal
 * @coversNothing
 */
class MailFailoverTransportTest extends TestCase
{
    public function testGetFailoverTransportWithConfiguredTransports()
    {
        $container = $this->getContainer([
            'default' => 'failover',
            'mailers' => [
                'failover' => [
                    'transport' => 'failover',
                    'mailers' => [
                        'sendmail',
                        'array',
                    ],
                ],

                'sendmail' => [
                    'transport' => 'sendmail',
                    'path' => '/usr/sbin/sendmail -bs',
                ],

                'array' => [
                    'transport' => 'array',
                ],
            ],
        ]);

        $transport = $container->get(FactoryContract::class)
            ->removePoolable('failover')
            ->getSymfonyTransport();
        $this->assertInstanceOf(FailoverTransport::class, $transport);
    }

    public function testGetFailoverTransportWithLaravel6StyleMailConfiguration()
    {
        $container = $this->getContainer([
            'driver' => 'failover',
            'mailers' => ['sendmail', 'array'],
            'sendmail' => '/usr/sbin/sendmail -bs',
        ]);

        $transport = $container->get(FactoryContract::class)
            ->removePoolable('failover')
            ->getSymfonyTransport();
        $this->assertInstanceOf(FailoverTransport::class, $transport);
    }

    protected function getContainer(array $config = []): ContainerInterface
    {
        $container = new Container(
            new DefinitionSource([
                ConfigInterface::class => fn () => new Config(['mail' => $config]),
                FactoryContract::class => MailManager::class,
                ViewInterface::class => fn () => Mockery::mock(ViewInterface::class),
                EventDispatcherInterface::class => fn () => Mockery::mock(EventDispatcherInterface::class),
            ])
        );

        ApplicationContext::setContainer($container);

        return $container;
    }
}
