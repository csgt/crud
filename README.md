# Crud

This package is used to generate cruds.

> **You are on branch `master` — package version `8.0` (tags `v8.0.x`).**

| Version  | BS  | Datatables | Methods | Views engine | Crypt | Constructor | UTC | Validation     |
| -------- | --- | ---------- | ------- | ------------ | ----- | ----------- | --- | -------------- |
| 5.5      | 3   | 1.x        | es      | blade        | yes   | yes         | no  | formvalidation |
| 5.6      | 4   | 1.x        | es      | blade        | yes   | yes         | no  | laravel        |
| 5.9      | 4   | 1.x        | es      | blade        | yes   | no          | no  | laravel        |
| 6.0      | 4   | 1.x        | en      | blade        | yes   | yes         | no  | laravel        |
| 7.0 Beta | 4   | 1.x        | en      | vue          | yes   | yes         | no  | formvalidation |
| 8.0      | 4/5 | 2.x        | en      | blade        | no    | no          | yes | laravel        |

## Documentation for contributors

| Document | Read it for |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the package is wired: request lifecycle, field metadata, the listing query |
| [docs/METHODS.md](docs/METHODS.md) | What every method of `CrudController` does on this branch |
| [docs/RULES.md](docs/RULES.md) | What you may and may not change: no repurposing methods, behaviour changes go to a new version branch, legacy compatibility first |

## Requirements

This branch (`master`, package version `8.0`) requires:

| Requirement | Supported                                       |
| ----------- | ----------------------------------------------- |
| PHP         | `>= 7.2`                                        |
| Laravel     | `>= 5.8` (no upper bound declared; shipped against 8.x) |
| Frontend    | Bootstrap 4/5 + jQuery + DataTables 2.x         |

## Compatibility matrix

The branch number is the **package version**, not the Laravel version. Each
package version lives in its own branch and is released from it.

| Package version | Branch   | PHP      | Laravel  | Status                                   |
| --------------- | -------- | -------- | -------- | ---------------------------------------- |
| 8.0             | `master` | `>= 7.2` | `>= 5.8` | Active                                   |
| 7.0 Beta        | `7.x`    | `>= 7.2` | `>= 7.0` | Active (Vue views, paginated response)    |
| 6.0             | `6.0`    | `>= 7.2` | `>= 5.8` | Maintenance                              |
| 5.9             | `5.9`    | `>= 7.1` | `>= 6.0` | Maintenance                              |
| 5.8             | `5.8`    | `>= 7.1` | `>= 5.8` | Maintenance                              |
| 5.7             | `5.7`    | `>= 7.1` | `5.8` only | Maintenance                            |
| 5.6             | `5.6`    | `>= 7.0` | `>= 5.5` | Legacy                                   |
| 5.5             | `5.5`    | `>= 7.0` | `>= 5.5` | Legacy                                   |
| 5.4             | `5.4`    | `>= 5.6` | `5.4`–`5.8` | Legacy                                  |
| 5.3             | `5.3`    | `>= 5.5` | `5.1`–`5.8` | Legacy                                  |
| 5.0             | `5.0`    | `>= 5.5` | `>= 5.0` | Archived                                 |

The Laravel floor is inferred from the framework APIs each branch actually
calls, since the `composer.json` constraints were never updated:

- Package auto-discovery (`extra.laravel.providers`) → Laravel `>= 5.5`
- `Storage::disk()->temporaryUrl()` → Laravel `>= 5.4`
- `BelongsTo::getOwnerKeyName()` → Laravel `>= 5.8`
- `Relation::getRelationExistenceQuery()` → Laravel `>= 5.5`
- `Paginator::withQueryString()` → Laravel `>= 7.0`
- `Form::` helpers (`laravelcollective/html`) on `5.0`–`5.4` → Laravel `5.x` only
- `array_except()` / `str_slug()` on `5.3`, `5.4` and `5.7` → removed in Laravel
  6, so those branches have an upper bound as well as a floor. Branch `5.7`
  combines that ceiling with `getOwnerKeyName()`, which arrives in 5.8, leaving
  a single supported Laravel version

## Installation

```bash
composer require csgt/crud
```

The service provider is registered through package auto-discovery. To publish
the translations:

```bash
php artisan vendor:publish --provider="Csgt\Crud\CrudServiceProvider" --tag=lang
```

