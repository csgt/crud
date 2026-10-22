# Architecture

Branch `5.4` — package version `5.4`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md` for
what you are allowed to change.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields, in Spanish-named methods (`setModelo`, `setCampo`,
`setTitulo`, ...). From that declaration the package renders the listing, serves
it to DataTables over AJAX, renders the edit/create form (built with
`laravelcollective/html` `Form::` helpers), validates and persists the input,
and registers the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` route |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: DataTables config, column renderers, the global search box |
| `src/resources/views/edit.blade.php` | Edit/create form, `Form::` helper per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config |
| `tests/` | PHPUnit suite over the query-building methods |

## Request lifecycle

```
Route::resource('clientes', ClientesController::class)
        │
        │  ResourceRegistrar adds one route to the seven standard ones:
        │      POST clientes/data          -> data()
        │  (there is no "detail" route on this branch)
        ▼
GET /clientes ─────────► index()
                          │ builds the column metadata for the view
                          └─► view csgtcrud::index
                                  │ DataTables boots with serverSide: true
                                  ▼
                        POST /clientes/data ─────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ joins, fixed wheres, global search,
                                  │ order, offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clientes/create ───► create()  ─────────────► view csgtcrud::edit
GET /clientes/{id}/edit ► edit($id) ─────────────► view csgtcrud::edit
POST /clientes ─────────► store()   ─► update($request, 0)
PUT  /clientes/{id} ────► update($id)
DELETE /clientes/{id} ──► destroy($id)
```

Every action calls the controller's own setup first (`__construct` on this
branch), so the field declaration is always in place before anything reads it.
`create()` does not delegate to `edit()`: it is its own method with a
duplicated body (`$data = null` instead of a model lookup). Ids are always
decrypted with `Crypt::decrypt()` in `show()`, `edit()`, `update()` and
`destroy()` — there is no config flag to disable it on this branch.

## Field metadata

`setCampo()` is the heart of the package. Each call validates the incoming keys
against an allow-list and normalises them into one array with a fixed shape,
appended to `$campos`:

```php
[
  'campo'         => 'pais.nombre',       // as declared
  'nombre'        => 'Pais',              // column header
  'alias'         => 'pais__nombre',      // safe key for the JSON/DOM
  'campoReal'     => 'nombre',            // column without the relation
  'tipo'          => 'string',            // drives rendering and casting
  'show'          => true,                // appears in the listing
  'editable'      => true,                // appears in the form
  'searchable'    => true,
  ...                                     // default, class, decimales,
]                                        // filepath, target, ...
```

There is no `isforeign` key and no `multi` field type on this branch. Only two
shapes of `campo` exist, and the query code branches on which one it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre` (any field containing a `.`) | eager load + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | `DB::raw` in the select, `orderByRaw` for ordering |

`getCamposShowForeign()` treats **every** field whose `campo` contains a `.`
(and no `"`) as a relation column — there is no `isforeign` flag gating it,
unlike the `5.5`+ branches.

The `getCamposXxx()` helpers are just filters over that list; see `METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->modelo->newQuery()
   ├── select( local fields + raw expressions )          getSelect + getCamposShowMine
   ├── with( relation => fn($q) => addSelect(...) )       getCamposShowForeign
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                      setLeftJoin
   ├── where / whereIn / whereRaw                         setWhere*, fixed filters
   │
   ├── recordsTotal = (clone $query)->count()
   │
   ├── applySearchToQuery( DataTables global search )     if search.value is not empty
   ├── recordsFiltered = (clone $query)->count()          only if a search term was applied
   │
   ├── applyOrderToQuery( DataTables order )              plain / raw / subquery
   ├── offset(start)->limit(length)->get()                ONE page
   └── (no multi relations to eager load post-fetch: the type does not exist)
```

The rendering loop then walks `getCamposOrden()` per row and pulls each value:
the primary key becomes `DT_RowId` (re-encrypted with `Crypt::encrypt()`),
relation columns are read with `data_get`, everything else is read directly off
the row. There is no `securefile` temporary-URL handling and no `multi`
imploding in this loop — neither feature exists on this branch.

Two invariants hold the performance in place:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination, no Collection `filter`/`sortBy`/`splice`.
2. **No query inside the row loop.** Anything a row needs is eager loaded before
   the loop starts.

## Global search

This branch has no per-column filters. The listing keeps the DataTables global
search box, and `data()` reads `$request->search['value']`. When it is not
empty, `applySearchToQuery()` builds one `WHERE` group that `orWhereRaw`s
`LOWER(field) LIKE ?` over every field with `searchable => true`, skipping the
unique-id column. The alias is stripped first, since `AS alias` is only valid
in a `SELECT`. `recordsFiltered` is only recounted when a search term was
actually sent.

There is no `getFilterColumns()` and no `filters[]` payload on this branch —
those belong to `5.5` and later.

## Ordering

DataTables sends column indices; `getCamposOrden()` maps them back to declared
fields. Then:

- a plain column → `orderBy`
- a raw expression → `orderByRaw`, so it is not quoted as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, **but only when that method exists** on the
  installed Eloquent (Laravel 5.5+); on an older Eloquent `applyOrderToQuery()`
  falls back to ordering by the model's primary key instead, since there is no
  way to build the subquery
- an unknown relation → falls back to a plain `orderBy`, never throws

This branch's floor is Laravel 5.4 (see `README.md`), which predates
`getRelationExistenceQuery()`, so the `method_exists()` guard is load-bearing
here, not defensive boilerplate.

## Persistence

`store()` delegates to `update($request, 0)`, so one method handles both create
and update. It filters out `noGuardar` (always includes `_token`, via the
global `array_except()` helper) and merges `camposHidden`, then per field type:
parses dates with `Carbon::createFromFormat()` against a `d/m/Y` or
`d/m/Y H:i` input format, and moves uploaded `file`/`image` uploads. There is
no `securefile` type, no `utc` shift and no `multi` relation handling on this
branch — none of those exist here.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
