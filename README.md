# MadeItEasyTools/Multiverse 🌌

**Run trusted Python workers from your Laravel application.**

Multiverse connects Laravel to Python for data processing, image handling, machine learning, PDF generation, and other Python workloads. Send JSON from PHP, execute your Python worker, and receive a JSON result.

> **Python-only direction:** Multiverse was originally planned to support multiple programming languages. We have decided to keep the package focused exclusively on Python. Support for other programming languages is no longer planned.

[![Latest Version](https://img.shields.io/packagist/v/madeiteasytools/multiverse.svg?style=flat-square)](https://packagist.org/packages/madeiteasytools/multiverse)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/udaykiranchenna2/Multiverse?style=flat-square)](LICENSE.md)

---

## ✨ Features

- **🚀 Performance Optimized**: Sub-500ms execution times via pre-installed environments
- **🛡️ Robust Error Handling**: Custom exceptions with safe failure metadata
- **⏱️ Configurable Timeouts**: Prevent hanging workers with flexible timeout options
- **📊 Automatic Logging**: Failed workers logged with metadata only
- **🧹 Process Management**: Manual cleanup command for zombie processes
- **🐍 Python Only**: A Laravel integration dedicated to Python workers
- **📦 Shared Dependencies**: One `requirements.txt` for all workers
- **🛠️ Artisan Integration**: `multiverse:worker`, `multiverse:install`, `multiverse:update`, `multiverse:clear`
- **🔒 Safer Execution**: Finite timeouts, bounded I/O, restricted environment inheritance, and advisory code checks

---

## 🖥️ Server Requirements

Before installing, ensure your server meets these requirements:

### PHP
- PHP 8.2+ and the PHP version required by your Laravel release
- `proc_open` must **not** be in `disable_functions` in `php.ini`

> **Shared hosting / HestiaCP / cPanel**: These panels often disable `proc_open` in PHP-FPM pools. You must remove it from `disable_functions` in your pool config or global `php.ini` for the package to work. See [Troubleshooting](#-troubleshooting).

### Python
A Python runtime and virtual environment support are required:

| Package | Purpose | Install (Ubuntu/Debian) |
|---|---|---|
| `python3` (3.8+) | Python runtime | pre-installed on most servers |
| `python3-venv` | Virtual environment support | `apt install python3.X-venv` |
| `python3-dev` | Python headers (for C extensions) | `apt install python3.X-dev` |
| `build-essential` | C/C++ compiler (`gcc`, `g++`) | `apt install build-essential` |

> Replace `X` with your Python version (e.g. `python3.12-venv` for Python 3.12).
> `python3-dev` and `build-essential` are only needed if your `requirements.txt` includes packages that compile from source (e.g. `pyswisseph`, `numpy`, `Pillow`).

**Quick check:**
```bash
python3 --version          # should be 3.8+
python3 -m venv --help     # should not error
gcc --version              # needed for C extensions
```

---

## 📦 Installation

```bash
composer require madeiteasytools/multiverse
```

### 1. Check Server Requirements

Before setting up, verify your server has everything needed:

```bash
php artisan multiverse:check --lang=python
```

Example output:

```
  Multiverse Requirements Check

  PHP
  ✓ PHP 8.4.0
  ✓ proc_open is available

  Python
  ✓ Python 3.12.3
  ✓ Version 3.12 meets 3.8+ requirement
  ✓ python3-venv available
  ✓ gcc 13.2.0
  ✓ g++ 13.2.0
  ✓ pip available in venv

  ✓ All requirements met. You are good to go!
```

Each failing check prints the exact `apt install` command to fix it. Returns exit code `1` if any requirement is missing — useful in CI/CD pipelines.

### 2. Setup Python Environment

```bash
php artisan multiverse:install --lang=python
```

This creates a `multiverse/` directory, sets up a virtual environment, and automatically updates your `.gitignore`.

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=multiverse-config
```

---

## 🚀 Quick Start

### Create a Worker

```bash
php artisan multiverse:worker image_processor --lang=python
```

### Write Your Python Logic

Edit `multiverse/python/image_processor/main.py`:

```python
import sys
import json

def main():
    # 1. Read Input
    data = json.loads(sys.stdin.read())

    # 2. Process Data
    result = {
        "status": "success",
        "processed": data['image_url']
    }

    # 3. Return Output
    print(json.dumps(result))

if __name__ == "__main__":
    main()
```

### Run from Laravel

```php
use MadeItEasyTools\Multiverse\Facades\Multiverse;

$result = Multiverse::run('image_processor', [
    'image_url' => 'https://example.com/image.jpg'
]);

// $result = ['status' => 'success', 'processed' => '...']
```

---

## Upgrading from v2.x to v3.0

Version 3.0 focuses exclusively on Python and includes breaking execution and failure-handling changes.

Move execution controls out of payload data:

```php
// v2.x
Multiverse::run('image_processor', [
    'image_url' => $imageUrl,
    'driver' => 'python',
    '_timeout' => 120,
]);

// v3.0
Multiverse::run('image_processor', ['image_url' => $imageUrl], [
    'driver' => 'python',
    'timeout' => 120,
]);
```

Before upgrading:

- Merge the new keys from `config/multiverse.php` into your application's published config. Use a finite positive `timeout` no greater than `max_timeout`; unlimited timeouts are rejected.
- Add required inherited variables to `process.env_allowlist` or pass specific trusted variables through `options.env`. Application secrets are excluded from subprocess environments by default.
- Check the input and combined output budgets. Both default to 10 MiB and must be positive integers.
- Create the shared Python virtual environment before running workers. System Python is used only when `python.allow_system_python` is explicitly enabled.
- Catch `WorkerException` for process failures and invalid or scalar JSON output. Do not rely on `raw_output` or raw stderr in exceptions; automatic logs contain metadata only.
- Keep scripts and symlink targets inside the configured workers directory.

Ordinary two-argument calls with data-only payloads remain valid. See the [API reference](API.md) for execution options and [changelog](CHANGELOG.md) for the release details.

---

## ⚙️ Advanced Features

### Timeout Configuration

The default timeout is **60 seconds**, with a configurable **300-second maximum**. Timeouts must be finite and positive; `null`, zero, negative values, and values above the maximum are rejected.

```php
// config/multiverse.php
'timeout' => 60,
'max_timeout' => 300,
```

Pass trusted execution options as the third argument:

```php
$result = Multiverse::run('worker_name', ['data' => 'value'], [
    'driver' => 'python',
    'timeout' => 120,
]);
```

Do not pass request data as execution options. The legacy `driver` and `_timeout` payload keys are rejected with a migration message. Use `driver` and `timeout` in the third argument instead.

### Error Handling

```php
use MadeItEasyTools\Multiverse\Exceptions\WorkerException;
use MadeItEasyTools\Multiverse\Exceptions\TimeoutException;

try {
    $result = Multiverse::run('risky_worker', $data);
} catch (TimeoutException $e) {
    // Worker exceeded timeout
    Log::error('Worker timed out', [
        'worker' => $e->getWorkerName(),
        'timeout' => $e->getMessage()
    ]);
} catch (WorkerException $e) {
    // Worker failed (exit code != 0)
    Log::error('Worker failed', [
        'worker' => $e->getWorkerName(),
        'exit_code' => $e->getExitCode(),
        'driver' => $e->getDriver()
    ]);
}
```

### Automatic Error Logging

Failed workers are logged to the configured channel using metadata only. Payloads, HTML, stdout, stderr, and raw process exception messages are excluded. `WorkerException::getErrorOutput()` returns `null` for failures produced by the manager. Invalid JSON or scalar JSON output throws `WorkerException`; raw output is never returned on these failures.

```php
// config/multiverse.php
'logging' => [
    'enabled' => true,
    'channel' => env('LOG_CHANNEL', 'stack'),
],
```

**Log Entry Example:**

```
[2026-02-08 12:00:00] local.ERROR: Multiverse worker failed
{
    "worker": "image_processor",
    "driver": "python",
    "exit_code": 1,
    "exception": "MadeItEasyTools\\Multiverse\\Exceptions\\WorkerException"
}
```

### Process Cleanup

Kill hanging or zombie worker processes:

```bash
# Clear all multiverse processes
php artisan multiverse:clear

# Clear specific worker
php artisan multiverse:clear worker_name
```

**Example:**

```bash
$ php artisan multiverse:clear test_worker
Searching for processes matching worker: test_worker
  Killed PID: 12345
✓ Killed 1 process(es)
```

---

## 📚 Managing Dependencies

### Add Python Packages

1. Edit `multiverse/python/requirements.txt`:

```txt
numpy==1.24.0
opencv-python-headless==4.8.0
requests==2.31.0
```

2. Update environment:

```bash
php artisan multiverse:update --lang=python
```

### Shared Virtual Environment

All Python workers share one virtual environment, saving disk space and installation time.

---

## 🔒 Security

Multiverse executes **trusted application code** with the operating-system permissions of the PHP process. It is not a sandbox. A Python virtual environment isolates dependencies, not filesystem access, networking, or OS permissions.

### Execution Limits and Environment

```php
'process' => [
    'max_input_bytes' => 10 * 1024 * 1024,
    'max_output_bytes' => 10 * 1024 * 1024, // Combined stdout and stderr.
    'env_allowlist' => [
        'PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP', 'TMPDIR', 'LANG', 'LC_ALL', 'LC_CTYPE',
    ],
],
```

Input is rejected before launch when oversized. A process that exceeds its output budget or timeout is stopped. Byte limits must be positive integers. These limits do not impose an OS memory limit or a global concurrency limit, and they do not sandbox child processes started by a worker.

Parent environment variables are removed unless allowlisted. Explicitly provide only the variables a trusted worker needs:

```php
$result = Multiverse::run('image_processor', $validatedData, [
    'env' => ['WORKER_API_TOKEN' => config('services.worker.token')],
]);
```

The shared virtual environment must exist. If deliberately using system Python, set `python.allow_system_python` to `true`; there is no automatic fallback. Dependency installation and updates use the finite `pip_timeout` setting. Pin and audit your Python requirements separately.

Worker names and driver names allow only 1 to 128 letters, numbers, underscores, or dashes. Resolved script paths must stay under `workers_path`, including symlink targets. Keep worker code, dependencies, and parent directories owned by the deployment user and unwritable by web users. Path checks cannot protect code that an attacker can modify concurrently.

### Advisory Pattern Checks

`security.scan_for_dangerous_code` retains the existing substring checks on the entry point. These checks can catch accidental use of listed patterns; they do not inspect imports, prevent obfuscated code, or make untrusted code safe. Do not treat them as a security boundary.

For untrusted workloads, use a separate service or OS/container sandbox with its own user, restricted mounts, network restrictions, resource limits, and process cleanup.

### Best Practices

✅ **Validate Input**: Always validate data before passing to workers  
✅ **Use Timeouts**: Set reasonable timeouts for all workers  
✅ **Monitor Logs**: Check `storage/logs` for worker failures  
✅ **Limit Permissions**: Run workers with minimal system permissions  
✅ **Sanitize Output**: Validate worker output before using in your app

---

## 🛠️ Artisan Commands

| Command                                | Description                     |
| -------------------------------------- | ------------------------------- |
| `multiverse:check --lang=python`       | Check server requirements       |
| `multiverse:install --lang=python`     | Setup Python environment      |
| `multiverse:update --lang=python`      | Update dependencies             |
| `multiverse:worker name --lang=python` | Create a Python worker               |
| `multiverse:run worker`                | Run a Python worker manually             |
| `multiverse:clear [worker]`            | Kill zombie processes           |

---

## 📖 Configuration Reference

```php
// config/multiverse.php
return [
    // Worker storage path
    'workers_path' => base_path('multiverse'),

    // Finite timeout and maximum, in seconds
    'timeout' => 60,
    'max_timeout' => 300,

    // Automatic error logging
    'logging' => [
        'enabled' => true,
        'channel' => env('LOG_CHANNEL', 'stack'),
    ],

    // pip install/upgrade timeout in seconds
    'pip_timeout' => 300,

    // Python configuration
    'python' => [
        'root_path' => 'multiverse/python',
        'venv_path' => 'multiverse/python/venv',
        'requirements_path' => 'multiverse/python/requirements.txt',
        'allow_system_python' => false,
    ],

    // Positive byte limits; env_allowlist controls inherited environment
    'process' => [
        'max_input_bytes' => 10 * 1024 * 1024,
        'max_output_bytes' => 10 * 1024 * 1024,
        'env_allowlist' => ['PATH', 'LANG', 'LC_ALL', 'TMPDIR'],
    ],

    // Advisory entry-point pattern checks
    'security' => [
        'scan_for_dangerous_code' => true,
        'dangerous_patterns' => [
            // Add your patterns here
        ],
    ],
];
```

---

## 🎯 Use Cases

### AI & Machine Learning

```php
// Run TensorFlow/PyTorch models
$prediction = Multiverse::run('ml_model', [
    'image' => base64_encode($imageData)
]);
```

### Image Processing

```php
// OpenCV operations
$processed = Multiverse::run('image_processor', [
    'path' => storage_path('images/photo.jpg'),
    'operation' => 'resize',
    'width' => 800
]);
```

### Data Science

```php
// Pandas/NumPy analysis
$analysis = Multiverse::run('data_analyzer', [
    'csv_path' => storage_path('data.csv'),
    'operation' => 'statistics'
]);
```

### Web Scraping

```php
// BeautifulSoup/Scrapy
$data = Multiverse::run('scraper', [
    'url' => 'https://example.com',
    'selector' => '.product-price'
]);
```

---

## 🐛 Troubleshooting

### proc_open Disabled

```
LogicException: The Process class relies on proc_open, which is not available on your PHP installation.
```

`proc_open` is disabled in your PHP configuration. This is common on shared hosting and control panels (HestiaCP, cPanel, Plesk).

**Fix — check what's disabled:**
```bash
php -r "echo ini_get('disable_functions');"
```

**Fix — global php.ini** (affects all sites):
```bash
# Remove proc_open from disable_functions in /etc/php/X.Y/fpm/php.ini
# Then restart PHP-FPM
systemctl restart php8.4-fpm
```

**Fix — per-site PHP-FPM pool** (safer, affects only your site):

Edit your pool config (e.g. `/etc/php/8.4/fpm/pool.d/yoursite.conf`) and add:
```ini
php_admin_value[disable_functions] = pcntl_fork,pcntl_exec,...,exec,system,passthru,shell_exec,popen
```
Omit `proc_open` from the list. Note: pool config can only add to the global list, not remove from it — so `proc_open` must also be absent from the global `php.ini`.

### python3-venv Not Installed

```
RuntimeException: The virtual environment was not created successfully because ensurepip is not available.
```

```bash
apt install python3.12-venv -y   # replace 12 with your Python version
rm -rf multiverse/python/venv
php artisan multiverse:install --lang=python
```

### C Extension Build Fails (Python.h / g++ not found)

```
fatal error: Python.h: No such file or directory
error: command 'x86_64-linux-gnu-g++' failed: No such file or directory
```

```bash
apt install python3.12-dev build-essential -y
rm -rf multiverse/python/venv
php artisan multiverse:install --lang=python
```

### Worker Not Found

```
RuntimeException: Worker not found: my_worker
```

**Solution**: Check that `multiverse/python/my_worker/main.py` exists.

### Timeout Issues

```
TimeoutException: Worker [my_worker] timed out after 60 seconds
```

**Solution**: Increase timeout or optimize worker code.

### Import Errors

```
ModuleNotFoundError: No module named 'numpy'
```

**Solution**: Add package to `requirements.txt` and run `multiverse:update`.

### Zombie Processes

```
Worker seems stuck and won't respond
```

**Solution**: Run `php artisan multiverse:clear worker_name`.

---

## 📄 License

MIT License - see [LICENSE.md](LICENSE.md) for details.

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/udaykiranchenna2/Multiverse/issues)
- **Email**: madeiteasytools@gmail.com

---

**Made with ❤️ by MadeItEasyTools**
