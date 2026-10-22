# Crud

This package is used to generate cruds.

> **You are on branch `5.0` - package version `5.0`.**

## Documentation for contributors

| Document | Read it for |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the package is wired: the static `Crud` class, field metadata, the listing query |
| [docs/METHODS.md](docs/METHODS.md) | What every method of `Crud` does on this branch |
| [docs/RULES.md](docs/RULES.md) | What you may and may not change: this branch is archived and closed to new features |

## Requirements

| Requirement | Supported |
| ----------- | --------- |
| PHP         | `>= 5.5` |
| Laravel     | `5.1 - 5.2` (`illuminate/support ~5.1`) |
| Frontend    | Bootstrap 3 + jQuery + DataTables 1.x, laravelcollective/html |

Laravel bounds are derived from the framework APIs each branch actually calls,
since the `composer.json` constraints were never updated:

- `Form::` helpers (`laravelcollective/html`) on `5.0`-`5.4` -> Laravel `5.x` only
- Package auto-discovery (`extra.laravel.providers`) -> Laravel `>= 5.5`
- `Storage::disk()->temporaryUrl()` -> Laravel `>= 5.4`
- `BelongsTo::getOwnerKeyName()` -> Laravel `>= 5.8`
- `array_except()` (removed in Laravel 6) on `5.3`, `5.4`, `5.7` -> Laravel `<= 5.8`
- `Relation::getRelationExistenceQuery()` -> Laravel `>= 5.5`
- `Paginator::withQueryString()` -> Laravel `>= 7.0`

## Compatibility matrix

The branch number is the **package version**, not the Laravel version.
Every package version lives in its own branch and is released from it.

| Package version | Branch   | PHP      | Laravel     | Status                                 |
| --------------- | -------- | -------- | ----------- | -------------------------------------- |
| 8.0             | `master` | `>= 7.2` | `>= 5.8`    | Active                                 |
| 7.0 Beta        | `7.x`    | `>= 7.2` | `>= 7.0`    | Active (Vue views, paginated response) |
| 6.0             | `6.0`    | `>= 7.2` | `>= 5.8`    | Maintenance                            |
| 5.9             | `5.9`    | `>= 7.1` | `>= 6.0`    | Maintenance                            |
| 5.8             | `5.8`    | `>= 7.1` | `>= 7.0`    | Maintenance                            |
| 5.7             | `5.7`    | `>= 7.1` | `5.8` only  | Legacy                                 |
| 5.6             | `5.6`    | `>= 7.0` | `>= 5.5`    | Legacy                                 |
| 5.5             | `5.5`    | `>= 7.0` | `>= 5.5`    | Legacy                                 |
| 5.4             | `5.4`    | `>= 5.6` | `5.4 - 5.8` | Legacy                                 |
| 5.3             | `5.3`    | `>= 5.5` | `5.1 - 5.4` | Legacy                                 |
| 5.0             | `5.0`    | `>= 5.5` | `5.0 - 5.2` | Archived                               |

## Feature matrix

| Version  | BS  | Datatables | Methods | Views engine | Crypt | Constructor | UTC | Validation     |
| -------- | --- | ---------- | ------- | ------------ | ----- | ----------- | --- | -------------- |
| 5.5      | 3   | 1.x        | es      | blade        | yes   | yes         | no  | formvalidation |
| 5.6      | 4   | 1.x        | es      | blade        | yes   | yes         | no  | formvalidation |
| 5.9      | 4   | 1.x        | es      | blade        | yes   | no          | no  | laravel        |
| 6.0      | 4   | 1.x        | en      | blade        | yes   | yes         | no  | formvalidation |
| 7.0 Beta | 4   | 1.x        | en      | vue          | yes   | yes         | no  | formvalidation |
| 8.0      | 4/5 | 2.x        | en      | blade        | no    | no          | yes | laravel        |

## Installation

```bash
composer require csgt/crud:^5.0
```

## Usage

This version predates the `CrudController` API of 5.3 and up. It is a static
facade driven by a table name, not by an Eloquent model, and the service
provider has to be registered by hand in `config/app.php`:

```php
'providers' => [
    Csgt\Crud\CrudServiceProvider::class,
],

'aliases' => [
    'Crud' => Csgt\Crud\Facades\Crud::class,
],
```

```php
use Crud;

class ClientesController extends Controller
{
    public function index()
    {
        Crud::setTabla('clientes');
        Crud::setTablaId('cliente_id');
        Crud::setTitulo('Clientes');

        Crud::setCampo(['campo' => 'nombre', 'nombre' => 'Nombre']);
        Crud::setPermisos(['add' => true, 'edit' => true, 'delete' => true]);

        return Crud::index();
    }
}
```

Available setters: `setTabla`, `setTablaId`, `setTitulo`, `setCampo`,
`setWhere`, `setWhereIn`, `setWhereRaw`, `setLeftJoin`, `setOrderBy`,
`setGroupBy`, `setHidden`, `setBotonExtra`, `setPermisos`, `setPerPage`,
`setTemplate`, `setSlug`, `setSoftDelete`, `setExport`, `setSearch`,
`setStateSave`.

## Status

This branch is archived. It receives no fixes; the listing optimization
applied to `5.3` and up was not backported here. Use branch `5.3` or newer.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The suite covers the methods that build the listing query, asserting on the SQL
and the bindings they produce. It needs Eloquent but never a database server:
no query is executed. The test tooling is `require-dev` only, so nothing
changes for consumers of the package.
