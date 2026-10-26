<?php

declare(strict_types=1);
use MadeItEasyTools\Multiverse\Drivers\PythonDriver;

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
    | Must be a finite, positive number no greater than max_timeout.
    |
    */
    'timeout' => 60,

    'max_timeout' => 300,

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
    | Python Driver
    |--------------------------------------------------------------------------
    |
    | Python execution driver. The config key is retained for compatibility.
    |
    */
    'drivers' => [
        'python' => PythonDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Configuration
    |--------------------------------------------------------------------------
    |
    | All workers share this virtual environment. A venv isolates dependencies;
    | it does not restrict filesystem access, network access, or OS permissions.
    |
    */
    'python' => [
        // Path relative to base_path, or absolute
        'root_path' => 'multiverse/python',
        'venv_path' => 'multiverse/python/venv',
        'requirements_path' => 'multiverse/python/requirements.txt',
        'allow_system_python' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Best-effort substring checks on the entry point only. This does not
    | inspect imports or make untrusted code safe to execute.
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
    | Process Limits
    |--------------------------------------------------------------------------
    |
    | Input and combined stdout/stderr limits are measured in bytes.
    | Only allowlisted parent environment variables reach the subprocess.
    |
    */
    'process' => [
        'max_input_bytes' => 10 * 1024 * 1024,
        'max_output_bytes' => 10 * 1024 * 1024,
        'env_allowlist' => [
            'PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Log worker metadata only. Payloads, stdout, and stderr are never logged.
    |
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],
];
