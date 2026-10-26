<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use MadeItEasyTools\Multiverse\Contracts\LanguageDriver;
use MadeItEasyTools\Multiverse\Exceptions\TimeoutException;
use MadeItEasyTools\Multiverse\Exceptions\WorkerException;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class WorkerManager
{
    /** @var array<string, LanguageDriver> */
    protected array $drivers = [];

    public function __construct(
        protected ConfigRepository $config,
        protected ProcessRunner $processRunner,
        protected Container $container
    ) {}

    /**
     * Execute trusted worker code with JSON data supplied over stdin.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{driver?: string, timeout?: int|float, env?: array<string, string|false>}  $options  Trusted execution options, never request data.
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws WorkerException
     */
    public function run(string $workerName, array $payload = [], array $options = []): array
    {
        $this->validateName($workerName, 'worker');

        if (array_key_exists('driver', $payload) || array_key_exists('_timeout', $payload)) {
            throw new InvalidArgumentException('Move driver and _timeout out of the payload. Use the third argument: ["driver" => "python", "timeout" => 60].');
        }

        if (array_diff(array_keys($options), ['driver', 'timeout', 'env']) !== []) {
            throw new InvalidArgumentException('Unknown worker execution option. Supported options: driver, timeout, env.');
        }

        $driverName = array_key_exists('driver', $options) ? $options['driver'] : 'python';
        if (! is_string($driverName)) {
            throw new InvalidArgumentException('The driver name must be a string.');
        }

        $timeout = $this->resolveTimeout($options);
        $driver = $this->driver($driverName);
        [$workerPath, $scriptName] = $this->resolveScript($workerName, $driverName, $driver);
        $input = json_encode($payload, JSON_THROW_ON_ERROR);

        if (array_key_exists('env', $options) && ! is_array($options['env'])) {
            throw new InvalidArgumentException('The worker environment must be an array.');
        }

        try {
            $command = $driver->getRunCommand($workerPath, $scriptName);
            $output = $this->processRunner->run($command, $workerPath, $options['env'] ?? null, $input, $timeout);

            try {
                $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('Worker returned invalid JSON.');
            }

            if (! is_array($result)) {
                throw new RuntimeException('Worker must return a JSON object or array.');
            }

            return $result;
        } catch (ProcessTimedOutException) {
            $exception = new TimeoutException($workerName, $driverName, $timeout);
            $this->logWorkerError($exception);

            throw $exception;
        } catch (RuntimeException $exception) {
            $failure = new WorkerException(
                "Worker [{$workerName}] failed. Check the worker implementation and execution limits.",
                $workerName,
                $driverName,
                $exception->getCode()
            );
            $this->logWorkerError($failure);

            throw $failure;
        }
    }

    /**
     * Resolve a configured driver; names cannot address nested config values.
     *
     * @throws InvalidArgumentException
     */
    public function driver(string $driver): LanguageDriver
    {
        $this->validateName($driver, 'driver');

        if (isset($this->drivers[$driver])) {
            return $this->drivers[$driver];
        }

        $driverClass = $this->config->get("multiverse.drivers.{$driver}");

        if (! is_string($driverClass) || ! is_a($driverClass, LanguageDriver::class, true)) {
            throw new InvalidArgumentException('The requested driver is not configured as a language driver.');
        }

        return $this->drivers[$driver] = $this->container->make($driverClass);
    }

    protected function validateName(string $name, string $kind): void
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $name)) {
            throw new InvalidArgumentException("Invalid {$kind} name. Use 1 to 128 letters, numbers, dashes, or underscores.");
        }
    }

    /** @param array<string, mixed> $options */
    protected function resolveTimeout(array $options): float
    {
        $maximum = $this->config->get('multiverse.max_timeout', 300);
        $timeout = array_key_exists('timeout', $options)
            ? $options['timeout']
            : $this->config->get('multiverse.timeout', 60);

        foreach ([$maximum, $timeout] as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value <= 0) {
                throw new InvalidArgumentException('Worker timeout and max_timeout must be finite, positive numbers.');
            }
        }

        if ($timeout > $maximum) {
            throw new InvalidArgumentException('Worker timeout exceeds the configured max_timeout.');
        }

        return (float) $timeout;
    }

    /** @return array{string, string} */
    protected function resolveScript(string $workerName, string $driverName, LanguageDriver $driver): array
    {
        $configuredRoot = $this->config->get('multiverse.workers_path');
        $root = is_string($configuredRoot) ? realpath($configuredRoot) : false;

        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The configured workers directory does not exist.');
        }

        $scriptName = $driver->defaultScriptName();
        $extension = pathinfo($scriptName, PATHINFO_EXTENSION);
        $languagePath = $root.'/'.$driverName.'/'.$workerName;
        $flatPath = $root.'/'.$workerName;
        $candidates = [
            $languagePath.'/'.$scriptName,
            $flatPath.'/'.$scriptName,
            $languagePath.'.'.$extension,
            $flatPath.'.'.$extension,
        ];

        foreach ($candidates as $candidate) {
            $script = realpath($candidate);

            if ($script === false || ! is_file($script)) {
                continue;
            }

            if (! str_starts_with($script, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Worker scripts must resolve inside the configured workers directory.');
            }

            return [dirname($script), basename($script)];
        }

        throw new RuntimeException("Worker [{$workerName}] was not found.");
    }

    protected function logWorkerError(WorkerException $exception): void
    {
        if (! $this->config->get('multiverse.logging.enabled', true)) {
            return;
        }

        Log::channel($this->config->get('multiverse.logging.channel', 'stack'))->error('Multiverse worker failed', [
            'worker' => $exception->getWorkerName(),
            'driver' => $exception->getDriver(),
            'exit_code' => $exception->getExitCode(),
            'exception' => $exception::class,
        ]);
    }
}
