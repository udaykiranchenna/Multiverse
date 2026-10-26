<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Contracts;

interface LanguageDriver
{
    /**
     * Install dependencies for the worker.
     */
    public function installDependencies(string $workerPath): void;

    /**
     * Return the default entry-point filename for the Python worker (main.py).
     */
    public function defaultScriptName(): string;

    /**
     * Get the command to run the worker file.
     *
     * @return array<int, string>
     */
    public function getRunCommand(string $workerPath, string $scriptName): array;
}
