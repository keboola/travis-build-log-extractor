<?php

declare(strict_types = 1);

namespace TravisLogExtractor\Client;

use Psr\Http\Message\RequestInterface;

class TravisApiMiddleware
{

    private string $travisToken;

    public function __construct(
        string $travisToken
    )
    {
        $this->travisToken = $travisToken;
    }
    public function __invoke()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler(
                    $request
                        ->withAddedHeader('Authorization', 'token ' . $this->travisToken)
                        ->withAddedHeader('Travis-API-Version', 3),
                    $options
                );
            };
        };
    }
}
