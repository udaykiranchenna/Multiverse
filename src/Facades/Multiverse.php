<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Facades;

use Illuminate\Support\Facades\Facade;
use MadeItEasyTools\Multiverse\WorkerManager;

/**
 * @method static array run(string $workerName, array $payload = [], array $options = [])
 * @method static \MadeItEasyTools\Multiverse\Contracts\LanguageDriver driver(string $driver)
 *
 * @see WorkerManager
 */
class Multiverse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkerManager::class;
    }
}
