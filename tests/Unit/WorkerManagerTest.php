<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit;

use InvalidArgumentException;
use MadeItEasyTools\Multiverse\Exceptions\TimeoutException;
use MadeItEasyTools\Multiverse\Exceptions\WorkerException;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class WorkerManagerTest extends TestCase
{
    #[DataProvider('layouts')]
    public function test_returns_json_without_interpreting_payload_as_shell_code(string $layout): void
    {
        $this->worker("import json, sys\nprint(json.dumps(json.load(sys.stdin)))", layout: $layout);
        $data = ['message' => '$(touch sentinel); echo injected', 'name' => "quoted ' input", 'timeout' => 0];

        self::assertSame($data, $this->manager->run('example', $data));
        self::assertFileDoesNotExist($this->directory.'/sentinel');
    }

    public static function layouts(): array
    {
        return array_map(fn (string $layout): array => [$layout], ['language-directory', 'flat-directory', 'language-file', 'flat-file']);
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_worker_names_before_launch(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->run($name);
    }

    public static function invalidNames(): array
    {
        return [['../outside'], ['example/main.py'], ["example\n"], [''], [str_repeat('a', 129)], ['example;id']];
    }

    #[DataProvider('legacyControls')]
    public function test_rejects_execution_controls_in_payload(array $payload): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('third argument');
        $this->manager->run('example', $payload);
    }

    public static function legacyControls(): array
    {
        return [[['_timeout' => 0]], [['_timeout' => null]], [['driver' => 'python']]];
    }

    #[DataProvider('invalidTimeouts')]
    public function test_rejects_timeouts_that_disable_or_exceed_the_limit(mixed $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->run('example', [], ['timeout' => $timeout]);
    }

    public static function invalidTimeouts(): array
    {
        return [[0], [-1], [null], [INF], [NAN], [301], ['60'], [false], [[]]];
    }

    public function test_rejects_unlimited_default_configuration(): void
    {
        $this->config->set('multiverse.timeout', null);
        $this->expectException(InvalidArgumentException::class);
        $this->manager->run('example');
    }

    public function test_applies_default_timeout_and_logs_only_metadata(): void
    {
        $this->worker("import time\ntime.sleep(10)");
        $this->config->set('multiverse.timeout', 0.2);

        try {
            $this->manager->run('example', ['password' => 'private-customer-data']);
            self::fail('The worker must time out.');
        } catch (TimeoutException $exception) {
            self::assertSame(124, $exception->getExitCode());
            self::assertStringContainsString('0.2 seconds', $exception->getMessage());
        }

        self::assertSame([[
            'level' => 'error',
            'message' => 'Multiverse worker failed',
            'context' => [
                'worker' => 'example',
                'driver' => 'python',
                'exit_code' => 124,
                'exception' => TimeoutException::class,
            ],
        ]], $this->logger->records);
    }

    public function test_separates_trusted_options_from_stdin_data(): void
    {
        $this->worker("import json, sys, os\nprint(json.dumps({'data': json.load(sys.stdin), 'explicit': os.getenv('MULTIVERSE_EXPLICIT')}))");

        self::assertSame(['data' => ['value' => 42], 'explicit' => 'allowed'], $this->manager->run('example', ['value' => 42], [
            'driver' => 'python', 'timeout' => 2, 'env' => ['MULTIVERSE_EXPLICIT' => 'allowed'],
        ]));
    }

    public function test_rejects_a_driver_name_that_addresses_nested_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->driver('python.class');
    }

    public function test_rejects_configured_classes_that_do_not_implement_language_driver(): void
    {
        $this->config->set('multiverse.drivers.invalid', \stdClass::class);
        $this->expectException(InvalidArgumentException::class);
        $this->manager->driver('invalid');
    }

    public function test_rejects_unknown_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->run('example', [], ['_timeout' => 10]);
    }

    public function test_rejects_a_script_symlink_outside_the_workers_directory(): void
    {
        $path = $this->worker('');
        $outside = $this->directory.'/outside.py';
        file_put_contents($outside, "print('{}')");
        unlink($path);
        symlink($outside, $path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('inside the configured workers directory');
        $this->manager->run('example');
    }

    public function test_preserves_failure_exit_code_without_exposing_payload_or_output(): void
    {
        $this->worker("import sys\nprint('private-stdout')\nprint('private-stderr', file=sys.stderr)\nsys.exit(7)");

        try {
            $this->manager->run('example', ['html' => 'private-customer-html']);
            self::fail('A failing worker must throw.');
        } catch (WorkerException $exception) {
            self::assertSame(7, $exception->getExitCode());
            self::assertNull($exception->getErrorOutput());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('private-', $exception->getMessage());
        }

        self::assertCount(1, $this->logger->records);
        self::assertStringNotContainsString('private-', json_encode($this->logger->records, JSON_THROW_ON_ERROR));
    }

    public function test_respects_disabled_error_logging(): void
    {
        $this->worker('raise RuntimeError("private-error")');
        $this->config->set('multiverse.logging.enabled', false);

        try {
            $this->manager->run('example');
            self::fail('The worker must fail.');
        } catch (WorkerException) {
            self::assertSame([], $this->logger->records);
        }
    }

    #[DataProvider('invalidOutput')]
    public function test_throws_for_invalid_output_without_returning_raw_data(string $code): void
    {
        $this->worker($code);
        $this->expectException(WorkerException::class);
        $this->manager->run('example');
    }

    public static function invalidOutput(): array
    {
        return [["print('private-invalid-output')"], ["print('null')"], ["print('42')"]];
    }
}
