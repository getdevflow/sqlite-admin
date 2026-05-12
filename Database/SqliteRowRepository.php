<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use JsonException;
use PDO;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\Identifier;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use RuntimeException;

use function Qubus\Security\Helpers\t__;

final readonly class SqliteRowRepository
{
    use DomainLocaleAware;

    public function __construct(private SqliteDatabase $database)
    {
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     * @throws JsonException
     */
    public function browse(string $table, int $page = 1, int $perPage = 50, ?string $sort = null, string $direction = 'ASC'): array
    {
        $tableId = Identifier::from($table);
        $offset = max(0, ($page - 1) * $perPage);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $orderBy = '';
        if ($sort !== null && $sort !== '') {
            $orderBy = ' ORDER BY ' . Identifier::from($sort)->quoted() . ' ' . $direction;
        }

        $count = (int) $this->database->pdo()
            ->query('SELECT COUNT(*) FROM ' . $tableId->quoted())
            ->fetchColumn();

        $sql = 'SELECT * FROM ' . $tableId->quoted() . $orderBy . ' LIMIT ' . max(1, $perPage) . ' OFFSET ' . $offset;
        $rows = $this->database->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $this->withRowKeys($table, $rows),
            'total' => $count,
        ];
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $columnSql = implode(', ', array_map(fn (string $column): string => Identifier::from($column)->quoted(), $columns));
        $placeholderSql = implode(', ', array_map(fn (string $column): string => ':' . $column, $columns));

        $stmt = $this->database->execute(
            'INSERT INTO ' . Identifier::from($table)->quoted() . " ($columnSql) VALUES ($placeholderSql)",
            $data
        );

        return $stmt->rowCount();
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $where */
    public function update(string $table, array $data, array $where): int
    {
        $setSql = implode(', ', array_map(
            fn (string $column): string => Identifier::from($column)->quoted() . ' = :set_' . $column,
            array_keys($data)
        ));

        $whereSql = implode(' AND ', array_map(
            fn (string $column): string => Identifier::from($column)->quoted() . ' = :where_' . $column,
            array_keys($where)
        ));

        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings['set_' . $key] = $value;
        }
        foreach ($where as $key => $value) {
            $bindings['where_' . $key] = $value;
        }

        $stmt = $this->database->execute(
            'UPDATE ' . Identifier::from($table)->quoted() . " SET $setSql WHERE $whereSql",
            $bindings
        );

        return $stmt->rowCount();
    }

    /**
     * @param string $table
     * @param array<string,mixed> $where
     * @return array<string,mixed>|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function findOne(string $table, array $where): ?array
    {
        if ($where === []) {
            throw new RuntimeException(t__('Missing row identity.', $this->domain()));
        }

        $whereSql = implode(' AND ', array_map(
            static fn (string $column): string => Identifier::from($column)->quoted() . ' IS :where_' . $column,
            array_keys($where)
        ));

        $bindings = [];

        foreach ($where as $column => $value) {
            $bindings['where_' . $column] = $value;
        }

        $stmt = $this->database->execute(
            'SELECT * FROM ' . Identifier::from($table)->quoted() . " WHERE {$whereSql} LIMIT 1",
            $bindings
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param string $table
     * @param array<string,mixed> $where
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException(t__('Missing row identity.', $this->domain()));
        }

        $whereSql = implode(' AND ', array_map(
            static fn (string $column): string => Identifier::from($column)->quoted() . ' IS :where_' . $column,
            array_keys($where)
        ));

        $bindings = [];

        foreach ($where as $column => $value) {
            $bindings['where_' . $column] = $value;
        }

        $stmt = $this->database->execute(
            'DELETE FROM ' . Identifier::from($table)->quoted() . " WHERE {$whereSql}",
            $bindings
        );

        return $stmt->rowCount();
    }

    /**
     * @param array<string,mixed> $data
     * @param list<string> $nulls
     * @return array<string,mixed>
     */
    public function normalizeFormData(array $data, array $nulls = []): array
    {
        foreach ($nulls as $column) {
            if (array_key_exists($column, $data)) {
                $data[$column] = null;
            }
        }

        return $data;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     * @throws JsonException
     */
    public function withRowKeys(string $table, array $rows): array
    {
        foreach ($rows as &$row) {
            $row['_df_row_key'] = base64_encode(
                json_encode(
                    $this->rowIdentity($table, $row),
                    JSON_THROW_ON_ERROR
                )
            );
        }

        unset($row);

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{type:string,where:array<string,mixed>}
     */
    private function rowIdentity(string $table, array $row): array
    {
        $primaryKeys = $this->primaryKeyColumns($table);

        if ($primaryKeys !== []) {
            return [
                'type' => 'primary',
                'where' => array_intersect_key($row, array_flip($primaryKeys)),
            ];
        }

        return [
            'type' => 'row',
            'where' => $row,
        ];
    }

    /**
     * @return list<string>
     */
    private function primaryKeyColumns(string $table): array
    {
        $tableId = Identifier::from($table);

        $statement = $this->database
            ->pdo()
            ->query('PRAGMA table_info(' . $tableId->quoted() . ')');

        if ($statement === false) {
            return [];
        }

        $columns = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $primaryKeys = [];

        foreach ($columns as $column) {
            if (! empty($column['pk'])) {
                $primaryKeys[(int) $column['pk']] = (string) $column['name'];
            }
        }

        ksort($primaryKeys);

        return array_values($primaryKeys);
    }

    /**
     * @param string $encoded
     * @return array<string,mixed>
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function decodeRowKey(string $encoded): array
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new RuntimeException(t__('Invalid row key.', $this->domain()));
        }

        $payload = json_decode(
            $decoded,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        if (
                ! is_array($payload)
                || ! isset($payload['where'])
                || ! is_array($payload['where'])
        ) {
            throw new RuntimeException(t__('Invalid row key payload.', $this->domain()));
        }

        return $payload['where'];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     * @throws JsonException
     */
    public function search(string $table, array $filters): array
    {
        $where = [];
        $bindings = [];

        foreach ($filters as $column => $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $value = trim((string) ($filter['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $operator = strtoupper((string) ($filter['operator'] ?? 'LIKE'));
            $columnId = Identifier::from((string) $column);
            $param = 'search_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string) $column);

            match ($operator) {
                '=', '!=', '>', '>=', '<', '<=' => [
                    $where[] = $columnId->quoted() . " {$operator} :{$param}",
                    $bindings[$param] = $value,
                ],
                default => [
                    $where[] = $columnId->quoted() . " LIKE :{$param}",
                    $bindings[$param] = '%' . $value . '%',
                ],
            };
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = $this->database->execute(
            'SELECT COUNT(*) FROM ' . Identifier::from($table)->quoted() . $whereSql,
            $bindings
        );

        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->database->execute(
            'SELECT * FROM ' . Identifier::from($table)->quoted() . $whereSql . ' LIMIT 200',
            $bindings
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $this->withRowKeys($table, $rows),
            'total' => $total,
        ];
    }
}
