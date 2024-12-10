<?php

declare(strict_types=1);

namespace SwooleTW\Hyperf\Tests\Http;

use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Response as HyperfResponse;
use Hyperf\View\RenderInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use SwooleTW\Hyperf\Http\Response;
use SwooleTW\Hyperf\HttpMessage\Exceptions\RangeNotSatisfiableHttpException;
use Swow\Psr7\Message\ResponsePlusInterface;
use Swow\Psr7\Message\ServerRequestPlusInterface;

/**
 * @internal
 * @coversNothing
 */
class ResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Context::destroy(ResponseInterface::class);
        Context::destroy(Response::RANGE_HEADERS_CONTEXT);
    }

    public function testMake()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $psrResponse = new \Hyperf\HttpMessage\Base\Response();
        Context::set(ResponseInterface::class, $psrResponse);

        $response = new Response();

        // Test with string content
        $result = $response->make('Hello World', 200, ['X-Test' => 'Test']);
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('Hello World', (string) $result->getBody());
        $this->assertEquals('Test', $result->getHeaderLine('X-Test'));
        $this->assertEquals('text/plain', $result->getHeaderLine('content-type'));

        // Test with array content
        $result = $response->make(['key' => 'value'], 201);
        $this->assertEquals(201, $result->getStatusCode());
        $this->assertEquals('{"key":"value"}', (string) $result->getBody());
        $this->assertEquals('application/json', $result->getHeaderLine('content-type'));

        // Test with Arrayable content
        $arrayable = new class implements Arrayable {
            public function toArray(): array
            {
                return ['foo' => 'bar'];
            }
        };
        $result = $response->make($arrayable);
        $this->assertEquals('{"foo":"bar"}', (string) $result->getBody());
        $this->assertEquals('application/json', $result->getHeaderLine('content-type'));

        // Test with Jsonable content
        $jsonable = new class implements Jsonable {
            public function __toString(): string
            {
                return '{"baz":"qux"}';
            }
        };
        $result = $response->make($jsonable);
        $this->assertEquals('{"baz":"qux"}', (string) $result->getBody());
        $this->assertEquals('application/json', $result->getHeaderLine('content-type'));
    }

    public function testNoContent()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $psrResponse = new \Hyperf\HttpMessage\Base\Response();
        Context::set(ResponseInterface::class, $psrResponse);

        $response = new Response();
        $result = $response->noContent(204, ['X-Empty' => 'Yes']);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(204, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
        $this->assertEquals('Yes', $result->getHeaderLine('X-Empty'));
    }

    public function testView()
    {
        $psrResponse = new \Hyperf\HttpMessage\Base\Response();
        Context::set(ResponseInterface::class, $psrResponse);

        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $renderer = Mockery::mock(RenderInterface::class);
        $renderer->shouldReceive('render')->with('test-view', ['data' => 'value'])->andReturn(
            (new HyperfResponse())->withAddedHeader('content-type', 'text/html')->withBody(new SwooleStream('<h1>Test</h1>'))
        );

        $container->shouldReceive('get')->with(RenderInterface::class)->andReturn($renderer);

        $response = new Response();
        $result = $response->view('test-view', ['data' => 'value'], 200, ['X-View' => 'Rendered']);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('<h1>Test</h1>', (string) $result->getBody());
        $this->assertEquals('Rendered', $result->getHeaderLine('X-View'));
        $this->assertEquals('text/html', $result->getHeaderLine('content-type'));
    }

    public function testGetPsr7Response()
    {
        $psrResponse = new \Hyperf\HttpMessage\Base\Response();
        $response = new Response($psrResponse);

        $this->assertSame($psrResponse, $response->getPsr7Response());
    }

    public function testStream()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldReceive('write')
            ->with($content = 'Streaming content')
            ->once()
            ->andReturnTrue();
        Context::set(ResponseInterface::class, $psrResponse);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $stream = new SwooleStream($content);
        $result = $response->stream(
            fn () => $stream->eof() ? false : $stream->read(1024),
            ['X-Download' => 'Yes']
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals([
            'Content-Type' => ['text/event-stream'],
            'X-Download' => ['Yes'],
        ], $result->getHeaders());
    }

    public function testStreamWithStringResult()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldReceive('write')
            ->with($content = 'Streaming content')
            ->once()
            ->andReturnTrue();
        Context::set(ResponseInterface::class, $psrResponse);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $result = $response->stream(
            fn () => $content,
            ['X-Download' => 'Yes']
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals([
            'Content-Type' => ['text/event-stream'],
            'X-Download' => ['Yes'],
        ], $result->getHeaders());
    }

    public function testStreamWithNonChunkable()
    {
        $psrResponse = Mockery::mock(ResponsePlusInterface::class);
        Context::set(ResponseInterface::class, $psrResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The response is not a chunkable response.');

        (new \SwooleTW\Hyperf\Http\Response())
            ->stream(fn () => 'test');
    }

    public function testStreamDownload()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldReceive('write')
            ->with($content = 'File content')
            ->once()
            ->andReturnTrue();
        Context::set(ResponseInterface::class, $psrResponse);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $stream = new SwooleStream($content);
        $result = $response->streamDownload(
            fn () => $stream->eof() ? false : $stream->read(1024),
            'test.txt',
            ['X-Download' => 'Yes']
        );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals([
            'Content-Type' => ['application/octet-stream'],
            'Content-Description' => ['File Transfer'],
            'Content-Transfer-Encoding' => ['binary'],
            'Pragma' => ['no-cache'],
            'Content-Disposition' => ['attachment; filename=test.txt'],
            'X-Download' => ['Yes'],
        ], $result->getHeaders());
    }

    public function testStreamDownloadWithRangeHeader()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldReceive('write')
            ->with($content = 'File content')
            ->once()
            ->andReturnTrue();
        Context::set(ResponseInterface::class, $psrResponse);

        $this->mockRequest([
            'Range' => ['bytes=0-1023'],
        ]);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $stream = new SwooleStream($content);
        $result = $response->withRangeHeaders(8888)
            ->streamDownload(
                fn () => $stream->eof() ? false : $stream->read(1024),
                'test.txt',
                ['X-Download' => 'Yes'],
                'attachment',
            );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame($result->getStatusCode(), 206);
        $this->assertEquals([
            'Content-Type' => ['application/octet-stream'],
            'Content-Description' => ['File Transfer'],
            'Content-Transfer-Encoding' => ['binary'],
            'Pragma' => ['no-cache'],
            'Content-Disposition' => ['attachment; filename=test.txt'],
            'X-Download' => ['Yes'],
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1024'],
            'Content-Range' => ['bytes 0-1023/8888'],
        ], $result->getHeaders());
    }

    public function testStreamDownloadWithRangeHeaderAndWithoutContentLength()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldReceive('write')
            ->with($content = 'File content')
            ->once()
            ->andReturnTrue();
        Context::set(ResponseInterface::class, $psrResponse);

        $this->mockRequest([
            'Range' => ['bytes=1024-2047'],
        ]);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $stream = new SwooleStream($content);
        $result = $response->withRangeHeaders()
            ->streamDownload(
                fn () => $stream->eof() ? false : $stream->read(1024),
                'test.txt',
                ['X-Download' => 'Yes']
            );

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertSame($result->getStatusCode(), 206);
        $this->assertEquals([
            'Content-Type' => ['application/octet-stream'],
            'Content-Description' => ['File Transfer'],
            'Content-Transfer-Encoding' => ['binary'],
            'Pragma' => ['no-cache'],
            'Content-Disposition' => ['attachment; filename=test.txt'],
            'X-Download' => ['Yes'],
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1024'],
            'Content-Range' => ['bytes 1024-2047/*'],
        ], $result->getHeaders());
    }

    public function testStreamDownloadWithInvalidRangeHeader()
    {
        $psrResponse = Mockery::mock(\Hyperf\HttpMessage\Server\Response::class)->makePartial();
        $psrResponse->shouldNotReceive('write');
        Context::set(ResponseInterface::class, $psrResponse);

        $this->mockRequest([
            'Range' => ['bytes=9000-10000'],
        ]);

        $this->expectException(RangeNotSatisfiableHttpException::class);

        $response = new \SwooleTW\Hyperf\Http\Response();
        $stream = new SwooleStream('File content');
        $response->withRangeHeaders(8888)
            ->streamDownload(
                fn () => $stream->eof() ? false : $stream->read(1024),
                'test.txt',
                ['X-Download' => 'Yes'],
                'attachment',
            );
    }

    protected function mockRequest(array $headers = [], string $method = 'GET'): ServerRequestPlusInterface
    {
        $request = Mockery::mock(ServerRequestPlusInterface::class);
        $request->shouldReceive('getMethod')->andReturn($method);

        foreach ($headers as $key => $value) {
            $request->shouldReceive('getHeader')->with($key)->andReturn($value);
            $request->shouldReceive('hasHeader')->with($key)->andReturn(true);
        }

        $request->shouldReceive('hasHeader')->andReturn(false);

        Context::set(ServerRequestInterface::class, $request);

        return $request;
    }
}
