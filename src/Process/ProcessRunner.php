<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Process;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    /**
     * Run a process with optional timeout.
     *
     * @param  array  $command  Command and arguments to execute
     * @param  string|null  $cwd  Working directory
     * @param  array|null  $env  Environment variables
     * @param  string|null  $input  Input to send to stdin
     * @param  float|null  $timeout  Timeout in seconds (null = unlimited)
     * @return string Process output
     *
     * @throws RuntimeException If process fails
     */
    public function run(array $command, ?string $cwd = null, ?array $env = null, ?string $input = null, ?float $timeout = null): string
    {
        $process = new Process($command, $cwd, $env, $input, $timeout);

        $process->run();

        if (! $process->isSuccessful()) {
            Log::error('Multiverse worker process failed', [
                'command' => $command,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new RuntimeException(
                $process->getErrorOutput() ?: $process->getOutput(),
                $process->getExitCode() ?? 1
            );
        }

        return $process->getOutput();
    }

    /**
     * Get the stderr output from the last process run.
     * This is useful for debugging when a process fails.
     */
    public function getErrorOutput(Process $process): string
    {
        return $process->getErrorOutput();
    }
}
