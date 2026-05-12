<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use Exception;
use Plugin\SqliteAdmin\Database\SqliteExporter;
use Plugin\SqliteAdmin\Database\SqliteSchemaReader;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Plugin\SqliteAdmin\Security\SqliteTableScope;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;

use function Codefy\Framework\Helpers\view;
use function Qubus\Security\Helpers\t__;

final readonly class ExportController
{
    use DomainLocaleAware;

    public function __construct(
        private SqliteExporter $exporter,
        private SqliteSchemaReader $schema,
        private SqliteTableScope $scope,
        private DatabaseAccessPolicy $policy,
    ) {
    }

    /**
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws ReflectionException
     * @throws Exception
     */
    public function databaseForm(): ResponseInterface
    {
        if (! $this->policy->canDownloadDatabase()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/database/export', [
            'title' => t__('Export Database', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function databaseExport(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->policy->canDownloadDatabase()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = (array) $request->getParsedBody();

        $format = strtolower((string) ($body['format'] ?? 'sql'));
        $scope = strtolower((string) ($body['scope'] ?? 'database'));

        $selectedTables = isset($body['tables']) && is_array($body['tables'])
                ? array_values(array_map('strval', $body['tables']))
                : [];

        if ($scope === 'tables') {
            $selectedTables = $this->filterExportableTables($selectedTables);

            if ($selectedTables === []) {
                return view('plugin::SqliteAdmin/view/action-result', [
                    'title' => t__('Export Failed', $this->domain()),
                    'message' => t__('No valid tables were selected for export.', $this->domain()),
                    'tables' => $this->schema->tablesAndViews(),
                ]);
            }

            return match ($format) {
                'csv' => $this->exporter->downloadTablesCsv($selectedTables),
                default => $this->exporter->downloadTablesSql($selectedTables),
            };
        }

        return match ($format) {
            'csv' => $this->exporter->downloadDatabaseCsv(),
            default => $this->exporter->downloadDatabaseSql(),
        };
    }

    /**
     * @param string $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function tableForm(string $table): ResponseInterface
    {
        if (! $this->policy->canAccess() || ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/table/export', [
            'title' => t__('Export Table', $this->domain()),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'tables' => $this->schema->tablesAndViews(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $table
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     * @throws Exception
     */
    public function tableExport(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canAccess() || ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $body = $request->getParsedBody();
        $format = strtolower((string) ($body['format'] ?? 'sql'));

        return match ($format) {
            'csv' => $this->exporter->downloadTableCsv($table),
            default => $this->exporter->downloadTableSql($table),
        };
    }

    /**
     * @param list<string> $tables
     * @return list<string>
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws \Qubus\Exception\Exception
     */
    private function filterExportableTables(array $tables): array
    {
        $visible = [];

        foreach ($this->schema->tablesAndViews() as $item) {
            $name = (string) ($item['name'] ?? '');

            if ($name !== '' && $this->scope->canSeeTable($name, $this->policy)) {
                $visible[] = $name;
            }
        }

        return array_values(array_intersect($tables, $visible));
    }
}
