<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Support;

final readonly class Timer
{
    public function __construct(private float $start = 0.0)
    {
    }
    public static function start(): self
    {
        return new self(microtime(true));
    }
    public function elapsed(): float
    {
        return microtime(true) - $this->start;
    }
}
