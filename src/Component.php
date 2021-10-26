<?php

declare(strict_types=1);

namespace TravisLogExtractor;

use Exception;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\Uri;
use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Psr\Http\Message\ResponseInterface;
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
        if (count($filteredRepos) === 0) {
            throw new Exception(sprintf('Multiple repos with slug "%s" found', $config->getRepo()));
        }

        $repo = reset($filteredRepos);

        $this->extractBuilds($repo, $config->getBranch(), $config->getState());
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    private function extractBuilds(stdClass $repo, ?string $branch, ?string $state)
    {
        $outDir = $this->getDataDir() . '/out';
        $outfilesDir = $outDir . '/files';
        if (!file_exists($outfilesDir)) {
            mkdir($outfilesDir, 0777, true);
        }

        $summaryFilename = $outDir . '/jobs.csv';
        $this->prepareSummary($summaryFilename);

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
                    $logFilename = $outfilesDir . '/' . implode('-', $logFilenameParts);
                    $destinationStream = fopen($logFilename, 'w+');
                    stream_copy_to_stream($logStream, $destinationStream);
                    fclose($destinationStream);

                    $this->addLineToSummary($summaryFilename, [
                        $repo->slug,
                        $build->branch->name,
                        $build->id,
                        $build->number,
                        $job->number,
                        $job->state,
                        basename($logFilename),
                        filesize($logFilename),
                        $job->started_at,
                        $job->finished_at,
                    ]);
                }
            }
        }
    }

    private function prepareSummary(string $filename)
    {
        $summaryHeader = [
            'repo',
            'branch',
            'build_id',
            'build_number',
            'job',
            'job_state',
            'job_file',
            'job_file_size',
            'started_at',
            'finished_at',
        ];
        file_put_contents($filename, implode(',', $summaryHeader));
    }

    private function addLineToSummary(string $filename, array $line)
    {
        file_put_contents($filename, "\n" . implode(',', $line), FILE_APPEND);
    }

    private function parseBuild(stdClass $build)
    {

    }
}
