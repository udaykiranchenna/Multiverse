<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeWorkerCommand extends Command
{
    protected $signature = 'multiverse:worker {name? : The name of the worker} {--lang= : Worker runtime (python only)}';

    protected $description = 'Create a new Python worker';

    public function handle(): int
    {
        $name = $this->argument('name');
        $lang = $this->option('lang');

        if (! $lang) {
            $lang = select(
                label: 'Confirm the Python worker runtime',
                options: ['python'],
                default: 'python'
            );
        }

        if ($lang !== 'python') {
            $this->error('Only the python worker generator is supported.');

            return self::FAILURE;
        }

        if (! $name) {
            $name = text(
                label: 'What is the name of the worker?',
                placeholder: 'e.g. email_parser',
                required: true,
                validate: fn (string $value) => match (true) {
                    \strlen($value) < 3 => 'The name must be at least 3 characters.',
                    ! preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $value) => 'Use at most 128 letters, numbers, dashes, or underscores.',
                    default => null
                }
            );
        }

        if (! preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $name)) {
            $this->error('Invalid worker name. Use 1 to 128 letters, numbers, dashes, or underscores.');

            return self::FAILURE;
        }

        // Place in workers/{lang}/{name}
        $workersPath = config('multiverse.workers_path', base_path('multiverse'));
        File::ensureDirectoryExists($workersPath);
        $root = realpath($workersPath);
        $languagePath = $root.'/'.$lang;

        if (is_link($languagePath) && ! str_starts_with((string) realpath($languagePath), $root.DIRECTORY_SEPARATOR)) {
            $this->error('The language directory must resolve inside the workers directory.');

            return self::FAILURE;
        }

        $workerPath = $languagePath.'/'.$name;

        if (File::exists($workerPath)) {
            $this->error("Worker [{$name}] already exists at {$workerPath}!");

            return self::FAILURE;
        }

        File::makeDirectory($workerPath, 0755, true);

        $this->createPythonStubs($workerPath);

        $this->info("Worker [{$name}] created successfully.");
        $this->info("Path: {$workerPath}");
        $this->comment('Install dependencies with multiverse:install --lang=python before running the worker.');

        return self::SUCCESS;
    }

    protected function createPythonStubs(string $path): void
    {
        // main.py
        $stub = <<<'PYTHON'
import sys
import json

def main():
    # Read input from stdin
    try:
        input_str = sys.stdin.read()
        if not input_str:
            data = {}
        else:
            data = json.loads(input_str)
    except Exception as e:
        print(json.dumps({"status": "error", "message": f"Failed to parse input: {str(e)}"}))
        return

    # Process your logic here
    # result_data = {"key": "value"}
    result_data = data
    
    # Create strict response
    response = {
        "status": "success",
        "message": "Worker executed successfully",
        "data": result_data
    }
    
    # Print output as JSON
    print(json.dumps(response))

if __name__ == "__main__":
    main()
PYTHON;

        File::put($path.'/main.py', $stub);

        // We no longer create requirements.txt per worker, as we use a shared one.
        // We can optionally check if the shared one exists and append? No, better let user manage it manually to avoid duplicates.

        // .gitignore for the worker folder
        $gitignore = <<<'GITIGNORE'
# Python
__pycache__/
*.pyc
*.py[cod]

# Worker Output
output/
*.log

# IDE
.vscode/
.idea/

# OS
.DS_Store
Thumbs.db
GITIGNORE;

        File::put($path.'/.gitignore', $gitignore);
    }
}
