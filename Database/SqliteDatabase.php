<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use PDO;
use PDOStatement;
use Qubus\Expressive\Database;
use Throwable;

final readonly class SqliteDatabase
{
    public function __construct(
        private Database $dfdb,
    ) {
    }

    public function pdo(): PDO
    {
        return $this->dfdb->getConnection()->pdo;
    }

    public function query(string $sql): PDOStatement|false
    {
        return $this->dfdb->getConnection()->pdo->query($sql);
    }

    /**
     * @param array<string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->dfdb->getConnection()->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    /**
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->dfdb->getConnection()->pdo->inTransaction()) {
            return $callback($this);
        }

        $this->dfdb->getConnection()->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->dfdb->getConnection()->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->dfdb->getConnection()->pdo->inTransaction()) {
                $this->dfdb->getConnection()->pdo->rollBack();
            }

            throw $e;
        }
    }
}
