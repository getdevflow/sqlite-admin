<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Controller;

use App\Application\Devflow;
use Exception;
use Plugin\SqliteAdmin\Database\SqliteImporter;
use Plugin\SqliteAdmin\Database\SqliteSchemaReader;
use Plugin\SqliteAdmin\DomainLocaleAware;
use Plugin\SqliteAdmin\Security\DatabaseAccessPolicy;
use Plugin\SqliteAdmin\Security\SqliteTableScope;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use ReflectionException;
use RuntimeException;
use Throwable;

use function App\Shared\Helpers\admin_url;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\t__;
use function rawurlencode;

final readonly class ImportController
{
    use DomainLocaleAware;

    public function __construct(
        private SqliteImporter $importer,
        private SqliteSchemaReader $schema,
        private SqliteTableScope $scope,
        private DatabaseAccessPolicy $policy,
    ) {}

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
        if (! $this->policy->canRunWriteSql()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        return view('plugin::SqliteAdmin/view/database/import', [
            'title' => t__('Import Database', $this->domain()),
            'tables' => $this->schema->tablesAndViews(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function databaseImport(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->policy->canRunWriteSql()) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $file = $this->uploadedFile($request);

        $extension = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));

        match ($extension) {
            'sql' => $this->importer->importSqlFile($file),
            default => throw new RuntimeException(t__('Only SQL database imports are supported here.', $this->domain())),
        };

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('plugin/sqlite-admin'));
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

        return view('plugin::SqliteAdmin/view/table/import', [
            'title' => t__('Import Into Table', $this->domain()),
            'table' => $table,
            'columns' => $this->schema->columns($table),
            'tables' => $this->schema->tablesAndViews(),
        ]);
    }

    /**
     * @throws Throwable
     * @throws \Qubus\Exception\Exception
     */
    public function tableImport(ServerRequestInterface $request, string $table): ResponseInterface
    {
        if (! $this->policy->canAccess() || ! $this->scope->canSeeTable($table, $this->policy)) {
            return view('plugin::SqliteAdmin/view/error/forbidden');
        }

        $file = $this->uploadedFile($request);

        $extension = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));

        match ($extension) {
            'csv' => $this->importer->importCsvIntoTable($table, $file, $this->schema->columns($table)),
            'sql' => $this->importer->importSqlFile($file),
            default => throw new RuntimeException(t__('Only CSV and SQL imports are supported.', $this->domain())),
        };

        Devflow::$PHP->flash->success(Devflow::$PHP->flash->notice(200));

        return redirect(admin_url('sqlite-admin/table/' . rawurlencode($table)));
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function uploadedFile(ServerRequestInterface $request): UploadedFileInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['import'] ?? null;

        if (! $file instanceof UploadedFileInterface) {
            throw new RuntimeException(t__('No import file was uploaded.', $this->domain()));
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException(t__('The uploaded import file could not be processed.', $this->domain()));
        }

        return $file;
    }
}
