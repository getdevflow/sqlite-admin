<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\ValueObject;

final readonly class SqlResult
{
    /** @param list<array<string,mixed>> $rows */
    public function __construct(public string $sql, public array $rows, public float $time, public int $affectedRows = 0)
    {
    }
}
