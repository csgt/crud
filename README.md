# Crud

This package is used to generate cruds.

> **You are on branch `7.x` - package version `7.0 Beta`.**

## Documentation for contributors

| Document | Read it for |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the package is wired: request lifecycle, field metadata, the listing query, and what is unfinished on this Beta branch |
| [docs/METHODS.md](docs/METHODS.md) | What every method of `CrudController` does on this branch |
| [docs/RULES.md](docs/RULES.md) | What you may and may not change: no repurposing methods, behaviour changes go to a new version branch, legacy compatibility first |

## Requirements

| Requirement | Supported |
| ----------- | --------- |
| PHP         | `>= 7.2` |
| Laravel     | `>= 7.0` |
| Frontend    | Vue components (the host app builds them) |

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
composer require csgt/crud:^7.0
```

## Usage

Create a controller that extends `CrudController` and describe the CRUD in
`__construct()`:

```php
use Csgt\Crud\CrudController;

class ClientsController extends CrudController
{
    public function __construct()
    {
        $this->setModel(new \App\Client);
        $this->setTitle('Clients');

        $this->setField(['name' => 'Name', 'field' => 'name']);
        $this->setField(['name' => 'Country', 'field' => 'country.name', 'isforeign' => true]);

        $this->setPermissions(['create' => true, 'update' => true, 'destroy' => true]);
    }
}
```

Then register the route:

```php
Route::resource('clients', ClientsController::class);
```

## Known differences from 6.0

- Views are Vue components; add the `@prejavascript` section to your template.
- Validations move to `$this->setValidation()`.
- `enum` is deprecated, use `combo` with a manual collection.
- `isforeign` is now required for relations.
- `toastr` is required.

This branch already resolves search, ordering and pagination in the database
(`$query->paginate()`), so it does not need the listing optimization applied to
the other branches.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The suite covers the methods that build the listing query, asserting on the SQL
and the bindings they produce. It needs Eloquent but never a database server:
no query is executed. The test tooling is `require-dev` only, so nothing
changes for consumers of the package.
