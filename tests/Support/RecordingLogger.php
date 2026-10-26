<?php

declare(strict_types=1);

namespace MadeItEasyTools\Multiverse\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

class RecordingLogger extends AbstractLogger
{
    public array $records = [];

    public function channel(?string $channel = null): static
    {
        return $this;
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
