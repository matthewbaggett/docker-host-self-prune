<?php

namespace Prune;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use GuzzleHttp\Client as Guzzle;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rych\ByteSize\ByteSize;
use Spatie\Emoji\Emoji;

class Pruner
{
    protected array $environment;
    protected Logger $logger;
    protected Guzzle $client;

    public function __construct(
    ) {
        $this->environment = array_merge($_ENV, $_SERVER);
        ksort($this->environment);

        // Configure Monolog
        $this->logger = new Logger('pruner');
        $this->logger->pushHandler(new StreamHandler('/var/log/pruner.log', Logger::DEBUG));
        $stdout = new StreamHandler('php://stdout', Logger::DEBUG);
        $stdout->setFormatter(new ColoredLineFormatter(null, "%level_name%: %message% \n"));
        $this->logger->pushHandler($stdout);

        // Configure Guzzle
        if (isset($this->environment['DOCKER_HOST'])) {
            $this->logger->info(sprintf('%s Connecting to %s', Emoji::electricPlug(), $this->environment['DOCKER_HOST']));
            $this->client = new Guzzle(['base_uri' => $this->environment['DOCKER_HOST']]);
        } else {
            $this->logger->info(sprintf('%s Connecting to /var/run/docker.sock', Emoji::electricPlug()));
            $this->client = new Guzzle(['base_uri' => 'http://localhost', 'curl' => [CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock']]);
        }
    }

    public function run(): void
    {
        $this->prune('containers');
        $this->prune('networks');
        $this->prune('volumes');
        $this->wait();
    }

    private function prune($type): void
    {
        $pruneStart = microtime(true);
        $this->logger->debug(sprintf(
            '%s Pruning %s...',
            Emoji::magnifyingGlassTiltedLeft(),
            $type
        ));
        $pruneResponse = json_decode($this->client->request('POST', "/{$type}/prune")->getBody()->getContents(), true);
        // \Kint::dump($pruneResponse);
        $this->logger->info(sprintf(
            '%s  Pruned %d %s and freed %s in %d seconds',
            Emoji::recyclingSymbol(),
            isset($pruneResponse[ucfirst($type).'Deleted']) ? count($pruneResponse[ucfirst($type).'Deleted']) : 0,
            $type,
            ByteSize::formatMetric($pruneResponse['SpaceReclaimed'] ?? 0),
            microtime(true) - $pruneStart
        ));
    }

    private function wait(): void
    {
        if (isset($this->environment['INTERVAL_SECONDS']) && is_numeric($this->environment['INTERVAL_SECONDS'])) {
            $this->logger->info(sprintf(
                '%s  Waiting %d seconds for next run',
                Emoji::timerClock(),
                $this->environment['INTERVAL_SECONDS']
            ));
            sleep($this->environment['INTERVAL_SECONDS']);
        }
    }
}
