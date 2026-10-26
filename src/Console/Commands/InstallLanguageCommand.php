<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MadeItEasyTools\Multiverse\Process\ProcessRunner;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstallLanguageCommand extends Command
{
    protected $signature = 'multiverse:install {--lang= : Python runtime to install (python only)}';

    protected $description = 'Install and set up the Python runtime environment';

    public function __construct(
        protected ProcessRunner $processRunner
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $lang = $this->option('lang');

        if (! $lang) {
            error('Please specify a language using --lang option');
            info('Example: php artisan multiverse:install --lang=python');

            return;
        }

        match ($lang) {
            'python' => $this->installPython(),
            'node' => $this->installNode(),
            default => error("Language [{$lang}] is not supported. Only Python is supported.")
        };
    }

    protected function installPython(): void
    {
        info('🐍 Installing Python environment...');

        // 1. Check if python3 is available
        try {
            $this->processRunner->run(['python3', '--version']);
        } catch (\Exception) {
            error('Python 3 is not installed on your system.');
            error('Please install Python 3 first: https://www.python.org/downloads/');

            return;
        }

        // 2. Create directory structure
        $toAbsolute = fn (string $path): string => str_starts_with($path, '/') ? $path : base_path($path);
        $pythonRoot = $toAbsolute(config('multiverse.python.root_path', 'multiverse/python'));
        $venvPath = $toAbsolute(config('multiverse.python.venv_path', 'multiverse/python/venv'));
        $reqPath = $toAbsolute(config('multiverse.python.requirements_path', 'multiverse/python/requirements.txt'));

        if (! File::exists($pythonRoot)) {
            File::makeDirectory($pythonRoot, 0755, true);
            info("✓ Created directory: {$pythonRoot}");
        }

        // 3. Create virtual environment
        if (! is_dir($venvPath)) {
            info('Creating virtual environment...');

            spin(
                fn () => $this->processRunner->run(['python3', '-m', 'venv', $venvPath]),
                'Setting up Python virtual environment...'
            );

            info("✓ Virtual environment created at: {$venvPath}");
        } else {
            warning('Virtual environment already exists, skipping...');
        }

        // 4. Create requirements.txt
        if (! File::exists($reqPath)) {
            File::put($reqPath, "# Python Dependencies\n# Add your packages here, one per line\n");
            info("✓ Created requirements.txt at: {$reqPath}");
        }

        // 5. Upgrade pip
        info('Upgrading pip...');
        $pipPath = $venvPath.'/bin/pip';
        $pipTimeout = config('multiverse.pip_timeout', 300);

        spin(
            fn () => $this->processRunner->run([$pipPath, 'install', '--upgrade', 'pip'], null, null, null, $pipTimeout),
            'Upgrading pip...'
        );

        info('✓ Pip upgraded successfully');

        // 6. Install Dependencies
        info('Installing dependencies from requirements.txt...');

        spin(
            fn () => $this->processRunner->run([$pipPath, 'install', '-r', $reqPath], null, null, null, $pipTimeout),
            'Installing dependencies...'
        );

        info('✓ Dependencies installed successfully');

        // 7. Update .gitignore
        $this->updateGitignore();

        // 8. Success message
        $this->newLine();
        info('🎉 Python environment installed successfully!');
        $this->newLine();
        info('Next steps:');
        info('  1. Add dependencies to: workers/python/requirements.txt');
        info('  2. Create a worker: php artisan make:worker');
        info('  3. Run your worker: php artisan worker:run <name>');
    }

    /**
     * Update or create .gitignore inside multiverse directory.
     */
    protected function updateGitignore(): void
    {
        $workersPath = config('multiverse.workers_path');

        if (! str_starts_with($workersPath, '/')) {
            $workersPath = base_path($workersPath);
        }

        $gitignorePath = $workersPath.'/.gitignore';

        $multiverseRules = <<<'GITIGNORE'
# Python Virtual Environments
python/venv/
*/venv/

# Worker Outputs
*/*/output/

# Python Cache
*.pyc
__pycache__/
*.py[cod]
*$py.class

# IDE
.vscode/
.idea/

# OS
.DS_Store
Thumbs.db
GITIGNORE;

        if (! File::isDirectory($workersPath)) {
            File::makeDirectory($workersPath, 0755, true);
        }

        if (File::exists($gitignorePath)) {
            $existing = File::get($gitignorePath);
            $missing = array_filter(
                explode("\n", $multiverseRules),
                fn (string $line) => $line !== '' && ! str_contains($existing, $line)
            );
            if (empty($missing)) {
                info("✓ .gitignore already up-to-date at: {$gitignorePath}");

                return;
            }
            $result = File::append($gitignorePath, PHP_EOL.implode(PHP_EOL, $missing).PHP_EOL);
        } else {
            $result = File::put($gitignorePath, $multiverseRules);
        }

        if ($result !== false) {
            info("✓ Updated .gitignore at: {$gitignorePath}");
        } else {
            error("Failed to update .gitignore at: {$gitignorePath}");
        }
    }

    protected function installNode(): void
    {
        error('Only Python is supported. Other language runtimes are not planned.');
    }
}
