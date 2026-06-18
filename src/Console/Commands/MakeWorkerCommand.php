<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeWorkerCommand extends Command
{
    protected $signature = 'multiverse:worker {name? : The name of the worker} {--lang= : The language of the worker}';

    protected $description = 'Create a new multi-language worker';

    public function handle(): void
    {
        $name = $this->argument('name');
        $lang = $this->option('lang');

        if (! $lang) {
            $lang = select(
                label: 'Which language do you want to use?',
                options: ['python'],
                default: 'python'
            );
        }

        if (! $name) {
            $name = text(
                label: 'What is the name of the worker?',
                placeholder: 'e.g. email_parser',
                required: true,
                validate: fn (string $value) => match (true) {
                    \strlen($value) < 3 => 'The name must be at least 3 characters.',
                    ! preg_match('/^[a-zA-Z0-9_-]+$/', $value) => 'Only letters, numbers, dashes, and underscores are allowed.',
                    default => null
                }
            );
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            $this->error("Invalid worker name [{$name}]. Only letters, numbers, dashes, and underscores are allowed.");
            return;
        }

        // Place in workers/{lang}/{name}
        $workersPath = config('multiverse.workers_path', base_path('multiverse'));
        $workerPath = $workersPath.'/'.$lang.'/'.$name;

        if (File::exists($workerPath)) {
            $this->error("Worker [{$name}] already exists at {$workerPath}!");

            return;
        }

        File::makeDirectory($workerPath, 0755, true);

        $created = match ($lang) {
            'python' => $this->createPythonStubs($workerPath),
            default  => false,
        };

        if ($created === false) {
            File::deleteDirectory($workerPath);
            $this->error("No stub generator for language [{$lang}]. Worker directory removed.");
            return;
        }

        $this->info("Worker [{$name}] created successfully.");
        $this->info("Path: {$workerPath}");
        $this->comment("Don't forget to run dependencies installation if needed (will happen auto on first run if configured, or you can do it manually).");
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
