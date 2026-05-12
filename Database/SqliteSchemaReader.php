<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use PDO;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Plugin\SqliteAdmin\Security\Identifier;
use Plugin\SqliteAdmin\Security\SqliteTableScope;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

final readonly class SqliteSchemaReader
{
    public function __construct(
        private SqliteDatabase $database,
        private SqliteTableScope $scope,
        private DatabaseAccessPolicy $policy,
    ) {
    }

    /**
     * @return list<array{name:string,type:string,sql:string|null}>
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws Exception
     * @throws ReflectionException
     */
    public function tablesAndViews(): array
    {
        $stmt = $this->database->pdo()->query(
            "SELECT name, type, sql
             FROM sqlite_master
             WHERE type IN ('table', 'view')
             AND name NOT LIKE 'sqlite_%'
             ORDER BY name ASC"
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->scope->filter($rows, $this->policy);
    }

    /** @return list<array<string, mixed>> */
    public function columns(string $table): array
    {
        $table = Identifier::from($table);

        return $this->database->pdo()
            ->query('PRAGMA table_info(' . $table->quoted() . ')')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function indexes(string $table): array
    {
        $table = Identifier::from($table);

        return $this->database->pdo()
            ->query('PRAGMA index_list(' . $table->quoted() . ')')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function triggers(string $table): array
    {
        $stmt = $this->database->execute(
            "SELECT name, tbl_name, sql
             FROM sqlite_master
             WHERE type = 'trigger'
             AND tbl_name = :table
             ORDER BY name ASC",
            ['table' => $table]
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSql(string $name): ?string
    {
        $stmt = $this->database->execute(
            "SELECT sql FROM sqlite_master WHERE name = :name LIMIT 1",
            ['name' => $name]
        );

        $sql = $stmt->fetchColumn();

        return is_string($sql) ? $sql : null;
    }

    public function trigger(string $trigger): ?array
    {
        $stmt = $this->database->pdo()->prepare(
                'SELECT * FROM sqlite_master
         WHERE type = :type
         AND name = :name
         LIMIT 1'
        );

        $stmt->execute([
            'type' => 'trigger',
            'name' => $trigger,
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }
}
