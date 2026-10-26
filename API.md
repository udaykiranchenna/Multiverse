# API Reference

Multiverse runs trusted Python workers from Laravel. The project originally planned support for multiple programming languages; it now focuses exclusively on Python, and other language runtimes are no longer on the roadmap.

## Table of Contents

- [WorkerManager](#workermanager)
- [Facades](#facades)
- [Commands](#commands)
- [Configuration](#configuration)
- [Drivers](#drivers)

---

## WorkerManager

The core class for executing Python workers.

### Methods

#### `run(string $workerName, array $payload = [], array $options = []): array`

Execute a worker with the given payload.

**Parameters:**

- `$workerName` (string): Name of the worker (alphanumeric, dashes, underscores only)
- `$payload` (array): Associative array of data to pass to stdin. Legacy `driver` and `_timeout` control keys are rejected.
- `$options` (array): Trusted `driver`, `timeout`, and `env` options. Never populate this argument from request data.

**Returns:**

- `array`: The worker's JSON object or array. Invalid output throws; it is not returned as `raw_output`.

**Throws:**

- `InvalidArgumentException`: Invalid names, options, timeouts, environment entries, or oversized input
- `WorkerException`: Process failures, output limits, invalid JSON, or scalar JSON output
- `TimeoutException`: The worker exceeded its finite timeout
- `RuntimeException`: If worker not found or execution fails

**Example:**

```php
use MadeItEasyTools\Multiverse\WorkerManager;

$manager = app(WorkerManager::class);

$result = $manager->run('image_processor', [
    'image_url' => 'https://example.com/image.jpg',
    'filter' => 'blur'
]);

if ($result['status'] === 'success') {
    echo "Output: " . $result['output_path'];
}
```

#### `driver(string $driver): LanguageDriver`

Get or create the Python execution driver. The `LanguageDriver` interface name is retained for compatibility; it does not imply support for other runtimes.

**Parameters:**

- `$driver` (string): Use `'python'`, the supported driver.

**Returns:**

- `LanguageDriver`: The driver instance

**Throws:**

- `InvalidArgumentException`: If driver not configured

---

## Facades

### Multiverse

Convenient facade for accessing WorkerManager.

**Example:**

```php
use MadeItEasyTools\Multiverse\Facades\Multiverse;

// Simple usage
$result = Multiverse::run('echo_worker', ['message' => 'Hello']);

// With error handling
try {
    $result = Multiverse::run('complex_worker', $data);
} catch (\Exception $e) {
    Log::error('Worker failed: ' . $e->getMessage());
}
```

---

## Commands

### multiverse:install

Set up the shared Python virtual environment.

**Signature:**

```bash
php artisan multiverse:install --lang=python
```

**Options:**

- `--lang=python`: Required; Python is the only supported runtime.

**What it does:**

1. Creates the Python directory structure
2. Sets up virtual environment
3. Creates requirements file
4. Installs base dependencies

**Example:**

```bash
php artisan multiverse:install --lang=python
```

---

### multiverse:update

Install dependencies from the shared Python requirements file.

**Signature:**

```bash
php artisan multiverse:update --lang=python
```

**Options:**

- `--lang=python`: Required; Python is the only supported runtime.

**What it does:**

1. Reads requirements file
2. Installs/updates packages in virtual environment

**Example:**

```bash
# After adding packages to multiverse/python/requirements.txt
php artisan multiverse:update --lang=python
```

---

### multiverse:worker

Create a new Python worker.

**Signature:**

```bash
php artisan multiverse:worker {name} --lang=python
```

**Arguments:**

- `name`: Worker name (optional; prompts if omitted)

**Options:**

- `--lang=python`: Python is the only supported runtime; prompts if omitted.

**What it does:**

1. Creates worker directory
2. Generates main script file
3. Creates .gitignore

**Example:**

```bash
php artisan multiverse:worker image_processor --lang=python
```

Creates:

```
multiverse/
└── python/
    └── image_processor/
        ├── main.py
        └── .gitignore
```

---

### multiverse:run

Execute a Python worker manually.

**Signature:**

```bash
php artisan multiverse:run {worker} --payload=JSON
```

**Arguments:**

- `worker`: Worker name (required)

**Options:**

- `--payload=JSON`: JSON payload to pass (optional, defaults to `{}`)
- `--lang=python`: Python execution driver (defaults to `python`)
- `--timeout=SECONDS`: Finite positive timeout, capped by `max_timeout`

**Example:**

```bash
php artisan multiverse:run image_processor --payload='{"image_url": "https://example.com/img.jpg"}'
```

---

## Configuration

### config/multiverse.php

#### `workers_path`

**Type:** `string`  
**Default:** `base_path('multiverse')`

Root directory for all workers.

```php
'workers_path' => base_path('multiverse'),
```

---

#### `drivers`

**Type:** `array<string, string>`  
**Default:** `['python' => PythonDriver::class]`

The Python execution driver registration. The `drivers` key is retained for compatibility; Python is the package's only supported runtime.

```php
'drivers' => [
    'python' => \MadeItEasyTools\Multiverse\Drivers\PythonDriver::class,
],
```

---

#### `python`

**Type:** `array`

Python-specific configuration.

```php
'python' => [
    'root_path' => 'multiverse/python',
    'venv_path' => 'multiverse/python/venv',
    'requirements_path' => 'multiverse/python/requirements.txt',
],
```

**Keys:**

- `root_path`: Python workers directory
- `venv_path`: Virtual environment location
- `requirements_path`: Requirements file location
- `allow_system_python`: Explicitly allow system Python when the venv is unavailable (default `false`).

---

#### Execution limits and logging

- `timeout`: Finite positive worker timeout, default 60 seconds.
- `max_timeout`: Maximum timeout callers can request, default 300 seconds.
- `pip_timeout`: Finite positive dependency installation/update timeout, default 300 seconds.
- `process.max_input_bytes`: Positive stdin size limit, default 10 MiB.
- `process.max_output_bytes`: Positive combined stdout/stderr size limit, default 10 MiB.
- `process.env_allowlist`: Parent environment variable names to inherit. Other variables are removed. Trusted `options.env` values override this environment.
- `logging.enabled` and `logging.channel`: Control metadata-only failure logging. Payloads and process output are excluded; manager-generated exception messages are sanitized and `getErrorOutput()` is `null`.

Scripts must resolve inside `workers_path`. Workers retain the PHP user's OS permissions; use external sandboxing for untrusted workloads. These limits do not enforce OS memory or global concurrency limits.

#### `security`

**Type:** `array`

Best-effort entry-point substring checks. This does not inspect imports or sandbox worker code.

```php
'security' => [
    'scan_for_dangerous_code' => true,
    'dangerous_patterns' => [
        'rm -rf' => 'destructive deletion detected',
        'shutil.rmtree' => 'directory deletion detected',
    ],
],
```

**Keys:**

- `scan_for_dangerous_code` (bool): Enable/disable the advisory substring check
- `dangerous_patterns` (array): Map of patterns to error messages

**Adding custom patterns:**

```php
'dangerous_patterns' => [
    'rm -rf' => 'destructive deletion detected',
    'eval(' => 'eval usage detected',
    'import socket' => 'network access detected',
],
```

---

## Drivers

### LanguageDriver Interface

The Python driver implements this internal execution contract. Its name is retained from the original multi-language design; adding other language runtimes is outside the package scope.

```php
namespace MadeItEasyTools\Multiverse\Contracts;

interface LanguageDriver
{
    /**
     * Install dependencies for a worker.
     */
    public function installDependencies(string $workerPath): void;

    /** Return the Python entry-point filename, main.py. */
    public function defaultScriptName(): string;

    /**
     * Get the command to run a worker script.
     */
    public function getRunCommand(string $workerPath, string $scriptName): array;
}
```

---

### PythonDriver

Driver for executing Python workers.

#### `installDependencies(string $workerPath): void`

Installs Python packages from requirements.txt into the shared virtual environment.

**Parameters:**

- `$workerPath` (string): Path to worker directory (not used, uses shared venv)

**Throws:**

- `RuntimeException`: If venv not found or installation fails

---

#### `getRunCommand(string $workerPath, string $scriptName): array`

Builds the command to execute a Python script.

**Parameters:**

- `$workerPath` (string): Path to worker directory
- `$scriptName` (string): Script filename (usually 'main.py')

**Returns:**

- `array`: Command array for Symfony Process

**Security:**

- Scans file for dangerous patterns before execution
- Throws `RuntimeException` if dangerous code detected

**Example return:**

```php
[
    '/path/to/multiverse/python/venv/bin/python',
    '/path/to/multiverse/python/my_worker/main.py'
]
```

---

## Worker Contract

### Input

Workers receive JSON via stdin:

```python
import sys
import json

data = json.loads(sys.stdin.read())
# data is now a Python dict
```

### Output

Workers must output JSON to stdout:

```python
import json

result = {
    "status": "success",
    "message": "Processing complete",
    "data": {
        "output_path": "/tmp/result.jpg"
    }
}

print(json.dumps(result))
```

### Standard Response Format

**Success:**

```json
{
    "status": "success",
    "message": "Optional success message",
    "data": {
        "key": "value"
    }
}
```

**Error:**

```json
{
    "status": "error",
    "message": "Error description"
}
```

---

## Error Handling

### Common Exceptions

#### `InvalidArgumentException`

Thrown when:

- Worker name contains invalid characters
- Driver not configured

**Example:**

```php
try {
    Multiverse::run('../../../etc/passwd', []);
} catch (InvalidArgumentException $e) {
    // "Invalid worker name"
}
```

---

#### `RuntimeException`

Thrown when:

- Worker not found
- Worker execution fails
- Security violation detected
- Python venv not found

**Example:**

```php
try {
    Multiverse::run('nonexistent_worker', []);
} catch (RuntimeException $e) {
    // "Worker not found: nonexistent_worker"
}
```

---

## Events

Currently, the package does not fire Laravel events. This may be added in future versions.

**Potential future events:**

- `WorkerExecuting`
- `WorkerExecuted`
- `WorkerFailed`

---

## Testing

### Unit Testing Workers

```php
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    public function test_it_processes_images()
    {
        $result = Multiverse::run('image_processor', [
            'image_url' => 'https://example.com/test.jpg',
            'filter' => 'blur'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('output_path', $result['data']);
    }
}
```

### Mocking Workers

```php
use MadeItEasyTools\Multiverse\WorkerManager;
use Mockery;

public function test_with_mocked_worker()
{
    $mock = Mockery::mock(WorkerManager::class);
    $mock->shouldReceive('run')
        ->with('image_processor', Mockery::any())
        ->andReturn(['status' => 'success']);

    $this->app->instance(WorkerManager::class, $mock);

    // Your test code...
}
```

---

## Performance

### Benchmarks

Typical execution times:

| Operation                  | Time        |
| -------------------------- | ----------- |
| Simple echo worker         | ~20ms       |
| Image processing (Pillow)  | ~200-500ms  |
| Face detection (OpenCV)    | ~500-1500ms |
| ML inference (small model) | ~1-3s       |

### Optimization Tips

1. **Avoid repeated installs**: Dependencies are only installed once
2. **Use shared venv**: All workers share one environment
3. **Cache results**: Store frequently used outputs in Redis
4. **Queue heavy tasks**: Use Laravel queues for long-running workers
5. **Batch processing**: Process multiple items in one worker call

---

## Version History

### v1.0.0

- Initial release
- Python support
- Security scanning
- Shared virtual environments
- Artisan commands

### Python Worker Roadmap
- Worker events
- Performance monitoring
- Worker versioning
