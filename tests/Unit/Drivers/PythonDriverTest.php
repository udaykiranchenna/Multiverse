<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Drivers;

use MadeItEasyTools\Multiverse\Drivers\PythonDriver;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use RuntimeException;

class PythonDriverTest extends TestCase
{
    public function test_requires_the_configured_virtual_environment_by_default(): void
    {
        $script = $this->worker("print('{}')");
        $this->config->set('multiverse.python.allow_system_python', false);
        $driver = new PythonDriver($this->runner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('virtual environment is unavailable');
        $driver->getRunCommand(dirname($script), 'main.py');
    }

    public function test_uses_the_virtual_environment_interpreter_when_available(): void
    {
        $script = $this->worker("print('{}')");
        $venv = $this->directory.'/multiverse/python/venv';
        mkdir($venv.'/bin', 0700, true);
        symlink(PHP_BINARY, $venv.'/bin/python');
        $driver = new PythonDriver($this->runner);

        self::assertSame([$venv.'/bin/python', $script], $driver->getRunCommand(dirname($script), 'main.py'));
    }

    public function test_allows_system_python_only_after_explicit_opt_in(): void
    {
        $script = $this->worker("print('{}')");
        $driver = new PythonDriver($this->runner);

        self::assertSame(['python3', $script], $driver->getRunCommand(dirname($script), 'main.py'));
    }

    public function test_retains_the_best_effort_pattern_check(): void
    {
        $script = $this->worker('# IMPORT_DANGER_TEST');
        $driver = new PythonDriver($this->runner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test rule trigger');
        $driver->getRunCommand(dirname($script), 'main.py');
    }

    public function test_applies_pip_timeout_when_installing_dependencies(): void
    {
        $this->worker('');
        $root = $this->directory.'/multiverse/python';
        mkdir($root.'/venv', 0700, true);
        file_put_contents($root.'/requirements.txt', '');
        $this->config->set('multiverse.pip_timeout', 23);
        $runner = $this->createMock(ProcessRunner::class);
        $runner->expects(self::once())->method('run')->with(
            [$root.'/venv/bin/pip', 'install', '-r', $root.'/requirements.txt'],
            $root, null, null, 23
        )->willReturn('');

        (new PythonDriver($runner))->installDependencies($root.'/example');
    }
}
