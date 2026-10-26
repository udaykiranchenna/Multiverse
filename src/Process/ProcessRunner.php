<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Process;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    public function __construct(protected ?ConfigRepository $config = null) {}

    /**
     * Run a command without a shell, with bounded input, output, and duration.
     *
     * @param  array<int, string>  $command
     * @param  array<string, string|false>|null  $env  Explicit, trusted environment overrides.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function run(array $command, ?string $cwd = null, ?array $env = null, ?string $input = null, ?float $timeout = null): string
    {
        $timeout ??= $this->config?->get('multiverse.timeout', 60) ?? 60;

        if (! is_finite($timeout) || $timeout <= 0) {
            throw new InvalidArgumentException('Process timeout must be a finite, positive number.');
        }

        $inputLimit = $this->byteLimit('max_input_bytes');
        $outputLimit = $this->byteLimit('max_output_bytes');

        if (strlen($input ?? '') > $inputLimit) {
            throw new InvalidArgumentException('Process input exceeds the configured byte limit.');
        }

        $process = new Process($command, $cwd, $this->environment($env ?? []), $input, $timeout);
        $output = '';
        $outputBytes = 0;
        $exceededOutputLimit = false;

        try {
            $process->run(function (string $type, string $buffer) use ($process, $outputLimit, &$output, &$outputBytes, &$exceededOutputLimit): void {
                $process->clearOutput();
                $process->clearErrorOutput();

                if ($exceededOutputLimit) {
                    return;
                }

                $outputBytes += strlen($buffer);

                if ($outputBytes > $outputLimit) {
                    $exceededOutputLimit = true;
                    throw new RuntimeException('Process output exceeds the configured byte limit.');
                }

                if ($type === Process::OUT) {
                    $output .= $buffer;
                }
            });
        } finally {
            $process->stop(0);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Worker process failed.', $process->getExitCode() ?? 1);
        }

        return $output;
    }

    protected function byteLimit(string $name): int
    {
        $limit = $this->config?->get('multiverse.process.'.$name, 10 * 1024 * 1024) ?? 10 * 1024 * 1024;

        if (! is_int($limit) || $limit <= 0) {
            throw new InvalidArgumentException('Process byte limits must be positive integers.');
        }

        return $limit;
    }

    /**
     * Symfony inherits the parent environment unless each unwanted key is false.
     *
     * @param  array<string, string|false>  $overrides
     * @return array<string, string|false>
     */
    protected function environment(array $overrides): array
    {
        $inherited = array_merge($_SERVER, (array) getenv(), $_ENV);
        $environment = array_fill_keys(array_keys($inherited), false);
        $allowed = $this->config?->get('multiverse.process.env_allowlist', [
            'PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE',
        ]) ?? [];

        if (! is_array($allowed)) {
            throw new InvalidArgumentException('The process environment allowlist must be an array.');
        }

        foreach ($allowed as $name) {
            if (! is_string($name) || ! preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $name)) {
                throw new InvalidArgumentException('The process environment allowlist contains an invalid name.');
            }

            if (isset($inherited[$name]) && is_string($inherited[$name])) {
                $environment[$name] = $inherited[$name];
            }
        }

        foreach ($overrides as $name => $value) {
            if (! is_string($name) || ! preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) || (! is_string($value) && $value !== false)) {
                throw new InvalidArgumentException('Process environment overrides require valid names and string or false values.');
            }

            $environment[$name] = $value;
        }

        return $environment;
    }

    /** Return stderr from a caller-owned Symfony process. */
    public function getErrorOutput(Process $process): string
    {
        return $process->getErrorOutput();
    }
}
