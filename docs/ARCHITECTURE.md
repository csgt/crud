# Architecture

**This branch is archived.** It is closed to new features and accepts fixes
only — see `RULES.md`. Use branch `5.3` or newer for anything else.

Branch `5.0` — package version `5.0`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md`
for what you are allowed to change.

---

## What the package does

There is no `CrudController` on this branch — that API starts on `5.3`. The
package is a single **static class**, `Csgt\Crud\Crud`, plus a Laravel facade
(`Csgt\Crud\Facades\Crud`, alias `Crud`). It is driven by a **table name**
through the query builder (`DB::table(...)`), not by an Eloquent model. Your
own controller calls the static setters, then `Crud::index()` /
`Crud::create()` / `Crud::store()` / `Crud::destroy()` to render the
listing/form and persist changes.

The service provider does not use package auto-discovery (there is no
`extra.laravel.providers` key in `composer.json`, and the Laravel version this
branch targets predates that mechanism), so it must be registered by hand in
`config/app.php`:

```php
'providers' => [
    Csgt\Crud\CrudServiceProvider::class,
],

'aliases' => [
    'Crud' => Csgt\Crud\Facades\Crud::class,
],
```

## File map

| File | Role |
|---|---|
| `src/Crud.php` | Everything: listing query, CRUD actions, field metadata, setters. A static class, not a controller. |
| `src/Facades/Crud.php` | The `Illuminate\Support\Facades\Facade` subclass; its accessor is `'crud'`. |
| `src/CrudServiceProvider.php` | Binds `'crud'` to `new Crud`, registers the `Crud` alias itself via `AliasLoader` (in addition to the facade alias you register by hand), loads views and translations. |
| `src/resources/views/index.blade.php` | Listing: DataTables config, column renderers. |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type. |
| `src/resources/lang/{en,es}/crud.php` | User-facing strings. |
| `src/config/csgtcrud.php` | Package config (`usar_encripcion`, ...). |
| `tests/CrudTest.php` | PHPUnit suite over the pure helper and the setters — see "What the tests cover" in `METHODS.md`. |

There is no `ResourceRegistrar.php`, no `Console/MakeCrudCommand.php`, and no
`data` route registration anywhere in the package: `Crud::getData()` exists
but nothing in this package wires a route to it. Your own application's route
file must point a route at it (typically `POST` to the listing URL, matching
whatever the Blade view's DataTables config posts to).

## Request lifecycle

There is no `Route::resource()` integration and no `setup()`/`__construct()`
hook: your own controller action calls the setters, then the matching static
method, every time it runs — there is no persistent state between requests
(`Crud`'s properties are `private static`, reset only by process restart, so
each request must fully re-declare everything it needs before reading it).

```
YourController::index()
    Crud::setTabla(...); Crud::setTablaId(...); Crud::setTitulo(...);
    Crud::setCampo(...); ...
    return Crud::index();
        │ aborts (dd()) if setTabla/setTablaId were not called
        └─► view csgtcrud::index
                │ DataTables boots with serverSide: true, posts to a route
                │ your application wired to Crud::getData()
                ▼
YourController::data()  (route + method you define yourself)
    Crud::setTabla(...); ...  (redeclared, same as above)
    return Crud::getData($showEdit);
                │ builds ONE query: selectRaw, leftJoins, fixed wheres,
                │ optional soft-delete filter, group by, DataTables global
                │ search, order, skip/take
                └─► JSON { draw, recordsTotal, recordsFiltered, data[] }

