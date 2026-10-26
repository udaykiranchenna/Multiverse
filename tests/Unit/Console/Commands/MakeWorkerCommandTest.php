<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Console\Commands;

use MadeItEasyTools\Multiverse\Console\Commands\MakeWorkerCommand;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;

class MakeWorkerCommandTest extends TestCase
{
    #[DataProvider('invalidArguments')]
    public function test_rejects_invalid_paths_before_creating_any_directories(string $name, string $lang): void
    {
        $command = new MakeWorkerCommand;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute(['name' => $name, '--lang' => $lang]));
        self::assertDirectoryDoesNotExist($this->directory.'/multiverse');
    }

    public static function invalidArguments(): array
    {
        return [['example', '../../outside'], ['example', 'node'], ['../outside', 'python'], ["example\n", 'python']];
    }

    public function test_does_not_write_through_a_language_directory_symlink_outside_workers(): void
    {
        mkdir($this->directory.'/multiverse', 0700, true);
        mkdir($this->directory.'/outside', 0700, true);
        symlink($this->directory.'/outside', $this->directory.'/multiverse/python');
        $command = new MakeWorkerCommand;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute(['name' => 'example', '--lang' => 'python']));
        self::assertDirectoryDoesNotExist($this->directory.'/outside/example');
    }

    public function test_creates_a_worker_inside_the_language_directory(): void
    {
        $command = new MakeWorkerCommand;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute(['name' => 'example', '--lang' => 'python']));
        self::assertFileExists($this->directory.'/multiverse/python/example/main.py');
    }
}
