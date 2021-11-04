<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Exception;
use GuzzleHttp\Psr7\StreamWrapper;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use stdClass;

class Component extends BaseComponent
{

    private TravisClient $client;

    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $clientConfig = [];
        if (!defined('KBC_STACKID')) {
            // development
            $cachedir = $this->getDataDir() . '/.cache';
            $clientConfig = ['cacheDir' => $cachedir];
            $this->getLogger()->notice(sprintf('Using cachedir "%s"', $cachedir));
        }

        $this->client = new TravisClient(
            $clientConfig,
            $config->getToken()
        );
        $repos = $this->client->repositories();
        $filteredRepos = array_filter($repos, fn($repo) => $repo->slug === $config->getRepo());
        if (count($filteredRepos) === 0) {
            throw new UserException(sprintf('Selected repo "%s" not found', $config->getRepo()));
        }
        if (count($filteredRepos) > 1) {
            throw new Exception(sprintf('Multiple repos with slug "%s" found', $config->getRepo()));
        }

        $repo = reset($filteredRepos);

        $this->extractBuilds($repo, $config->getBranch(), $config->getState());
    }

    private function extractBuilds(stdClass $repo, ?string $branch, ?string $state): void
    {
        $outfilesDir = $this->getDataDir() . '/out/files';
        if (!file_exists($outfilesDir)) {
            mkdir($outfilesDir, 0777, true);
        }

        $this->getLogger()->notice(sprintf('Exporting repo "%s"', $repo->name));
        foreach ($this->client->buildsOfRepo($repo, $branch, $state) as $build) {
            foreach ($this->client->stagesOfBuild($build, true) as $stage) {
                foreach ($stage->jobs as $job) {
                    $this->getLogger()->notice(sprintf(
                        'Job "%s" (%s) from %s',
                        $job->number,
                        $job->state,
                        $job->finished_at
                    ));
                    $log = $this->client->logOfJob($job);
                    $logStream = StreamWrapper::getResource($this->client->getLogStreamFromLog($log));
                    $logFilenameParts = [];
                    $logFilenameParts[] = 'log';
                    $logFilenameParts[] = $repo->slug;

                    $logFilenameParts[] = $job->number;
                    $logFilenameParts = array_map(fn($part) => strtr($part, '/', '-'), $logFilenameParts);
                    $destinationStreamFileName = $outfilesDir . '/' . implode('-', $logFilenameParts);
                    $destinationStream = fopen($destinationStreamFileName, 'w+');
                    if (!$destinationStream) {
                        throw new Exception(sprintf('Cannot write log to "%s"', $destinationStreamFileName));
                    }
                    stream_copy_to_stream($logStream, $destinationStream);
                    fclose($destinationStream);
                }
            }
        }
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    private function parseBuild(stdClass $build): void
    {
    }
}
