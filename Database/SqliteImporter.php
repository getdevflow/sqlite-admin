<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Database;

use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\Identifier;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\UploadedFileInterface;
use ReflectionException;
use RuntimeException;
use Throwable;

use function Qubus\Security\Helpers\t__;

final readonly class SqliteImporter
{
    use DomainLocaleAware;

    public function __construct(
            private SqliteDatabase $database,
            private SqliteStatementSplitter $splitter,
    ) {}

    /**
     * @throws Throwable
     */
    public function importSqlFile(UploadedFileInterface $file): void
    {
        $sql = (string) $file->getStream();

        $statements = $this->splitter->split($sql);

        if ($statements === []) {
            throw new RuntimeException(t__('The SQL import file did not contain any executable statements.', $this->domain()));
        }

        $this->database->transaction(function () use ($statements): void {
            foreach ($statements as $statement) {
                $this->database->query($statement);
            }
        });
    }

    /**
     * @param list<array<string,mixed>> $columns
     * @throws Throwable
     */
    public function importCsvIntoTable(
            string $table,
            UploadedFileInterface $file,
            array $columns = []
    ): void {
        $stream = $file->getStream();

        $tmp = tmpfile();

        if ($tmp === false) {
            throw new RuntimeException(t__('Could not create temporary import file.', $this->domain()));
        }

        fwrite($tmp, (string) $stream);

        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'] ?? null;

        if (! is_string($path)) {
            fclose($tmp);
            throw new RuntimeException(t__('Could not read temporary import file.', $this->domain()));
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            fclose($tmp);
            throw new RuntimeException(t__('Could not open uploaded CSV file.', $this->domain()));
        }

        $headers = fgetcsv($handle);

        if ($headers === false || $headers === [null]) {
            fclose($handle);
            fclose($tmp);
            throw new RuntimeException(t__('CSV file is empty.', $this->domain()));
        }

        $headers = array_map(
            static fn (mixed $header): string => trim((string) $header),
            $headers
        );

        $this->validateHeaders($headers, $columns);

        $this->database->transaction(function () use ($table, $headers, $handle): void {
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $values = [];

                foreach ($headers as $index => $header) {
                    $values[$header] = $row[$index] ?? null;
                }

                $this->insertRow($table, $values);
            }
        });

        fclose($handle);
        fclose($tmp);
    }

    /**
     * @param list<string> $headers
     * @param list<array<string,mixed>> $columns
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function validateHeaders(array $headers, array $columns): void
    {
        if ($headers === []) {
            throw new RuntimeException(t__('CSV file does not contain headers.', $this->domain()));
        }

        foreach ($headers as $header) {
            Identifier::from($header);
        }

        if ($columns === []) {
            return;
        }

        $allowedColumns = array_map(
            static fn (array $column): string => (string) $column['name'],
            $columns
        );

        foreach ($headers as $header) {
            if (! in_array($header, $allowedColumns, true)) {
                throw new RuntimeException(sprintf(
                    t__('CSV column "%s" does not exist on the selected table.', $this->domain()),
                    $header
                ));
            }
        }
    }

    /**
     * @param array<string,mixed> $values
     */
    private function insertRow(string $table, array $values): void
    {
        $columns = array_keys($values);

        $columnSql = implode(', ', array_map(
            static fn (string $column): string => Identifier::from($column)->quoted(),
            $columns
        ));

        $placeholderSql = implode(', ', array_map(
            static fn (string $column): string => ':' . $column,
            $columns
        ));

        $this->database->execute(
            'INSERT INTO ' . Identifier::from($table)->quoted() . " ($columnSql) VALUES ($placeholderSql)",
            $values
        );
    }
}
