<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;
use MadeItEasyTools\Multiverse\Tests\Support\RecordingLogger;
use MadeItEasyTools\Multiverse\WorkerManager;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $directory;

    protected Application $app;

    protected Repository $config;

    protected RecordingLogger $logger;

    protected ProcessRunner $runner;

    protected WorkerManager $manager;

    private ?Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/multiverse-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->previousContainer = Container::getInstance();
        $this->app = new Application($this->directory);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->config = new Repository(['multiverse' => require dirname(__DIR__).'/config/multiverse.php']);
        $this->app->instance('config', $this->config);
        $this->app->instance('files', new Filesystem);
        $this->logger = new RecordingLogger;
        Log::swap($this->logger);
        $this->runner = new ProcessRunner($this->config);
        $this->app->instance(ProcessRunner::class, $this->runner);
        $this->manager = new WorkerManager($this->config, $this->runner, $this->app);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    protected function worker(string $code, string $name = 'example', string $layout = 'language-directory'): string
    {
        $workers = $this->directory.'/multiverse';
        $path = match ($layout) {
            'language-directory' => $workers.'/python/'.$name.'/main.py',
            'flat-directory' => $workers.'/'.$name.'/main.py',
            'language-file' => $workers.'/python/'.$name.'.py',
            'flat-file' => $workers.'/'.$name.'.py',
        };
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $code);
        $this->config->set('multiverse.python.allow_system_python', true);

        return $path;
    }
}
