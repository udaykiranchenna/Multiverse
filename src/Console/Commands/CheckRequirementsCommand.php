<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class CheckRequirementsCommand extends Command
{
    protected $signature = 'multiverse:check {--lang=python : Language driver to check (python)}';

    protected $description = 'Check server requirements for Multiverse';

    private bool $hasFailures = false;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Multiverse Requirements Check</>');
        $this->newLine();

        $this->checkPhp();
        $this->newLine();

        $lang = $this->option('lang');

        if ($lang === 'python') {
            $this->checkPython();
            $this->newLine();
        }

        if ($this->hasFailures) {
            $this->line('  <fg=red;options=bold>✗ Some requirements are not met. See fixes above.</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  <fg=green;options=bold>✓ All requirements met. You are good to go!</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function checkPhp(): void
    {
        $this->line('  <options=bold>PHP</>');

        // PHP version
        $version = PHP_VERSION;
        $meetsVersion = version_compare($version, '8.1.0', '>=');
        $this->result($meetsVersion, "PHP {$version}", 'PHP 8.1+ required');

        // proc_open
        $procOpenAvailable = function_exists('proc_open') && ! in_array('proc_open', array_map('trim', explode(',', ini_get('disable_functions'))));
        $this->result(
            $procOpenAvailable,
            'proc_open is available',
            'proc_open is disabled — remove it from disable_functions in php.ini or your PHP-FPM pool config'
        );
    }

    private function checkPython(): void
    {
        $this->line('  <options=bold>Python</>');

        // python3 binary
        $pythonVersion = $this->runBinary(['python3', '--version']);
        $hasPython = $pythonVersion !== null;
        $this->result($hasPython, $hasPython ? trim($pythonVersion) : 'python3 not found', 'Install Python 3.8+: apt install python3');

        if (! $hasPython) {
            $this->hasFailures = true;

            return;
        }

        // python3 version >= 3.8
        $versionOutput = trim($pythonVersion ?? '');
        preg_match('/Python (\d+\.\d+)/', $versionOutput, $matches);
        $meetsVersion = isset($matches[1]) && version_compare($matches[1], '3.8', '>=');
        $this->result($meetsVersion, $meetsVersion ? "Version {$matches[1]} meets 3.8+ requirement" : "Version {$matches[1]} is below 3.8", 'Upgrade to Python 3.8+');

        // python3 -m venv
        $venvCheck = $this->runBinary(['python3', '-m', 'venv', '--help']);
        $hasVenv = $venvCheck !== null;

        preg_match('/Python (\d+\.\d+)/', $versionOutput, $vMatches);
        $versionHint = isset($vMatches[1]) ? str_replace('.', '', $vMatches[1]) : 'X';
        $this->result($hasVenv, 'python3-venv available', "Install venv: apt install python3.{$vMatches[1]}-venv");

        // gcc
        $gccVersion = $this->runBinary(['gcc', '--version']);
        $this->result($gccVersion !== null, $gccVersion ? trim(explode("\n", $gccVersion)[0]) : 'gcc not found', 'Install build tools: apt install build-essential');

        // g++
        $gppVersion = $this->runBinary(['g++', '--version']);
        $this->result($gppVersion !== null, $gppVersion ? trim(explode("\n", $gppVersion)[0]) : 'g++ not found', 'Install build tools: apt install build-essential');

        // venv pip (only if venv exists)
        $venvPath = config('multiverse.python.venv_path', 'multiverse/python/venv');
        if (! str_starts_with($venvPath, '/')) {
            $venvPath = base_path($venvPath);
        }

        $pipPath = $venvPath.'/bin/pip';
        if (is_dir($venvPath)) {
            $pipCheck = file_exists($pipPath) ? $this->runBinary([$pipPath, '--version']) : null;
            $this->result($pipCheck !== null, $pipCheck ? 'pip available in venv' : 'pip missing from venv', 'Run: php artisan multiverse:install --lang=python');
        } else {
            $this->line('  <fg=yellow>  → Venv not created yet — run: php artisan multiverse:install --lang=python</>');
        }
    }

    private function result(bool $pass, string $passMessage, string $failFix): void
    {
        if ($pass) {
            $this->line("  <fg=green>  ✓</> {$passMessage}");
        } else {
            $this->line("  <fg=red>  ✗</> {$failFix}");
            $this->hasFailures = true;
        }
    }

    private function runBinary(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                return $process->getOutput() ?: $process->getErrorOutput();
            }

            // Some binaries (like --version) write to stderr
            $stderr = $process->getErrorOutput();
            if ($stderr !== '') {
                return $stderr;
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
