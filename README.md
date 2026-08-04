# Crud

This package is used to generate cruds.

> **You are on branch `master` — package version `8.0` (tags `v8.0.x`).**

| Version  | BS  | Datatables | Methods | Views engine | Crypt | Constructor | UTC | Validation     |
| -------- | --- | ---------- | ------- | ------------ | ----- | ----------- | --- | -------------- |
| 5.5      | 3   | 1.x        | es      | blade        | yes   | yes         | no  | formvaldiation |
| 5.6      | 4   | 1.x        | es      | blade        | yes   | yes         | no  | formvaldiation |
| 5.9      | 4   | 1.x        | es      | blade        | yes   | no          | no  | laravel        |
| 6.0      | 4   | 1.x        | en      | blade        | yes   | yes         | no  | formvaldiation |
| 7.0 Beta | 4   | 1.x        | en      | vue          | yes   | yes         | no  | formvaldiation |
| 8.0      | 4/5 | 2.x        | en      | blade        | no    | no          | yes | laravel        |

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
| 5.7             | `5.7`    | `>= 7.1` | `>= 5.8` | Maintenance                              |
| 5.6             | `5.6`    | `>= 7.0` | `>= 5.5` | Legacy                                   |
| 5.5             | `5.5`    | `>= 7.0` | `>= 5.5` | Legacy                                   |
| 5.4             | `5.4`    | `>= 5.6` | `>= 5.4` | Legacy                                   |
| 5.3             | `5.3`    | `>= 5.5` | `>= 5.1` | Legacy                                   |
| 5.0             | `5.0`    | `>= 5.5` | `>= 5.0` | Archived                                 |

The Laravel floor is inferred from the framework APIs each branch actually
calls, since the `composer.json` constraints were never updated:

- Package auto-discovery (`extra.laravel.providers`) → Laravel `>= 5.5`
- `Storage::disk()->temporaryUrl()` → Laravel `>= 5.4`
- `BelongsTo::getOwnerKeyName()` → Laravel `>= 5.8`
- `Relation::getRelationExistenceQuery()` → Laravel `>= 5.5`
- `Paginator::withQueryString()` → Laravel `>= 7.0`
- `Form::` helpers (`laravelcollective/html`) on `5.0`–`5.4` → Laravel `5.x` only

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