## Usage
To create a new CRUD, you may use the `php artisan make:crud ExampleController` command.  This will set up a boilerplate 
```
public function setup(Request $request)
    {
        //Required. Set the model to render
        $this->setModel(new Model);
        $this->setTitle('Title');
        //This will render an extra button next to the Add button
        $this->setExtraAction(['url' => '/module/import', 'title' => 'Importar']);
        //Render the breadcrumb according to BS version
        $this->setBreadcrumb([
            ['url' => '', 'title' => 'Catálogos', 'icon' => 'fa fa-book'],
            ['url' => '', 'title' => 'Titulo', 'icon' => 'fa fa-university'],
        ]);
        //Set columns to show
        $this->setField(['name' => 'Nombre', 'field' => 'name']);
        $this->setField(['name' => 'Descripción', 'field' => 'descripcion']);

        //Set combobox field
        $this->setField([
            'name'       => 'Protocol',
            'field'      => 'protocol_id',
            'type'       => 'combobox',
            'collection' => Protocol::select('id', 'name')->get(),
        ]);

 
        //Campo Multi:
        $this->setField(['name' => 'Requirements', 'type' => 'multi', 'field' => 'relationName']);
        requiere en el modelo, crear la relation belongsToMany relationName
        requiere en el modelo, crear el método fetchRelationName
        opcional, si la columna para mostrar en el show, no se llama 'name'
        crear el método fetchRelationNameColumn y retorne el campo deseado.

        //Set hidden variables to append to inserts and updates
        $this->setHidden(['field' => 'client_id', 'value' => 1]);

        //Set extra button for each row in the Crud
        $this->setExtraButton([
            'url'    => '/configuration/sensors?gateway_id={id}',
            'icon'   => 'fa fa-thermometer',
            'title'  => 'Sensors',
            'class'  => 'btn-warning',
        ]);

        //Set permissions using the ['edit' => true, 'create' => true, 'delete' => true] syntax
        $this->setPermissions(Cancerbero::crudPermissions('module'));
    }
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
recounted when at least one filter is actually applied. Filters for fields with
`type => 'date'` use the browser's native date picker and submit an ISO date
value (`YYYY-MM-DD`).

## Configuration

Publish the config with `php artisan vendor:publish --tag=config`.

| Key | Default | Meaning |
| --- | ------- | ------- |
| `stateDuration` | `0` | Seconds DataTables keeps the saved listing state — search, column filters, sort and page — in the browser's localStorage. `0` means it never expires. DataTables' own default is 7200, which silently discarded the saved search after two hours. |
| `extensiones_permitidas` | `null` | Whitelist of extensions accepted when uploading `file` and `image` fields, lowercase and without the dot. `null` applies no restriction, which is the historical behaviour. Set an array to enable it, for example `['jpg', 'png', 'pdf']`. |
| `max_page_length` | `null` | Largest page size `data()` will honour from the client. `null` applies no limit, which is the historical behaviour, and the `length` the browser sends is used as-is. |

`stateDuration` can be overridden for a single controller with
`$this->setStateDuration($seconds)`.

Each listing table is rendered with a unique DOM id derived from the request
path, so two CRUD listings on the same page no longer share, and overwrite, one
another's saved state.

## Server-side permissions

`$this->permissions` is enforced on every write action, not merely read by the
index view to hide buttons. `create()`, `edit()`, `store()`, `update()` and
`destroy()` return `403` when the matching flag is false.

```php
$this->setPermissions(['create' => true, 'update' => false, 'destroy' => false]);
```

**This changed.** The routes used to be reachable by a direct request whatever
the flags said, so the buttons were hidden but the endpoints were not. The three
flags default to `false`, so an application that never called
`setPermissions()` had every write action open and will now receive a `403` on
all of them. Declare the permissions explicitly before upgrading.

## Continuous integration

`.github/workflows/ci.yml` lints the whole `src` tree on PHP 7.2, 7.4 and 8.3,
and runs the suite on PHP 8.1 and 8.3, on every push and pull request. The dev
dependencies (phpunit `^9.6`, illuminate `^8`) need PHP 7.3 or newer, which is
why the test matrix starts above the lint one.

Other branches carry the same workflow with the PHP matrix each one actually
supports. It replaces a `.travis.yml` that had stopped running: Travis ended
free open-source builds in 2021, and the file still listed PHP 5.3 to 5.6 plus
hhvm and invoked a `phpunit` that was never a dependency of most branches.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The suite covers the methods that build the listing query, asserting on the SQL
and the bindings they produce. It needs Eloquent but never a database server:
no query is executed. PHPUnit, `illuminate/database` and `illuminate/routing`
are `require-dev` only, so nothing changes for consumers of the package.

## Upgrade from version 6 to version 8 or from 5.6 to 5.9

Add the following includes:

```
use Illuminate\Http\Request;
use Csgt\Cancerbero\Facades\Cancerbero;
```

Rename the `__construct` method to:

```
public function setup(Request $request)
```

Call the `setPermissions` directly

```
$this->setPermissions(Cancerbero::crudPermissions('module'));
```

Remove all references to `Crypt::encrypt` and `Crypt::decrypt` if any methos were overriden.
Remove all references to `validationRulesMessages` or `reglasmensaje`

Remove the enclosing middleware. It is now unnecessary.

```
$this->middleware(function ($request, $next) {

    return $next($request);
});
```
