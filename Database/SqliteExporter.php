<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use PDO;
use Plugin\SqliteAdmin\Http\DownloadResponseFactory;
use Plugin\SqliteAdmin\Security\Identifier;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

final readonly class SqliteExporter
{
    public function __construct(
        private SqliteDatabase $database,
        private SqliteSchemaReader $schema,
        private DownloadResponseFactory $downloads,
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function downloadDatabaseSql(): ResponseInterface
    {
        $sql = [];

        foreach ($this->schema->tablesAndViews() as $item) {
            $name = (string) $item['name'];

            if (! empty($item['sql'])) {
                $sql[] = (string) $item['sql'] . ';';
            }

            if (($item['type'] ?? '') === 'table') {
                $sql[] = $this->tableDataSql($name);
            }
        }

        return $this->downloads->make(
            implode("\n\n", array_filter($sql)) . "\n",
            'sqlite-database.sql',
            'text/sql; charset=utf-8'
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function downloadDatabaseCsv(): ResponseInterface
    {
        $parts = [];

        foreach ($this->schema->tablesAndViews() as $item) {
            if (($item['type'] ?? '') !== 'table') {
                continue;
            }

            $table = (string) $item['name'];
            $parts[] = '-- TABLE: ' . $table;
            $parts[] = $this->tableCsv($table);
        }

        return $this->downloads->make(
            implode("\n\n", $parts),
            'sqlite-database.csv',
            'text/csv; charset=utf-8'
        );
    }

    public function downloadTableSql(string $table): ResponseInterface
    {
        $createSql = $this->schema->createSql($table);

        $sql = [];

        if ($createSql !== null) {
            $sql[] = $createSql . ';';
        }

        $sql[] = $this->tableDataSql($table);

        return $this->downloads->make(
            implode("\n\n", array_filter($sql)) . "\n",
            $table . '.sql',
            'text/sql; charset=utf-8'
        );
    }

    public function downloadTableCsv(string $table): ResponseInterface
    {
        return $this->downloads->make(
            $this->tableCsv($table),
            $table . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    private function tableDataSql(string $table): string
    {
        $quotedTable = Identifier::from($table)->quoted();

        $stmt = $this->database->pdo()->query('SELECT * FROM ' . $quotedTable);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return '';
        }

        $sql = [];

        foreach ($rows as $row) {
            $columns = array_map(
                static fn (string $column): string => Identifier::from($column)->quoted(),
                array_keys($row)
            );

            $values = array_map(
                fn (mixed $value): string => $this->sqlValue($value),
                array_values($row)
            );

            $sql[] = sprintf(
                'INSERT INTO %s (%s) VALUES (%s);',
                $quotedTable,
                implode(', ', $columns),
                implode(', ', $values)
            );
        }

        return implode("\n", $sql);
    }

    private function tableCsv(string $table): string
    {
        $quotedTable = Identifier::from($table)->quoted();

        $stmt = $this->database->pdo()->query('SELECT * FROM ' . $quotedTable);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);

        $csv = stream_get_contents($handle);

        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param list<string> $tables
     */
    public function downloadTablesSql(array $tables): ResponseInterface
    {
        $sql = [];

        foreach ($tables as $table) {
            $createSql = $this->schema->createSql($table);

            if ($createSql !== null) {
                $sql[] = $createSql . ';';
            }

            $sql[] = $this->tableDataSql($table);
        }

        return $this->downloads->make(
            implode("\n\n", array_filter($sql)) . "\n",
            'sqlite-selected-tables.sql',
            'text/sql; charset=utf-8'
        );
    }

    /**
     * @param list<string> $tables
     */
    public function downloadTablesCsv(array $tables): ResponseInterface
    {
        $parts = [];

        foreach ($tables as $table) {
            $parts[] = '-- TABLE: ' . $table;
            $parts[] = $this->tableCsv($table);
        }

        return $this->downloads->make(
            implode("\n\n", $parts),
            'sqlite-selected-tables.csv',
            'text/csv; charset=utf-8'
        );
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $this->database->pdo()->quote((string) $value);
    }
}
