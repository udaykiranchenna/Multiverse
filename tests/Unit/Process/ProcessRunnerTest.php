<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Process;

use InvalidArgumentException;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class ProcessRunnerTest extends TestCase
{
    public function test_excludes_parent_secrets_unless_explicitly_allowed(): void
    {
        $name = 'MULTIVERSE_TEST_SECRET';
        $previous = getenv($name);
        putenv($name.'=private-environment-value');
        $_ENV[$name] = 'private-environment-value';
        $_SERVER[$name] = 'private-environment-value';
        $command = [PHP_BINARY, '-r', 'echo json_encode(getenv("MULTIVERSE_TEST_SECRET"));'];

        try {
            self::assertSame('false', $this->runner->run($command));
            $this->config->set('multiverse.process.env_allowlist', [$name]);
            self::assertSame('"private-environment-value"', $this->runner->run($command));
            self::assertSame('false', $this->runner->run($command, env: [$name => false]));
        } finally {
            putenv($previous === false ? $name : $name.'='.$previous);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function test_returns_stdout_and_excludes_stderr_on_success(): void
    {
        self::assertSame('result', $this->runner->run([
            PHP_BINARY, '-r', 'fwrite(STDERR, "private-diagnostic"); echo "result";',
        ]));
        self::assertSame([], $this->logger->records);
    }

    public function test_rejects_oversized_input_before_starting_the_process(): void
    {
        $this->config->set('multiverse.process.max_input_bytes', 4);
        $marker = $this->directory.'/started';

        try {
            $this->runner->run([PHP_BINARY, '-r', 'touch($argv[1]);', $marker], input: '12345');
            self::fail('Oversized input must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertFileDoesNotExist($marker);
        }
    }

    public function test_accepts_input_and_output_at_the_exact_byte_limit(): void
    {
        $this->config->set('multiverse.process.max_input_bytes', 4);
        $this->config->set('multiverse.process.max_output_bytes', 4);

        self::assertSame('1234', $this->runner->run([PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'], input: '1234'));
    }

    #[DataProvider('overflowOutput')]
    public function test_limits_combined_stdout_and_stderr_and_stops_the_process(string $code): void
    {
        $this->config->set('multiverse.process.max_output_bytes', 100);
        $pidFile = $this->directory.'/pid';

        try {
            $this->runner->run([PHP_BINARY, '-r', 'file_put_contents($argv[1], (string) getmypid()); '.$code.' sleep(10);', $pidFile]);
            self::fail('Excessive output must stop the process.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('output exceeds', $exception->getMessage());
            $pid = (int) file_get_contents($pidFile);
            if (function_exists('posix_kill')) {
                self::assertFalse(posix_kill($pid, 0), 'The over-limit child process must have exited.');
            }
        }
    }

    public static function overflowOutput(): array
    {
        return [
            ['echo str_repeat("x", 101);'],
            ['fwrite(STDERR, str_repeat("x", 101));'],
            ['echo str_repeat("x", 60); fwrite(STDERR, str_repeat("y", 60));'],
        ];
    }

    #[DataProvider('invalidTimeouts')]
    public function test_rejects_nonpositive_and_nonfinite_process_timeouts(float $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->runner->run([PHP_BINARY, '-v'], timeout: $timeout);
    }

    public static function invalidTimeouts(): array
    {
        return [[0.0], [-1.0], [INF], [NAN]];
    }

    public function test_uses_a_finite_timeout_for_direct_runner_calls(): void
    {
        $this->config->set('multiverse.timeout', 0.2);
        $this->expectException(ProcessTimedOutException::class);
        $this->runner->run([PHP_BINARY, '-r', 'sleep(10);']);
    }

    public function test_rejects_unbounded_byte_limits(): void
    {
        $this->config->set('multiverse.process.max_output_bytes', 0);
        $this->expectException(InvalidArgumentException::class);
        $this->runner->run([PHP_BINARY, '-v']);
    }

    public function test_does_not_log_or_expose_failed_process_output(): void
    {
        try {
            $this->runner->run([PHP_BINARY, '-r', 'echo "private-stdout"; fwrite(STDERR, "private-stderr"); exit(9);']);
            self::fail('The process must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame(9, $exception->getCode());
            self::assertStringNotContainsString('private-', $exception->getMessage());
            self::assertSame([], $this->logger->records);
        }
    }

    #[DataProvider('invalidEnvironments')]
    public function test_rejects_malformed_environment_overrides(array $environment): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->runner->run([PHP_BINARY, '-v'], env: $environment);
    }

    public static function invalidEnvironments(): array
    {
        return [[['BAD=NAME' => 'x']], [['VALID_NAME' => []]], [['VALID_NAME' => true]]];
    }
}
