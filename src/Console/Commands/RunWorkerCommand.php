<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Console\Commands;

use Illuminate\Console\Command;
use MadeItEasyTools\Multiverse\Facades\Multiverse;

class RunWorkerCommand extends Command
{
    protected $signature = 'multiverse:run
                            {worker : The worker name}
                            {--payload= : JSON object to send to stdin}
                            {--lang=python : Python execution driver (python only)}
                            {--timeout= : Positive timeout in seconds, capped by max_timeout}';

    protected $description = 'Run a Python worker manually';

    public function handle(): int
    {
        try {
            $payload = json_decode($this->option('payload') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->error('Invalid JSON payload.');

            return self::FAILURE;
        }

        if (! is_array($payload)) {
            $this->error('The payload must be a JSON object or array.');

            return self::FAILURE;
        }

        $options = ['driver' => $this->option('lang')];
        $timeout = $this->option('timeout');

        if ($timeout !== null) {
            if (! is_numeric($timeout)) {
                $this->error('The timeout must be a positive number.');

                return self::FAILURE;
            }

            $options['timeout'] = (float) $timeout;
        }

        try {
            $result = Multiverse::run($this->argument('worker'), $payload, $options);
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Exception $exception) {
            $this->error('Execution failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
