# Changelog

All notable changes to `madeiteasytools/multiverse` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Worker events (WorkerExecuting, WorkerExecuted, WorkerFailed)
- Performance monitoring dashboard
- Worker versioning system
- Async worker execution
- Worker output streaming

---

## [3.0.0] - 2026-10-26

### Security

- Enforce a finite 60-second default timeout and a configurable 300-second maximum; remove the duplicate unlimited default.
- Move trusted driver, timeout, and environment options to the third `run()` argument. Reject legacy `driver` and `_timeout` payload keys with migration guidance.
- Limit stdin and combined stdout/stderr to configurable positive byte budgets (10 MiB each by default); stop the process on output overflow.
- Inherit only allowlisted parent environment variables; permit explicit trusted overrides.
- Log failure metadata without payloads, rendered HTML, command output, or raw exception messages. Invalid or scalar JSON now throws instead of returning raw output.
- Validate worker/driver names strictly and reject script paths and symlink targets outside the configured workers directory.
- Require the configured Python venv unless system Python is explicitly enabled; apply pip timeouts consistently.
- Clarify that entry-point substring checks and Python venvs are not sandboxes. OS memory, concurrency, filesystem, network, and descendant-process restrictions require deployment controls.

### Changed

- Focus Multiverse exclusively on Python. The original multi-language plan has been dropped; other language runtimes are no longer planned. Update package documentation and descriptions to match.

- This major release includes the breaking API changes above. Existing two-argument calls with ordinary payloads continue to work.
- Reject unsupported/path-like generator languages before writes; escape cleanup patterns and validate worker names before searching processes.
- `multiverse:run` accepts `--lang` and `--timeout` as separate controls and returns failure for invalid input or execution errors.
- Preserve the package's PHPUnit regression suite in Git and use local Composer test binaries.

---

## [2.0.0] - 2026-06-18

### Added

- Laravel 13 support (`illuminate/support: ^13.0`)
- Symfony Process 8.x support (`symfony/process: ^8.0`)
- `defaultScriptName(): string` method added to `LanguageDriver` interface — drivers now own their default script filename
- `PythonDriver` implements `defaultScriptName()` returning `'main.py'`
- `WorkerManager` reads default script name from the resolved driver instead of hardcoding `'main.py'`
- `config('multiverse.timeout')` key (default `60` seconds) — applied to every worker execution via `ProcessRunner`
- `config('multiverse.pip_timeout')` key (default `300` seconds) — applied to pip install/upgrade calls
- `Log::error()` in `ProcessRunner` captures command, exit code, and stderr on every process failure
- `$container` injected into `WorkerManager`; `app()` global replaced with `$this->container->make()`

### Fixed

- **Critical** — Worker execution had no timeout; a hung Python script would block PHP-FPM indefinitely
- **Critical** — Non-JSON worker output (tracebacks, print statements) was returned with `status: 'success'`; now returns `status: 'error'`
- **Critical** — `json_encode($payload)` silently passed `false` to the worker on unserializable payloads (INF, NAN, resources); now throws `JsonException`
- **Warning** — `driver` key was serialized into the worker's stdin payload, leaking routing internals to the script; it is now stripped before encoding
- **Warning** — `make:worker` prompt validated name length only; path traversal names like `../../etc/passwd` were accepted; regex `/^[a-zA-Z0-9_-]+$/` now enforced on both the interactive prompt and the CLI argument
- `make:worker` — creating a worker for an unsupported language no longer leaves an empty directory behind
- `make:worker` — misnamed variable `$workersCount` corrected to `$workersPath`
- `multiverse:install` — `.gitignore` was unconditionally overwritten on re-runs, destroying custom entries; now append-only (missing lines are added, existing lines preserved)
- `multiverse:install` / `multiverse:update` — `base_path()` was applied twice to already-absolute config paths; `$toAbsolute` guard added
- `WorkerManager` — flat-file fallback extension derived from `pathinfo($scriptName, PATHINFO_EXTENSION)` instead of hardcoded `.py`
- `WorkerManager` — `json_decode` now uses `JSON_THROW_ON_ERROR` with `try/catch`, consistent with encode
- `RunWorkerCommand` — `json_last_error()` check replaced with `JSON_THROW_ON_ERROR` + `try/catch`
- `MultiWorker` facade — `@method` and `@see` PHPDoc corrected from stale `MultiLanguage` namespace to `Multiverse`

### Breaking Changes

- `LanguageDriver` interface now requires `defaultScriptName(): string` — any custom driver must implement this method
- `WorkerManager` constructor now requires a `Container` argument as its third parameter

### Upgrade Guide from 1.0.0

1. Update `composer.json`: `"madeiteasytools/multiverse": "^2.0"`
2. If you have a custom `LanguageDriver` implementation, add `public function defaultScriptName(): string` returning the default entry-point filename (e.g. `'main.py'`)
3. Publish and review the updated config: `php artisan vendor:publish --tag=multiverse-config`
   - New key `timeout` (default `60`) — set to `null` to disable (not recommended in production)
   - New key `pip_timeout` (default `300`) — time allowed for pip install/upgrade

---

## [1.0.0] - 2026-02-08

### Added

- Initial release of MadeItEasyTools/Multiverse
- Python 3.8+ support with shared virtual environments
- Core `WorkerManager` class for executing workers
- `Multiverse` facade for convenient access
- Security scanning with configurable dangerous pattern detection
- Path traversal protection for worker names
- Artisan commands:
  - `multiverse:install` - Setup language environments
  - `multiverse:update` - Update language dependencies
  - `multiverse:worker` - Generate new worker files
  - `multiverse:run` - Execute workers manually
  - `multiverse:clear` - Clean up zombie processes
- Configuration file (`config/multiverse.php`) with:
  - Customizable workers directory path
  - Language driver mappings
  - Python-specific settings (venv path, requirements path)
  - Security settings (scan toggle, dangerous patterns)
- `PythonDriver` with:
  - Shared virtual environment support
  - Automatic dependency management
  - Static code analysis before execution
- Comprehensive documentation:
  - README.md - Quick start guide
  - DOCUMENTATION.md - Complete user guide
  - API.md - API reference
  - EXAMPLES.md - Real-world examples
  - CHANGELOG.md - Version history
- PHPDoc documentation for all public methods
- Example workers:
  - `glitch_art` - Image glitch effects using NumPy
  - `privacy_guard` - Face detection and anonymization using OpenCV
- Security features:
  - Banned pattern detection (`rm -rf`, `shutil.rmtree`, `mkfs`, etc.)
  - Worker name validation (alphanumeric, dashes, underscores only)
  - Configurable security rules

### Security

- Implemented static code analysis to prevent execution of dangerous commands
- Added path traversal protection
- Validated worker names to prevent directory traversal attacks
- Scans Python files before execution for banned patterns

---

## Development Notes

### Version Numbering

- **Major** (X.0.0): Breaking changes, major new features
- **Minor** (0.X.0): New features, backward compatible
- **Patch** (0.0.X): Bug fixes, minor improvements

### Breaking Changes Policy

Breaking changes will only be introduced in major versions and will be clearly documented with migration guides.

---

## Upgrade Guide

### From 0.x to 1.0.0

This is the initial stable release. No upgrade path needed.

---

## Contributors

- Uday Kiran Chenna - Initial development

---

## License

MIT License - See LICENSE file for details
