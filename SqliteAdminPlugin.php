<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin;

use App\Application\Devflow;
use App\Infrastructure\Services\Plugin;
use App\Shared\Services\Registry;
use JsonException;
use Plugin\SqliteAdmin\Controller\DatabaseController;
use Plugin\SqliteAdmin\Controller\ExportController;
use Plugin\SqliteAdmin\Controller\ImportController;
use Plugin\SqliteAdmin\Controller\IndexController;
use Plugin\SqliteAdmin\Controller\SqlController;
use Plugin\SqliteAdmin\Controller\TableController;
use Plugin\SqliteAdmin\Controller\TriggerController;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\ServerRequest;
use Qubus\Routing\Psr7Router;
use ReflectionException;

use Throwable;

use function App\Shared\Helpers\add_plugins_submenu;
use function App\Shared\Helpers\plugin_basename;
use function App\Shared\Helpers\plugin_dir_path;
use function App\Shared\Helpers\plugin_url;
use function dirname;
use function get_class;
use function Qubus\Security\Helpers\esc_html__;

class SqliteAdminPlugin extends Plugin
{
    /**
     * @inheritDoc
     * @throws ReflectionException|Exception
     */
    public function meta(): array
    {
        $plugin = [
            'name' => esc_html__(string: 'SQLite Admin', domain: 'sqlite-admin'),
            'id' => 'sqlite-admin',
            'slug' => 'SqliteAdmin',
            'author' => 'Joshua Parker',
            'version' => '1.0.1',
            'description' => 'An easy web-based database management tool for SQLite.',
            'basename' => plugin_basename(dirname(__FILE__)),
            'path' => plugin_dir_path(dirname(__FILE__)),
            'url' => plugin_url('', __CLASS__),
            'pluginUri' => 'https://github.com/getdevflow/DevflowSqliteAdmin',
            'authorUri' => 'https://joshuaparker.dev/',
            'className' => get_class($this),
            'screenshot' => plugin_url('SqliteAdmin/images/screenshot.png'),
        ];

        Registry::getInstance()->set('sqlite-admin', $plugin);

        return $plugin;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function handle(): void
    {
        Action::getInstance()->addAction('plugins_submenu', [$this, 'registerSubmenu']);
        Action::getInstance()->addAction('plugins_loaded', [$this, 'render'], 1);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function registerSubmenu(): void
    {
        echo add_plugins_submenu(
            menuTitle: $this->meta()['name'],
            menuRoute: 'plugin/' . $this->meta()['id'],
            screen: $this->meta()['id'],
            permission: 'manage:plugins',
            newTab: true
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws JsonException
     * @throws Throwable
     */
    public function render(): void
    {
        $router = Devflow::$PHP->make(name: Psr7Router::class);

        $router->group('/admin/plugin/sqlite-admin', function ($route): void {
            $route->get('/', fn(DatabaseController $c) => $c->dashboard());
            $route->get('/structure', fn(DatabaseController $c) => $c->structure());
            $route->get('/sql', fn(SqlController $c) => $c->editor());
            $route->post('/sql', fn(SqlController $c, ServerRequest $r) => $c->execute($r));
            $route->get('/export', fn(ExportController $c) => $c->databaseForm());
            $route->post('/export', fn(ExportController $c, ServerRequest $r) => $c->databaseExport($r));
            $route->get('/import', fn(ImportController $c) => $c->databaseForm());
            $route->post('/import', fn(ImportController $c, ServerRequest $r) => $c->databaseImport($r));
            $route->post('/vacuum', fn(DatabaseController $c) => $c->vacuum());
            $route->post('/integrity-check', fn(DatabaseController $c) => $c->integrityCheck());

            $route->get('/table/{table}', fn(TableController $c, ServerRequest $r, string $table) => $c->browse($r, $table));
            $route->get('/table/{table}/structure', fn(TableController $c, ServerRequest $r, string $table) => $c->structure($r, $table));
            $route->get('/table/{table}/search', fn(TableController $c, ServerRequest $r, string $table) => $c->searchForm($r, $table));
            $route->post('/table/{table}/search', fn(TableController $c, ServerRequest $r, string $table) => $c->search($r, $table));
            $route->get('/table/{table}/insert', fn(TableController $c, ServerRequest $r, string $table) => $c->insertForm($r, $table));
            $route->post('/table/{table}/insert', fn(TableController $c, ServerRequest $r, string $table) => $c->insert($r, $table));
            $route->get('/table/{table}/edit', fn(TableController $c, ServerRequest $r, string $table) => $c->editForm($r, $table));
            $route->post('/table/{table}/edit', fn(TableController $c, ServerRequest $r, string $table) => $c->update($r, $table));
            $route->post('/table/{table}/delete-row', fn(TableController $c, ServerRequest $r, string $table) => $c->deleteRow($r, $table));
            $route->post('/table/{table}/empty', fn(TableController $c, ServerRequest $r, string $table) => $c->empty($r, $table));
            $route->post('/table/{table}/drop', fn(TableController $c, ServerRequest $r, string $table) => $c->drop($r, $table));
            $route->post('/table/{table}/rename', fn(TableController $c, ServerRequest $r, string $table) => $c->rename($r, $table));
            $route->post('/table/{table}/columns/add', fn(TableController $c, ServerRequest $r, string $table) => $c->addColumn($r, $table));
            $route->post('/table/{table}/columns/rename', fn(TableController $c, ServerRequest $r, string $table) => $c->renameColumn($r, $table));
            $route->post('/table/{table}/columns/drop', fn(TableController $c, ServerRequest $r, string $table) => $c->dropColumn($r, $table));

            $route->get('/table/{table}/sql', fn(SqlController $c, string $table) => $c->tableEditor($table));
            $route->post('/table/{table}/sql', fn(SqlController $c, ServerRequest $r, string $table) => $c->executeForTable($r, $table));

            $route->get('/table/{table}/export', fn(ExportController $c, string $table) => $c->tableForm($table));
            $route->post('/table/{table}/export', fn(ExportController $c, ServerRequest $r, string $table) => $c->tableExport($r, $table));
            $route->get('/table/{table}/import', fn(ImportController $c, string $table) => $c->tableForm($table));
            $route->post('/table/{table}/import', fn(ImportController $c, ServerRequest $r, string $table) => $c->tableImport($r, $table));

            $route->get('/table/{table}/indexes', fn(IndexController $c, ServerRequest $r, string $table) => $c->index($r, $table));
            $route->post('/table/{table}/indexes', fn(IndexController $c, ServerRequest $r, string $table) => $c->create($r, $table));
            $route->post('/table/{table}/indexes/drop', fn(IndexController $c, ServerRequest $r, string $table) => $c->drop($r, $table));

            $route->get('/table/{table}/triggers', fn(TriggerController $c, ServerRequest $r, string $table) => $c->index($r, $table));
            $route->post('/table/{table}/triggers', fn(TriggerController $c, ServerRequest $r, string $table) => $c->create($r, $table));
            $route->post('/table/{table}/triggers/drop', fn(TriggerController $c, ServerRequest $r, string $table) => $c->drop($r, $table));
            $route->map(['GET', 'POST'],
                '/table/{table}/triggers/edit/{trigger}',
                fn (
                    TriggerController $c,
                    ServerRequest $r,
                    string $table,
                    string $trigger
                ) => $c->edit($r, $table, $trigger)
            );
        });
    }
}
