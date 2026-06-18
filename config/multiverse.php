<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Workers Path
    |--------------------------------------------------------------------------
    |
    | The directory where your worker scripts are located.
    |
    */
    'workers_path' => base_path('multiverse'),

    /*
    |--------------------------------------------------------------------------
    | Worker Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds a worker process may run before being killed.
    | Set to null for no limit (not recommended in production).
    |
    */
    'timeout' => 60,

    /*
    |--------------------------------------------------------------------------
    | Pip Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds pip install/upgrade may run before being killed.
    |
    */
    'pip_timeout' => 300,

    /*
    |--------------------------------------------------------------------------
    | Language Drivers
    |--------------------------------------------------------------------------
    |
    | supported language drivers and their implementation classes.
    |
    */
    'drivers' => [
        'python' => \MadeItEasyTools\Multiverse\Drivers\PythonDriver::class,
        // 'node' => \MadeItEasyTools\Multiverse\Drivers\NodeDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the python environment strategy.
    | 'venv_path': null (default) -> means isolated venv per worker.
    | 'venv_path': 'multiverse/python/venv' -> means shared venv.
    |
    */
    'python' => [
        // Path relative to base_path, or absolute
        'root_path' => 'multiverse/python',
        'venv_path' => 'multiverse/python/venv',
        'requirements_path' => 'multiverse/python/requirements.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Control the static analysis checks.
    |
    */
    'security' => [
        'scan_for_dangerous_code' => true,
        'dangerous_patterns' => [
            'rm -rf' => 'destructive deletion (rm -rf) detected',
            'shutil.rmtree' => 'directory deletion (shutil) detected',
            'mkfs' => 'disk formatting command detected',
            ':(){:|:&};:' => 'fork bomb detected',
            'dd if=/dev/zero' => 'disk wiping command detected',
            'IMPORT_DANGER_TEST' => 'test rule trigger', // Safe dummy rule for testing
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | Default timeout for worker execution in seconds.
    | Set to null for unlimited execution time.
    | Can be overridden per-worker by passing '_timeout' in the payload.
    |
    */
    'timeout' => null, // Unlimited by default

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic error logging for failed workers.
    |
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],
];