YourController::create($id = 0) -> Crud::create($id)
YourController::store($id = null) -> Crud::store($id)
YourController::destroy($id) -> Crud::destroy($id)
```

## Field metadata

`Crud::setCampo()` validates the incoming keys against `$allowed` and
normalises them into one array, appended to `$camposShow` (if `show` is true)
and/or `$camposEdit` (if `editable` is true) — a field can end up in one list,
the other, both, or (if `show` and `editable` are both false) neither:

```php
[
  'nombre'     => 'Pais',
  'campo'      => 'pais.nombre',       // as declared
  'alias'      => 'pais__nombre',      // used in the SELECT ... AS alias
  'campoReal'  => 'nombre',            // column without the relation prefix
  'tipo'       => 'string',
  'show'       => true,
  'editable'   => true,
  'searchable' => true,                // false for any field containing "("
  ...                                  // default, reglas, reglasmensaje,
]                                      // class, decimales, query, combokey, ...
```

Allowed keys: `campo`, `nombre`, `editable`, `show`, `tipo`, `class`,
`default`, `reglas`, `reglasmensaje`, `decimales`, `query`, `combokey`,
`enumarray`, `filepath`, `filewidth`, `fileheight`, `target`, `editClass`.
Unlike every later branch, there is **no `isforeign` key and no `multi` type**
on this branch — relation-style columns are declared exactly like local ones
(`pais.nombre`), and the query builds them with a plain `leftJoin` +
`selectRaw('pais.nombre AS pais__nombre')`, never Eloquent eager loading or
`whereHas`.

Field shapes:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `selectRaw('nombre AS nombre')` |
| joined column | `pais.nombre` | `setLeftJoin()` + `selectRaw('pais.nombre AS pais__nombre')`; `searchable` still true |
| raw expression | `CONCAT(nombre, id)` | `selectRaw` with a generated alias (`a<timestamp><count>`); `searchable` forced to `false` |
| `combobox` | `pais_id` with `tipo => 'combobox'` | resolved for the edit form via a raw `query` string run through `DB::select(DB::raw(...))`, not a model relation |

## The listing query

`Crud::getData($showEdit)` builds a single query and never materialises more
than one page:

```
DB::table($tabla)
   ├── selectRaw( implode(',', "campo AS alias" for each shown/edit field) )
   ├── addSelect( $tabla.$tablaId )               (no separate ___id___ alias)
   ├── leftJoin(...)                               $leftJoins
   ├── where / whereRaw / whereIn                  $wheres, $wheresRaw, $wheresIn
   ├── whereNull($tabla.'.deleted_at')              only if setSoftDelete(true)
   ├── groupBy(...)                                 $groups
   │
   ├── recordsTotal = $query->count()
   │
   ├── orderBy( DB::raw(unaliased column of the DataTables column index) )
   ├── one big orWhere() over every `searchable` column     the DataTables
   │                                                          GLOBAL search box
   ├── recordsFiltered = $query->count()             always recounted, even
   │                                                   with an empty search
   ├── skip(start)->take(length)->get()               ONE page
   └── row loop: encrypt $tablaId if configured, pass every other column through
```

There is **no per-column filter** on this branch — the "search by column,
multifilter" feature is a `5.9` addition. The single DataTables global search
box `OR`-matches every field whose `searchable` is true (i.e. every field
except a raw expression). There is also no correlated-subquery ordering for
relation columns: since a joined column is just another aliased `selectRaw`
column, `orderBy` sorts on whatever `campoReal`/expression the column index
maps to, resolved by re-deriving it from `$selects[$order['column']]` inside
`getData()` itself rather than through a dedicated helper.

Two invariants still hold, even without the later optimizations:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination.
2. **No relation eager loading exists to avoid** — there is no Eloquent
   relation on this branch, so there is no N+1 to eliminate in the first
   place; a `leftJoin` already returns every column in one query.

## Persistence

`Crud::store($id = null)` handles both create (`$id === null`) and update. It
walks `$camposEdit` (not the request) and, per field type: reads
`bool`/`combobox`/`date` (`d/m/Y` only)/`datetime` (`d/m/Y H:i`)/`password`
(hashed, and only overwritten on update if a new value was submitted)/`file`/
`image`/`securefile` from the global `Input` facade, builds a slug from the
columns registered with `setSlug()` (with a hardcoded accent-stripping table
and a `-<n>` suffix on collision), stamps `created_at`/`updated_at`, merges
`$camposHidden`, and inserts or updates through the query builder directly —
there is no Eloquent model in the loop at any point. `Crud::destroy($aId)`
either soft-deletes (`deleted_at`) or hard-deletes depending on
`setSoftDelete()`.

## Extending

There is nothing to subclass: `Crud` is a static class, so "extending" this
branch means calling its setters from your own controller action before every
call to `Crud::index()`/`create()`/`store()`/`destroy()`/`getData()`, since no
state survives between requests. This branch is archived — see `RULES.md`
before changing anything on it.
