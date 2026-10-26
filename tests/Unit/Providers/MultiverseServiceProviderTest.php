<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Unit\Providers;

use InvalidArgumentException;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use MadeItEasyTools\Multiverse\Providers\MultiverseServiceProvider;
use MadeItEasyTools\Multiverse\Tests\TestCase;

class MultiverseServiceProviderTest extends TestCase
{
    public function test_binds_the_runner_with_the_application_limits(): void
    {
        $this->app->forgetInstance(ProcessRunner::class);
        (new MultiverseServiceProvider($this->app))->register();
        $this->config->set('multiverse.process.max_input_bytes', 4);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('input exceeds');
        $this->app->make(ProcessRunner::class)->run([PHP_BINARY, '-v'], input: '12345');
    }
}
