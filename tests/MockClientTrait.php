<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Nomokonov\SberSdk\Authorization\ApiClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Helper for building an {@see ApiClient} backed by a Guzzle MockHandler.
 */
trait MockClientTrait
{
    /** @var list<array{request: RequestInterface, response: mixed}> */
    private array $history = [];

    /**
     * @param list<Throwable|ResponseInterface> $queue
     * @param array<string, mixed>                                 $config
     */
    private function makeClient(array $queue, array $config = []): ApiClient
    {
        $this->history = [];
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $http = new Client(['handler' => $stack, 'http_errors' => true]);

        return new ApiClient(
            ['host' => 'https://api.test.local:9443', 'retryDelay' => 1, ...$config],
            $http,
        );
    }

    private function lastRequest(): RequestInterface
    {
        $last = end($this->history);
        \assert(\is_array($last));

        return $last['request'];
    }
}
