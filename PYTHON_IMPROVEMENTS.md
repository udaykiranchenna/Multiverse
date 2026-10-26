# Python Support Improvements - Implementation Summary

Multiverse is now focused exclusively on Python. The original plan to support multiple programming languages has been dropped. The features below describe the Python worker integration.

## ✅ Completed Features

### 1. Exception Handling

- **WorkerException**: Base exception class for all worker-related errors
  - Stores worker name, driver, and exit code; manager-generated failures omit raw stderr
  - Provides getter methods for debugging
- **TimeoutException**: Specific exception for timeout scenarios
  - Extends WorkerException
  - Automatically includes timeout duration in message

### 2. Flexible Timeout Configuration

- **Default**: 60 seconds, capped by `max_timeout` (300 seconds by default). Timeouts must be finite and positive.
- **Global Config**: Set in `config/multiverse.php`
- **Per-Call Override**: Pass trusted `timeout` in the third argument; legacy payload controls are rejected
  ```php
  Multiverse::run('video_render', [], ['timeout' => 300]); // 5 minutes
  ```

### 3. Automated Error Logging

- **Configurable**: Enable/disable via `multiverse.logging.enabled`
- **Channel Selection**: Use any Laravel log channel
- **Rich Context**: Logs include:
  - Worker name
  - Driver and exit code
  - Exception class

### 4. Manual Process Cleanup

- **Command**: `php artisan multiverse:clear {worker?}`
- **Usage**:

  ```bash
  # Clear specific worker
  php artisan multiverse:clear video_processor

  # Clear all multiverse workers
  php artisan multiverse:clear
  ```

- **How it works**: Uses `pgrep` to find processes, then `kill -9` to terminate

### 5. Improved ProcessRunner

- **Timeout Support**: Accepts optional timeout parameter
- **Safe Failures**: Excludes raw stdout/stderr from failure messages and logs
- **Exit Code Preservation**: Maintains process exit codes for debugging

## Configuration

Add to your `config/multiverse.php`:

```php
// Finite, positive timeouts
'timeout' => 60,
'max_timeout' => 300,

// Logging
'logging' => [
    'enabled' => true,
    'channel' => env('LOG_CHANNEL', 'stack'),
],
```

## Usage Examples

### Basic Worker Execution

```php
use MadeItEasyTools\Multiverse\Facades\Multiverse;
use MadeItEasyTools\Multiverse\Exceptions\WorkerException;
try {
    $result = Multiverse::run('image_processor', [
        'image_url' => 'https://example.com/image.jpg'
    ]);
} catch (WorkerException $e) {
    // Worker failed - check logs for details
    Log::error('Image processing failed', [
        'worker' => $e->getWorkerName(),
        'exit_code' => $e->getExitCode(),
    ]);
}
```

### With Custom Timeout

```php
// Long-running task within the configured maximum
$result = Multiverse::run('video_render', [
    'video_path' => '/path/to/video.mp4',
], ['timeout' => 300]);
```

### Cleanup Zombie Processes

```bash
# If a worker hangs, manually kill it
php artisan multiverse:clear video_render
```

## Files Modified/Created

### New Files

- `src/Exceptions/WorkerException.php`
- `src/Exceptions/TimeoutException.php`
- `src/Commands/ClearWorkerCommand.php`

### Modified Files

- `src/Process/ProcessRunner.php` - Added timeout support
- `src/WorkerManager.php` - Added error handling and logging
- `src/MultiverseServiceProvider.php` - Registered new command
- `config/multiverse.php` - Added timeout and logging config

## Next Steps

1. **Publish Updated Config**: Merge the new keys from `config/multiverse.php` into existing application configuration
2. **Test Error Scenarios**: Create a worker that intentionally fails to verify logging
3. **Test Timeout**: Create a worker with `time.sleep(10)` and pass `['timeout' => 2]` as the third argument
4. **Test Cleanup**: Run a long worker and use `multiverse:clear` to kill it
