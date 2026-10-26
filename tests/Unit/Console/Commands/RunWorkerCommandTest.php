<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Console\Commands;

use MadeItEasyTools\Multiverse\Console\Commands\RunWorkerCommand;
use MadeItEasyTools\Multiverse\Tests\TestCase;
use MadeItEasyTools\Multiverse\WorkerManager;
use Symfony\Component\Console\Tester\CommandTester;

class RunWorkerCommandTest extends TestCase
{
    public function test_passes_execution_options_separately_from_json_data(): void
    {
        $manager = $this->createMock(WorkerManager::class);
        $manager->expects(self::once())->method('run')->with('example', ['value' => 42], [
            'driver' => 'python', 'timeout' => 1.5,
        ])->willReturn(['status' => 'success']);
        $this->app->instance(WorkerManager::class, $manager);
        $command = new RunWorkerCommand;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute([
            'worker' => 'example', '--payload' => '{"value":42}', '--timeout' => '1.5',
        ]));
        self::assertStringContainsString('success', $tester->getDisplay());
    }

    public function test_does_not_echo_malformed_payload_in_errors(): void
    {
        $command = new RunWorkerCommand;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute(['worker' => 'example', '--payload' => 'private-invalid-json']));
        self::assertStringNotContainsString('private-invalid-json', $tester->getDisplay());
    }
}
