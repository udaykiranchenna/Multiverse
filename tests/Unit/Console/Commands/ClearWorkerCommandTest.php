<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Console\Commands;

use MadeItEasyTools\Multiverse\Console\Commands\ClearWorkerCommand;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ClearWorkerCommandTest extends TestCase
{
    private function command(): ClearWorkerCommand
    {
        $command = new class extends ClearWorkerCommand
        {
            public array $patterns = [];

            protected function findAndKillProcesses(string $pattern): int
            {
                $this->patterns[] = $pattern;

                return 0;
            }
        };
        $command->setLaravel($this->app);

        return $command;
    }

    public function test_rejects_worker_regex_injection_without_searching_or_killing_processes(): void
    {
        $command = $this->command();
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute(['worker' => 'example|.*']));
        self::assertSame([], $command->patterns);
    }

    public function test_escapes_configured_paths_and_matches_the_whole_worker_name(): void
    {
        $root = $this->directory.'/workers[trusted]';
        $this->config->set('multiverse.python.root_path', $root);
        $command = $this->command();
        $tester = new CommandTester($command);
        $tester->execute(['worker' => 'example']);

        self::assertCount(1, $command->patterns);
        $pattern = '~'.$command->patterns[0].'~';
        self::assertSame(1, preg_match($pattern, 'python '.$root.'/example/main.py'));
        self::assertSame(0, preg_match($pattern, 'python '.$root.'/example_other/main.py'));
        self::assertSame(0, preg_match($pattern, 'python '.$this->directory.'/workerst/example/main.py'));
        self::assertSame(0, preg_match($pattern, 'python /unrelated'.$root.'/example/main.py'));
    }
}
