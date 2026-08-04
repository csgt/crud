# Architecture

Branch `5.6` — package version `5.6`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md` for
what you are allowed to change.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields. From that declaration the package renders the
listing, serves it to DataTables over AJAX, renders the edit/create form,
validates and persists the input, and registers the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD.

**This branch's public API is Spanish**: the model setter is `setModelo()`,
fields are declared with `setCampo()` using the keys `campo`/`nombre`/`tipo`
(not `field`/`name`/`type`), and most getters read `getCampos...()`. See
`METHODS.md` for the exact names.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` and `detail` routes |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: Bootstrap 4 + DataTables config, column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config (encryption of ids, ...) |
| `tests/` | PHPUnit suite over the query-building methods |

## Request lifecycle

```
Route::resource('clientes', ClientesController::class)
        │
        │  ResourceRegistrar adds two routes to the seven standard ones:
        │      POST clientes/data          -> data()
        │      GET  clientes/{id}/detail   -> detail()   (not implemented on
        │                                                 this branch; declaring
        │                                                 it is up to your
        │                                                 controller)
        ▼
GET /clientes ──────────► index()
                          │ builds the column metadata for the view
                          └─► view csgtcrud::index
                                  │ DataTables boots with serverSide: true
                                  ▼
                        POST /clientes/data ──────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ joins, fixed wheres, column filters,
                                  │ order, offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clientes/create ───► create() ─► edit($id = null) ─► view csgtcrud::edit
GET /clientes/{id}/edit ► edit($id) ─────────────────► view csgtcrud::edit
POST /clientes ─────────► store() ─► update($id = 0)
PUT  /clientes/{id} ────► update($id)
DELETE /clientes/{id} ──► destroy($id)
```

`CrudController` itself declares no constructor: the field declaration lives in
`__construct()` of your own subclass (`setModelo()`, `setCampo()`, ...), which
Laravel calls before any action runs, so the fields are always in place before
an action reads them.

## Field metadata

`setCampo()` is the heart of the package. Each call validates the incoming keys
and normalises them into one array with a fixed shape, appended to `$campos`:

```php
[
  'campo'      => 'pais.nombre AS pais_nombre',  // as declared
  'nombre'     => 'Pais',                        // column header
  'alias'      => 'pais__nombre',                // safe key for the JSON/DOM
  'campoReal'  => 'nombre',                       // column without the relation
  'tipo'       => 'string',                       // drives rendering and casting
  'show'       => true,                           // appears in the listing
  'editable'   => true,                           // appears in the form
  'isforeign'  => true,                           // resolve through a relation
  'searchable' => true,
  'utc'        => false,
  ...                                             // default, class, decimales,
]                                                 // filepath, target, editClass, ...
```

Three shapes of `campo` exist, and most of the query code branches on which one
it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre AS pais_nombre` (`isforeign => true`) | eager load + `whereHas` + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`tipo => 'multi'`) | `belongsToMany`, eager loaded per page |

The `getCamposShow()` / `getCamposShowMine()` / `getCamposShowForeign()` /
`getCamposOrden()` helpers are just filters over that list; see `METHODS.md`.
There is no dedicated "show multiple fields" helper on this branch: `data()`
computes the eager-loaded `multi` relations inline with an
`array_filter`/`array_map` over `$this->campos` rather than through a named
method.

The `securefile` field type is accepted by `setCampo()`'s type list and rendered
with `Storage::disk($filedisk)->temporaryUrl()` in the listing, but `filedisk`
is **not** one of `setCampo()`'s allowed keys on this branch, and `update()` has
no upload handling for `securefile` (only `file`/`image` are handled). A
`securefile` field declared here can be shown but not usefully uploaded to.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->modelo->newQuery()
   ├── select( local fields + raw expressions )          getSelect + getCamposShowMine
   ├── with( relation => fn($q) => addSelect(...) )      getCamposShowForeign
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                     setLeftJoin
   ├── where / whereIn / whereRaw                        setWhere*, fixed filters
   │
   ├── recordsTotal = (clone $query)->count()
   │
   ├── applyFiltersToQuery( filters[] from the view )    per-column filters
   ├── recordsFiltered = (clone $query)->count()         only if a filter applied
   │
   ├── applyOrderToQuery( DataTables order )             plain / raw / subquery
   ├── offset(start)->limit(length)->get()               ONE page
   └── $items->load( multi relations )                   one extra query, no N+1
```

The rendering loop then walks `getCamposOrden()` per row and pulls each value:
the primary key becomes `DT_RowId`, relation columns are read with `data_get`,
`multi` fields are imploded from the eager-loaded relation, and `securefile`
fields are turned into temporary URLs.

Two invariants hold the performance in place:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination, no Collection `filter`/`sortBy`/`splice`.
2. **No query inside the row loop.** Anything a row needs is eager loaded before
   the loop starts.

`setJoin()` records a relation name into `$this->joins`, but `data()` never
reads `$this->joins` — only `$this->leftJoins` (populated by `setLeftJoin()`)
is applied to the query. Calling `setJoin()` on this branch has no effect on
the listing.

## Column filters

The listing has no global search box. The view renders a list of column/value
rows (`#crud-filter-rows` in `index.blade.php`, Bootstrap 4 markup) and posts
them as `filters[]`:

```json
{ "filters": [ { "column": 0, "value": "acme" },
               { "column": 1, "value": "mex" } ] }
```

`column` is an index into `getCamposShow()`. `applyFiltersToQuery()` validates
each entry (integer index, known column, non-empty value) and hands the valid
ones to `applyColumnFilter()`, which picks the clause by field shape: `whereHas`
for a relation or a `multi` field, `whereRaw` for an expression, plain `where`
otherwise. The alias is stripped first, since `AS alias` is only valid in a
`SELECT`.

`getFilterColumns()` is what feeds the `<select>` in the view: index plus a
tag-free label (`strip_tags($column['nombre'])`).

## Ordering

DataTables sends column indices; `getCamposOrden()` maps them back to declared
fields. Then:

- a plain column → `orderBy`
- a raw expression → `orderByRaw`, so it is not quoted as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation → falls back to a plain `orderBy`, never throws

`setOrderBy()` only feeds the DataTables `order` option rendered into the view
(`$orders`); the actual sort applied by `data()` always comes back from the
DataTables request (`$request->order`).

## Persistence

`store()` delegates to `update($id = 0)`, so one method handles both. It strips
`noGuardar` fields with Laravel's own `Request::except()`, merges
`camposHidden`, then per field type: parses dates with `Carbon::parse()`, moves
uploaded `file`/`image` uploads, and detaches/attaches `multi` relations
(removed from `$fields` with `Illuminate\Support\Arr::except()`) after the
model is saved. There is no `bool` cast and no `securefile` upload handling on
this branch.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct()`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
