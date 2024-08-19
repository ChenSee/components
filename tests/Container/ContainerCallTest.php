<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Container;

use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\ClosureDefinitionCollector;
use Hyperf\Di\ClosureDefinitionCollectorInterface;
use Hyperf\Di\MethodDefinitionCollector;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Serializer\SimpleNormalizer;
use PHPUnit\Framework\TestCase;
use stdClass;
use SwooleTW\Hyperf\Container\BindingResolutionException;
use SwooleTW\Hyperf\Container\Container;
use SwooleTW\Hyperf\Container\DefinitionSource;

/**
 * @internal
 * @coversNothing
 */
class ContainerCallTest extends TestCase
{
    protected function getContainer(array $dependencies = [])
    {
        return new Container(
            new DefinitionSource(
                array_merge([
                    MethodDefinitionCollectorInterface::class => MethodDefinitionCollector::class,
                    ClosureDefinitionCollectorInterface::class => ClosureDefinitionCollector::class,
                    NormalizerInterface::class => SimpleNormalizer::class,
                ], $dependencies)
            )
        );
    }

    public function testCallWithAtSignBasedClassReferencesWithoutMethodThrowsException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Invalid callable `ContainerTestCallStub` provided.');

        $this->getContainer()->call('ContainerTestCallStub');
    }

    public function testCallWithAtSignBasedClassReferences()
    {
        $container = $this->getContainer();
        $result = $container->call(ContainerTestCallStub::class . '@work', ['foo', 'bar']);

        $this->assertEquals(['foo', 'bar'], $result);

        $container = $this->getContainer();
        $result = $container->call(ContainerTestCallStub::class . '@inject');
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);

        $container = $this->getContainer();
        $result = $container->call(ContainerTestCallStub::class . '@inject', ['default' => 'foo']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('foo', $result[1]);

        $container = $this->getContainer();
        $result = $container->call(ContainerTestCallStub::class, ['foo', 'bar'], 'work');
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallWithCallableArray()
    {
        $container = $this->getContainer();
        $stub = new ContainerTestCallStub();
        $result = $container->call([$stub, 'work'], ['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testCallWithBindMethod()
    {
        $container = $this->getContainer();
        $container->bindMethod(ContainerTestCallStub::class . '@work', function ($stub) {
            $this->assertInstanceOf(ContainerTestCallStub::class, $stub);
            return 'foo';
        });
        $stub = new ContainerTestCallStub();
        $result = $container->call([$stub, 'work'], ['foo', 'bar']);
        $this->assertSame('foo', $result);
    }

    public function testCallWithStaticMethodNameString()
    {
        $container = $this->getContainer();
        $result = $container->call('SwooleTW\Hyperf\Tests\Container\ContainerStaticMethodStub::inject');
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testCallWithBoundMethod()
    {
        $container = $this->getContainer();
        $container->bindMethod(ContainerTestCallStub::class . '@unresolvable', function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = $container->call(ContainerTestCallStub::class . '@unresolvable');
        $this->assertEquals(['foo', 'bar'], $result);

        $container = $this->getContainer();
        $container->bindMethod(ContainerTestCallStub::class . '@unresolvable', function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = $container->call([new ContainerTestCallStub(), 'unresolvable']);
        $this->assertEquals(['foo', 'bar'], $result);

        $container = $this->getContainer();
        $result = $container->call([new ContainerTestCallStub(), 'inject'], ['_stub' => 'foo', 'default' => 'bar']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('bar', $result[1]);

        $container = $this->getContainer();
        $result = $container->call([new ContainerTestCallStub(), 'inject'], ['_stub' => 'foo']);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('taylor', $result[1]);
    }

    public function testBindMethodAcceptsAnArray()
    {
        $container = $this->getContainer();
        $container->bindMethod([ContainerTestCallStub::class, 'unresolvable'], function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = $container->call(ContainerTestCallStub::class . '@unresolvable');
        $this->assertEquals(['foo', 'bar'], $result);

        $container = $this->getContainer();
        $container->bindMethod([ContainerTestCallStub::class, 'unresolvable'], function ($stub) {
            return $stub->unresolvable('foo', 'bar');
        });
        $result = $container->call([new ContainerTestCallStub(), 'unresolvable']);
        $this->assertEquals(['foo', 'bar'], $result);
    }

    public function testClosureCallWithInjectedDependency()
    {
        $container = $this->getContainer();
        $container->call(function (ContainerCallConcreteStub $stub, string $foo) {
            $this->assertInstanceOf(ContainerCallConcreteStub::class, $stub);
            $this->assertSame('bar', $foo);
        }, ['foo' => 'bar']);

        $container->call(function (ContainerCallConcreteStub $stub, string $foo) {
            $this->assertInstanceOf(ContainerCallConcreteStub::class, $stub);
            $this->assertSame('bar', $foo);
        }, ['foo' => 'bar', 'stub' => new ContainerCallConcreteStub()]);
    }

    public function testCallWithDependencies()
    {
        $container = $this->getContainer();
        $result = $container->call(function (stdClass $foo, $bar = []) {
            return func_get_args();
        });

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertEquals([], $result[1]);

        $result = $container->call(function (stdClass $foo, $bar = []) {
            return func_get_args();
        }, ['bar' => 'taylor']);

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertSame('taylor', $result[1]);

        $stub = new ContainerCallConcreteStub();
        $result = $container->call(function (stdClass $foo, ContainerCallConcreteStub $bar) {
            return func_get_args();
        }, [ContainerCallConcreteStub::class => $stub]);

        $this->assertInstanceOf(stdClass::class, $result[0]);
        $this->assertSame($stub, $result[1]);
    }

    public function testCallWithCallableObject()
    {
        $container = $this->getContainer();
        $callable = new ContainerCallCallableStub();
        $result = $container->call($callable);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('jeffrey', $result[1]);
    }

    public function testCallWithCallableClassString()
    {
        $container = $this->getContainer();
        $result = $container->call(ContainerCallCallableClassStringStub::class);
        $this->assertInstanceOf(ContainerCallConcreteStub::class, $result[0]);
        $this->assertSame('jeffrey', $result[1]);
        $this->assertInstanceOf(ContainerTestCallStub::class, $result[2]);
    }

    public function testCallWithoutRequiredParamsThrowsException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class SwooleTW\Hyperf\Tests\Container\ContainerTestCallStub');

        $container = $this->getContainer();
        $container->call(ContainerTestCallStub::class . '@unresolvable');
    }

    public function testCallWithUnnamedParametersByOrder()
    {
        $container = $this->getContainer();
        $result = $container->call([new ContainerTestCallStub(), 'unresolvable'], ['foo', 'bar']);

        $this->assertSame('foo', $result[0]);
        $this->assertSame('bar', $result[1]);
    }

    public function testCallWithoutRequiredParamsOnClosureThrowsException()
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Unable to resolve dependency [Parameter #0 [ <required> $foo ]] in class SwooleTW\Hyperf\Tests\Container\ContainerCallTest');

        $container = $this->getContainer();
        $container->call(function (string $foo, $bar = 'default') {
            return $foo;
        });
    }
}

class ContainerTestCallStub
{
    public function work()
    {
        return func_get_args();
    }

    public function inject(ContainerCallConcreteStub $stub, $default = 'taylor')
    {
        return func_get_args();
    }

    public function unresolvable(string $foo, string $bar)
    {
        return func_get_args();
    }
}

class ContainerCallConcreteStub
{
}

class ContainerStaticMethodStub
{
    public static function inject(ContainerCallConcreteStub $stub, $default = 'taylor')
    {
        return func_get_args();
    }
}

class ContainerCallCallableStub
{
    public function __invoke(ContainerCallConcreteStub $stub, $default = 'jeffrey')
    {
        return func_get_args();
    }
}

class ContainerCallCallableClassStringStub
{
    public $stub;

    public $default;

    public function __construct(ContainerCallConcreteStub $stub, $default = 'jeffrey')
    {
        $this->stub = $stub;
        $this->default = $default;
    }

    public function __invoke(ContainerTestCallStub $dependency)
    {
        return [$this->stub, $this->default, $dependency];
    }
}
