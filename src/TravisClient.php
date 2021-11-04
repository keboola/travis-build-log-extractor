<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TravisLogExtractor\Client\TravisApiMiddleware;

class TravisClient
{

    private GuzzleClient $http;

    /**
     * @param mixed[] $config
     */
    public function __construct(
        array $config,
        string $travisToken
    ) {
        $stack = HandlerStack::create();
        $travisApiMiddleware = new TravisApiMiddleware($travisToken);
        $stack->push($travisApiMiddleware());

        if (isset($config['cacheDir'])) {
            $stack->push(
                new CacheMiddleware(
                    new GreedyCacheStrategy(
                        new Psr6CacheStorage(
                            new FilesystemAdapter('', 0, $config['cacheDir'])
                        ),
                        60 * 60 * 24 * 365, // the TTL in seconds
                        new KeyValueHttpHeader(['Authorization'])
                    )
                ),
                'cache'
            );
        }
        $overrideConfig = [
            'base_uri' => 'https://api.travis-ci.com',
            'handler' => $stack,
            'http_errors' => false,
        ];
        $this->http = new GuzzleClient(array_merge($config, $overrideConfig));
    }

    /**
     * @return stdClass[]
     */
    public function repositories(): array
    {
        $res = $this->http->request('GET', 'repos');

        $response = $res->getBody()->getContents();
        return json_decode($response)->repositories;
    }

    /**
     * @return Generator<stdClass>
     */
    public function buildsOfRepo(stdClass $repo, ?string $branch, ?string $state): Generator
    {
        $query = [];
        if ($branch !== null) {
            $query['branch.name'] = $branch;
        }
        if ($state !== null) {
            $query['build.state'] = $state;
        }
        $rootUri = new Uri($repo->{'@href'} . '/builds');
        $nextUri = $rootUri->withQuery(http_build_query($query));

        foreach ($this->processPaginated($nextUri, 'builds') as $build) {
            yield $build;
        }
    }

    protected function processPaginated(UriInterface $uri, string $itemKey): Generator
    {
        $next = $uri;
        do {
            $response = $this->http->request('GET', $next);
            $body = $response->getBody()->getContents();
            $page = json_decode($body);
            $next = null;
            if (!$page->{'@pagination'}->is_last) {
                $next = $page->{'@pagination'}->next->{'@href'};
            }
            foreach ($page->{$itemKey} as $item) {
                yield $item;
            }
        } while ($next);
    }

    /**
     * @return Generator<stdClass>
     */
    public function stagesOfBuild(stdClass $build, bool $withJobs): Generator
    {
        $query = [];
        if ($withJobs) {
            $query['include'] = 'stage.jobs';
        }
        $uri = new Uri($build->{'@href'} . '/stages');
        $uri = $uri->withQuery(http_build_query($query));
        $response = $this->http->request('GET', $uri);
        $body = $response->getBody()->getContents();
        $page = json_decode($body);
        foreach ($page->stages as $stage) {
            yield $stage;
        }
    }

    public function logOfJob(stdClass $job): stdClass
    {
        $response = $this->http->request('GET', $job->{'@href'} . '/log');
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public function getLogStreamFromLog(stdClass $log): StreamInterface
    {
        return $this->http->request('GET', $log->{'@raw_log_href'})->getBody();
    }
}
