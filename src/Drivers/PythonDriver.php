<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Drivers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use MadeItEasyTools\Multiverse\Contracts\LanguageDriver;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;

class PythonDriver implements LanguageDriver
{
    public function __construct(
        protected ProcessRunner $processRunner
    ) {}

    public function installDependencies(string $workerPath): void
    {
        $toAbsolute = fn (string $path): string => str_starts_with($path, '/') ? $path : base_path($path);
        $pythonRoot = $toAbsolute(Config::get('multiverse.python.root_path', 'multiverse/python'));
        $venvPath = $toAbsolute(Config::get('multiverse.python.venv_path', 'multiverse/python/venv'));
        $reqPath = $toAbsolute(Config::get('multiverse.python.requirements_path', 'multiverse/python/requirements.txt'));

        // Check if venv exists (should be created by multiverse:install)
        if (! is_dir($venvPath)) {
            throw new \RuntimeException(
                "Python virtual environment not found at: {$venvPath}\n".
                'Please run: php artisan multiverse:install --lang=python'
            );
        }

        // Ensure requirements.txt exists
        if (! File::exists($reqPath)) {
            File::put($reqPath, '# Shared Python Requirements'.PHP_EOL);
        }

        // Install/update dependencies from requirements.txt
        $pipPath = $venvPath.'/bin/pip';
        $this->processRunner->run([$pipPath, 'install', '-r', $reqPath], $pythonRoot, null, null, Config::get('multiverse.pip_timeout', 300));
    }

    public function defaultScriptName(): string
    {
        return 'main.py';
    }

    public function getRunCommand(string $workerPath, string $scriptName): array
    {
        $fullPath = $workerPath.'/'.$scriptName;

        // This best-effort entry-point check is not a sandbox or a code audit.
        if (Config::get('multiverse.security.scan_for_dangerous_code', true) && file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            $dangerousPatterns = Config::get('multiverse.security.dangerous_patterns', []);

            foreach ($dangerousPatterns as $pattern => $message) {
                if (str_contains($content, $pattern)) {
                    throw new \RuntimeException("Security Violation: Dangerous code detected in worker [{$scriptName}]: {$message}");
                }
            }
        }

        $toAbsolute = fn (string $path): string => str_starts_with($path, '/') ? $path : base_path($path);
        $venvPath = $toAbsolute(Config::get('multiverse.python.venv_path', 'multiverse/python/venv'));
        $venvPython = $venvPath.'/bin/python';

        if (is_file($venvPython) && is_executable($venvPython)) {
            return [$venvPython, $workerPath.'/'.$scriptName];
        }

        if (Config::get('multiverse.python.allow_system_python', false)) {
            return ['python3', $workerPath.'/'.$scriptName];
        }

        throw new \RuntimeException('Python virtual environment is unavailable. Run multiverse:install --lang=python.');
    }
}
