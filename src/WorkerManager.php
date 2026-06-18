<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use MadeItEasyTools\Multiverse\Contracts\LanguageDriver;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use RuntimeException;

/**
 * WorkerManager
 *
 * Core class responsible for executing workers written in other languages.
 * Handles driver resolution, path management, security checks, and process execution.
 */
class WorkerManager
{
    /** @var array<string, LanguageDriver> Cached language driver instances */
    protected array $drivers = [];

    public function __construct(
        protected ConfigRepository $config,
        protected ProcessRunner $processRunner,
        protected Container $container
    ) {}

    /**
     * Execute a worker with the given payload.
     *
     * This method:
     * 1. Validates the worker name for security
     * 2. Resolves the appropriate language driver
     * 3. Locates the worker script
     * 4. Executes the worker with the provided payload
     * 5. Returns the parsed JSON output
     *
     * @param  string  $workerName  The name of the worker to execute (alphanumeric, dashes, underscores only)
     * @param  array<string, mixed>  $payload  Data to pass to the worker as JSON
     * @return array<string, mixed> The worker's output parsed as an associative array
     *
     * @throws InvalidArgumentException If worker name contains invalid characters
     * @throws RuntimeException If worker is not found or execution fails
     *
     * @example
     * ```php
     * $result = $workerManager->run('image_processor', [
     *     'image_url' => 'https://example.com/image.jpg',
     *     'filter' => 'blur'
     * ]);
     * ```
     */
    public function run(string $workerName, array $payload = []): array
    {
        // SECURITY: Prevent Path Traversal
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $workerName)) {
            throw new InvalidArgumentException("Invalid worker name [{$workerName}]. Only alphanumeric characters, dashes, and underscores are allowed.");
        }

        $driverName = $payload['driver'] ?? 'python';
        unset($payload['driver']);
        $driver = $this->driver($driverName);

        // 2. Determine paths
        $workersPath = $this->config->get('multiverse.workers_path');

        // Try language-specific subfolder first: workers/python/worker_name
        $driverSpecificPath = rtrim($workersPath, '/').'/'.$driverName.'/'.$workerName;

        // Fallback to flat structure: workers/worker_name
        $flatPath = rtrim($workersPath, '/').'/'.$workerName;

        $scriptName = $driver->defaultScriptName();

        if (is_dir($driverSpecificPath)) {
            $workerPath = $driverSpecificPath;
        } elseif (is_dir($flatPath)) {
            $workerPath = $flatPath;
        } else {
            // Fallback: check if it's just a single file (use driver's extension)
            $ext = pathinfo($scriptName, PATHINFO_EXTENSION);
            if (file_exists("{$driverSpecificPath}.{$ext}")) {
                $workerPath = dirname($driverSpecificPath);
                $scriptName = basename($driverSpecificPath).".{$ext}";
            } elseif (file_exists("{$flatPath}.{$ext}")) {
                $workerPath = dirname($flatPath);
                $scriptName = basename($flatPath).".{$ext}";
            } else {
                throw new RuntimeException("Worker not found: {$workerName} (checked {$driverSpecificPath}, {$flatPath})");
            }
        }

        // 3. Build Command
        $command = $driver->getRunCommand($workerPath, $scriptName);

        // 4. Encode Payload
        $input = json_encode($payload, JSON_THROW_ON_ERROR);

        // 5. Get Timeout (payload _timeout overrides config default)
        $timeout = $payload['_timeout'] ?? $this->config->get('multiverse.timeout', null);

        // 6. Execute with structured error handling
        try {
            $output = $this->processRunner->run($command, $workerPath, null, $input, $timeout);
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $exception = new \MadeItEasyTools\Multiverse\Exceptions\TimeoutException(
                $workerName,
                $driverName,
                $timeout
            );
            $this->logWorkerError($workerName, $payload, $exception);
            throw $exception;
        } catch (RuntimeException $e) {
            $exception = new \MadeItEasyTools\Multiverse\Exceptions\WorkerException(
                "Worker [{$workerName}] failed: ".$e->getMessage(),
                $workerName,
                $driverName,
                $e->getCode(),
                $e->getMessage()
            );
            $this->logWorkerError($workerName, $payload, $exception);
            throw $exception;
        }

        // 7. Parse Output
        try {
            return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['status' => 'error', 'raw_output' => $output];
        }
    }

    /**
     * Log worker errors to Laravel log if logging is enabled.
     */
    protected function logWorkerError(string $workerName, array $payload, \Exception $exception): void
    {
        if (! $this->config->get('multiverse.logging.enabled', true)) {
            return;
        }

        \Illuminate\Support\Facades\Log::channel(
            $this->config->get('multiverse.logging.channel', 'stack')
        )->error("Multiverse Worker Failed: {$workerName}", [
            'worker' => $workerName,
            'payload' => $payload,
            'error' => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);
    }

    public function driver(string $driver): LanguageDriver
    {
        if (isset($this->drivers[$driver])) {
            return $this->drivers[$driver];
        }

        $driverClass = $this->config->get("multiverse.drivers.{$driver}");

        if (! $driverClass || ! class_exists($driverClass)) {
            throw new InvalidArgumentException("Driver [{$driver}] not configured or class not found.");
        }

        $this->drivers[$driver] = $this->container->make($driverClass);

        return $this->drivers[$driver];
    }
}
