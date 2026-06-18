# MadeItEasyTools/Multiverse 🌌

**Run Python, Node.js, and other languages natively inside your Laravel application.**

Bridge the gap between PHP's web dominance and Python's data supremacy. Run "Workers" written in other languages as if they were native Laravel classes.

[![Latest Version](https://img.shields.io/packagist/v/madeiteasytools/multiverse.svg?style=flat-square)](https://packagist.org/packages/madeiteasytools/multiverse)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/github/license/udaykiranchenna2/Multiverse?style=flat-square)](LICENSE.md)

---

## ✨ Features

- **🚀 Performance Optimized**: Sub-500ms execution times via pre-installed environments
- **🛡️ Robust Error Handling**: Custom exceptions with detailed context
- **⏱️ Configurable Timeouts**: Prevent hanging workers with flexible timeout options
- **📊 Automatic Logging**: Failed workers logged with full context
- **🧹 Process Management**: Manual cleanup command for zombie processes
- **🐍 Python Native**: First-class support for Python 3.x
- **📦 Shared Dependencies**: One `requirements.txt` for all workers
- **🛠️ Artisan Integration**: `multiverse:worker`, `multiverse:install`, `multiverse:update`, `multiverse:clear`
- **🔒 Security**: Built-in static analysis to block dangerous commands

---

## 🖥️ Server Requirements

Before installing, ensure your server meets these requirements:

### PHP
- PHP 8.1+
- `proc_open` must **not** be in `disable_functions` in `php.ini`

> **Shared hosting / HestiaCP / cPanel**: These panels often disable `proc_open` in PHP-FPM pools. You must remove it from `disable_functions` in your pool config or global `php.ini` for the package to work. See [Troubleshooting](#-troubleshooting).

### Python Workers
If you plan to use Python workers, the server also needs:

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

### 1. Setup Python Environment

```bash
php artisan multiverse:install --lang=python
```

This creates a `multiverse/` directory, sets up a virtual environment, and automatically updates your `.gitignore`.

### 2. Publish Configuration (Optional)

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

## ⚙️ Advanced Features

### Timeout Configuration

**Default (Unlimited):**

```php
// Workers run indefinitely by default
$result = Multiverse::run('long_task', $data);
```

**Global Timeout:**

```php
// config/multiverse.php
'timeout' => 300, // 5 minutes for all workers
```

**Per-Worker Timeout:**

```php
// Override timeout for specific execution
$result = Multiverse::run('worker_name', [
    'data' => 'value',
    '_timeout' => 60  // 1 minute timeout
]);
```

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
        'error' => $e->getErrorOutput()
    ]);
}
```

### Automatic Error Logging

Failed workers are automatically logged to `storage/logs/laravel.log`:

```php
// config/multiverse.php
'logging' => [
    'enabled' => true,
    'channel' => env('LOG_CHANNEL', 'stack'),
],
```

**Log Entry Example:**

```
[2026-02-08 12:00:00] local.ERROR: Multiverse Worker Failed: image_processor
{
    "worker": "image_processor",
    "driver": "python",
    "input": {"image_url": "..."},
    "error": "ValueError: Invalid image format",
    "exception": "MadeItEasyTools\\Multiverse\\Exceptions\\WorkerException",
    "stderr": "Traceback (most recent call last)..."
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

### Static Code Analysis

Block dangerous patterns in worker code:

```php
// config/multiverse.php
'security' => [
    'scan_for_dangerous_code' => true,
    'dangerous_patterns' => [
        'rm -rf' => 'destructive deletion detected',
        'mkfs' => 'formatting command detected',
        'eval(' => 'code execution detected',
    ],
],
```

### Best Practices

✅ **Validate Input**: Always validate data before passing to workers  
✅ **Use Timeouts**: Set reasonable timeouts for all workers  
✅ **Monitor Logs**: Check `storage/logs` for worker failures  
✅ **Limit Permissions**: Run workers with minimal system permissions  
✅ **Sanitize Output**: Validate worker output before using in your app

---

## 🛠️ Artisan Commands

| Command                                | Description                |
| -------------------------------------- | -------------------------- |
| `multiverse:install --lang=python`     | Setup language environment |
| `multiverse:update --lang=python`      | Update dependencies        |
| `multiverse:worker name --lang=python` | Create new worker          |
| `multiverse:run worker`                | Run worker manually        |
| `multiverse:clear [worker]`            | Kill zombie processes      |

---

## 📖 Configuration Reference

```php
// config/multiverse.php
return [
    // Worker storage path
    'workers_path' => base_path('multiverse'),

    // Default timeout (null = unlimited)
    'timeout' => null,

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
    ],

    // Security settings
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
