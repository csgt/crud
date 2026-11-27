# Crud

This package is used to generate cruds.

> **You are on branch `5.6` - package version `5.6`.**

## Documentation for contributors

| Document | Read it for |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the package is wired: request lifecycle, field metadata, the listing query |
| [docs/METHODS.md](docs/METHODS.md) | What every method of `CrudController` does on this branch |
| [docs/RULES.md](docs/RULES.md) | What you may and may not change: no repurposing methods, behaviour changes go to a new version branch, legacy compatibility first |

## Requirements

| Requirement | Supported |
| ----------- | --------- |
| PHP         | `>= 7.0` |
| Laravel     | `>= 5.5` |
| Frontend    | Bootstrap 4 + jQuery + DataTables 1.x |

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
composer require csgt/crud:^5.6
```

## Usage

Create a controller that extends `CrudController` and describe the CRUD in
`__construct()`:

```php
use Csgt\Crud\CrudController;

class ClientesController extends CrudController
{
    public function __construct()
    {
        $this->setModelo(new \App\Cliente);
        $this->setTitulo('Clientes');

        $this->setCampo(['nombre' => 'Nombre', 'campo' => 'nombre']);
        $this->setCampo(['nombre' => 'Pais', 'campo' => 'pais.nombre', 'isforeign' => true]);

        $this->setPermisos(['add' => true, 'edit' => true, 'delete' => true]);
    }
}
```

Then register the route:

```php
Route::resource('clientes', 'ClientesController');
```

## Listing performance

The `data()` endpoint resolves **search, ordering and pagination in the
database**:

- The DataTables global search becomes a `WHERE ... LIKE` clause, and relation
  columns are matched through `whereHas`.
- Ordering by a relation column uses a correlated subquery instead of sorting
  in memory.
- Only the requested page is fetched (`offset` + `limit`).
- `multi` fields are eager loaded, removing the N+1 query per row.

Previously the whole table was loaded into memory and filtered/sorted with
Collection methods, so the cost grew with the total number of rows instead of
the page size. No public API changed: `setField`, `setWhere`, `setOrderBy` and
the JSON response shape are the same.

## Column filters

The listing no longer uses the DataTables global search box. Instead the view
sends a list of column/value pairs as `filters[]`, and each one becomes a
`WHERE` clause:

- a plain column becomes `column LIKE ?`
- a relation column (`relation.column`, `isforeign`) is matched with `whereHas`
- a `multi` field is matched against its related column, also with `whereHas`
- a raw expression is matched with `whereRaw`, after its `AS alias` is stripped

Rows can be added and removed in the UI, they are all applied together, and the
active set is kept in the DataTables saved state. `recordsFiltered` is only
recounted when at least one filter is actually applied.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The suite covers the methods that build the listing query, asserting on the SQL
and the bindings they produce. It needs Eloquent but never a database server:
no query is executed. PHPUnit, `illuminate/database` and `illuminate/routing`
are `require-dev` only, so nothing changes for consumers of the package.
