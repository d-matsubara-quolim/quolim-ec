<?php

namespace App\Logging;

use App\LineWorks\LineWorksClient;
use Monolog\Level;
use Monolog\Logger;

final class CreateLineWorksLogger
{
    public function __construct(private readonly LineWorksClient $client) {}

    public function __invoke(array $config): Logger
    {
        return new Logger('lineworks', [
            new LineWorksHandler(
                $this->client,
                $config['channel'] ?? null,
                (int) ($config['throttle'] ?? 300),
                Level::fromName($config['level'] ?? 'error'),
            ),
        ]);
    }
}
