<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Providers;

use Illuminate\Support\ServiceProvider;
use MadeItEasyTools\Multiverse\Console\Commands\CheckRequirementsCommand;
use MadeItEasyTools\Multiverse\Console\Commands\ClearWorkerCommand;
use MadeItEasyTools\Multiverse\Console\Commands\InstallLanguageCommand;
use MadeItEasyTools\Multiverse\Console\Commands\MakeWorkerCommand;
use MadeItEasyTools\Multiverse\Console\Commands\RunWorkerCommand;
use MadeItEasyTools\Multiverse\Console\Commands\UpdateLanguageCommand;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use MadeItEasyTools\Multiverse\WorkerManager;

class MultiverseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/multiverse.php', 'multiverse');

        $this->app->singleton(WorkerManager::class, function ($app) {
            return new WorkerManager($app['config'], $app[ProcessRunner::class], $app);
        });

        $this->app->bind(ProcessRunner::class, function ($app) {
            return new ProcessRunner;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/multiverse.php' => config_path('multiverse.php'),
            ], 'multiverse-config');

            $this->commands([
                CheckRequirementsCommand::class,
                MakeWorkerCommand::class,
                RunWorkerCommand::class,
                InstallLanguageCommand::class,
                UpdateLanguageCommand::class,
                ClearWorkerCommand::class,
            ]);
        }
    }
}
