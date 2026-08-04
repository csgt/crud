# Architecture

Branch `5.6.bs4` — package version `5.6 (bs4 variant)`. See `README.md` for the
PHP and Laravel bounds of this branch, `METHODS.md` for the method reference
and `RULES.md` for what you are allowed to change.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields. From that declaration the package renders the
listing, serves it to DataTables over AJAX, renders the edit/create form,
validates and persists the input, and registers the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD.

**This branch's public API is Spanish**, same as `5.6`: `setModelo()`,
`setCampo()` with the keys `campo`/`nombre`/`tipo`, `setTitulo()`,
`setPermisos()`. This branch is `5.6` with the Bootstrap 4 look, reworked
date/datetime/time pickers, UTC handling on fields, and it is **not published
as its own package version** (see `README.md`, "About this branch").

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` and `detail` routes |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: Bootstrap 4 + DataTables config (built server-side in `index()`), column renderers, the native DataTables search box |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type, native date/datetime/time pickers |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config |
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
                          │ builds the column metadata AND the full DataTables
                          │ `options` array (ajax url, language strings, order,
                          │ buttons) in PHP
                          └─► view csgtcrud::index
                                  │ DataTables boots with serverSide: true
                                  ▼
                        POST /clientes/data ──────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ joins, fixed wheres, global search, order,
                                  │ offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clientes/create ───► create() ─► view csgtcrud::edit (data = null)
GET /clientes/{id}/edit ► edit($id) ─► view csgtcrud::edit
POST /clientes ─────────► store() ─► update($id = 0)
PUT  /clientes/{id} ────► update($id)
DELETE /clientes/{id} ──► destroy($id)
```

`CrudController` itself declares no constructor: the field declaration lives in
`__construct()` of your own subclass (`setModelo()`, `setCampo()`, ...), which
Laravel calls before any action runs, so the fields are always in place before
an action reads them.

Record ids are **always** encrypted/decrypted with `Crypt::encrypt()` /
`Crypt::decrypt()` on this branch (`show()`, `edit()`, `update()`, `destroy()`,
`data()`'s `DT_RowId`) — there is no `csgtcrud.usar_encripcion` config toggle
here, unlike `5.6` and `5.7`.

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
  'filedisk'   => 'local',                        // securefile / file storage disk
  'utc'        => false,                          // date/datetime/time UTC handling
  'searchable' => true,
  ...                                             // default, class, decimales,
]                                                 // filepath, target, ...
```

Compared to `5.6`, this branch's `setCampo()` additionally accepts `filedisk`
and adds `time` to the list of allowed types (`string`, `multi`, `numeric`,
`date`, `datetime`, `time`, `bool`, `combobox`, `password`, `enum`, `file`,
`image`, `textarea`, `url`, `summernote`, `securefile`), and `securefile` is
fully wired up: `update()` uploads the file to `filedisk`/`filepath` and
deletes the previous one, which `5.6` does not do.

Three shapes of `campo` exist, and most of the query code branches on which one
it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre AS pais_nombre` (`isforeign => true`) | eager load + `whereHas` + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`tipo => 'multi'`) | `belongsToMany`, eager loaded per page |

The `getCamposShow()` / `getCamposShowMine()` / `getCamposShowForeign()` /
`getCamposShowMulti()` / `getCamposOrden()` helpers are just filters over that
list; see `METHODS.md`. Unlike `5.6`, this branch does have a dedicated
`getCamposShowMulti()` helper — `5.6` computes that list inline instead.

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
   ├── applySearchToQuery( DataTables global search )    only when non-empty
   ├── recordsFiltered = (clone $query)->count()         only if search applied
   │
   ├── applyOrderToQuery( DataTables order )             plain / raw / subquery
   ├── offset(start)->limit(length)->get()               ONE page
   └── $items->load( multi relations )                   one extra query, no N+1
```

The rendering loop then walks `getCamposOrden()` per row and pulls each value:
the primary key becomes `DT_RowId` (encrypted), relation columns are read with
`data_get`, and `securefile` fields are turned into temporary URLs. Unlike
`5.6`, this branch does not implode `multi` values into the row output —
`getCamposShowMulti()`/`multiColumns` is computed but its result is not read
into `$cols` in `data()`'s render loop, only used to build `$multiRelations`
for the eager load.

Two invariants hold the performance in place:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination, no Collection `filter`/`sortBy`/`splice`.
2. **No query inside the row loop.** Anything a row needs is eager loaded before
   the loop starts.

`setJoin()` records a relation name into `$this->joins`, but `data()` never
reads `$this->joins` — only `$this->leftJoins` (populated by `setLeftJoin()`)
is applied to the query. Calling `setJoin()` on this branch has no effect on
the listing.

## Global search

This branch has **no per-column filters**. The DataTables global search box
(native, rendered by DataTables itself, not a custom view element) sends its
value as `search.value` in the AJAX request. When non-empty, `data()` calls
`applySearchToQuery($query, $search['value'], $columns, $foreigns)`, which
wraps the whole thing in one grouped `where`:

- every local shown column (`getCamposShowMine()`) is matched with
  `orWhereRaw('LOWER(column) LIKE ?', [...])`, case-insensitively
- every relation grouped by `getCamposShowForeign()` is matched with
  `orWhereHas($relation, ...)`, itself matching any of that relation's shown
  columns

The `___id___` alias is skipped. There is no `getFilterColumns()` and no
`filters[]` payload on this branch — the listing view has no filter-row UI at
all, it relies entirely on DataTables' own global search input.

## Ordering

DataTables sends column indices; `getCamposOrden()` maps them back to declared
fields. Then:

- a plain column → `orderBy`
- a raw expression → `orderByRaw`, so it is not quoted as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation → falls back to a plain `orderBy`, never throws

`setOrderBy()` feeds `$this->orders`, which `index()` turns directly into the
DataTables `options['order']` array (`foreach ($this->orders as $col => $order)
{ $options['order'][] = [$col, $order]; }`), so on this branch the default
sort order is baked into the DataTables initialisation options rather than
only rendered as a hint for the view to consume.

## Persistence

`store()` delegates to `update($id = 0)`, so one method handles both.
`update()` first builds Laravel validation `$rules` from every editable
field's `reglas` and calls `$request->validate($rules)` — a validation step
`5.6` and `5.7` do not have. It then strips `noGuardar` fields with
`Illuminate\Support\Arr::except()`, merges `camposHidden`, and per field type:
parses dates with `Carbon::createFromFormat()`, moves `file`/`image` uploads,
puts `securefile` uploads on their `filedisk` and deletes the previous one,
and detaches/attaches `multi` relations. There is no `bool` cast on this
branch.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct()`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
