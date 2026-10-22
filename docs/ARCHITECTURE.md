# Architecture

Branch `5.3` — package version `5.3`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md` for
what you are allowed to change.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields, in Spanish-named methods (`setModelo`, `setCampo`,
`setTitulo`, ...). From that declaration the package renders the listing, serves
it to DataTables over AJAX, renders the edit/create form (built with
`laravelcollective/html` `Form::` helpers), persists the input, and registers
the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD.

This is the oldest maintained branch. `src/CrudController.php` ends with a
large `/* ... */` comment block (roughly the last half of the file) containing
an older, `static`-method version of the class (`getData()`, `setTabla()`,
`setSlug()`, a second `setCampo()`, ...). It is dead code kept for reference —
none of it executes, and it is not part of this document. Do not treat any
method name that only appears inside that comment as part of the API.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters (plus a large commented-out legacy block at the end) |
| `src/CrudServiceProvider.php` | Registers views, translations, and swaps the resource registrar. There is no `make:crud` command on this branch (`src/Console/` does not exist). |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` route |
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
        │  (there is no "detail" route, and no show() action on this branch)
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
POST /clientes ─────────► store()
PUT  /clientes/{id} ────► update($id)
DELETE /clientes/{id} ──► destroy($id)
```

Every action calls the controller's own setup first (`__construct` on this
branch), so the field declaration is always in place before anything reads it.
`create()` does not delegate to `edit()`: it is its own method with a
duplicated body (`$data = null` instead of a model lookup). `store()` does not
delegate to `update()` either — unlike the newer branches, the two are
independent methods here. Ids are always decrypted with `Crypt::decrypt()` in
`edit()`, `update()` and `destroy()` — there is no `show()` action and no
config flag to disable the decryption on this branch.

`index()` takes no `Request` parameter (`public function index()`), unlike
every other lifecycle action. There is also no `getQueryString()` helper on
this branch: `edit()` and `create()` hardcode `'nuevasVars'` to an empty
string instead of preserving the current query string across a save.

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
  'tipo'          => 'string',            // drives rendering
  'show'          => true,                // appears in the listing
  'editable'      => true,                // appears in the form
  'searchable'    => true,                // false for a raw expression
  ...                                     // default, class, decimales,
]                                        // filepath, target, ...
```

There is no `isforeign` key and no `multi` field type on this branch. Only two
shapes of `campo` exist, and the query code branches on which one it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre` (any field containing a `.`) | eager load + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | left as-is in the select (no `DB::raw` wrapping, see `getSelect()` below), `orderByRaw` for ordering, and marked `searchable => false` |

`getCamposShowForeign()` treats **every** field whose `campo` contains a `.`
as a relation column — there is no `isforeign` flag gating it, and no check for
a quoted or parenthesised expression either (the `5.4` branch added a `"`
check; this branch does not have it).

The `getCamposXxx()` helpers are just filters over that list; see `METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->modelo->select( local fields + expressions )        getSelect + getCamposShowMine
   ├── with( relation => fn($q) => addSelect(...) )         getCamposShowForeign
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                        setLeftJoin
   ├── where / whereRaw                                     setWhere, setWhereRaw, fixed filters
   │
   ├── recordsTotal = (clone $query)->count()
   │
   ├── applySearchToQuery( DataTables global search )       if search.value is not empty
   ├── recordsFiltered = (clone $query)->count()            only if a search term was applied
   │
   ├── applyOrderToQuery( DataTables order )                plain / raw / subquery
   └── skip(start)->take(length)->get()                     ONE page
```

Unlike every other branch, `getSelect()` does **not** wrap fields in `DB::raw`
— it returns the plain strings. There is also no `setWhereIn()` on this branch:
only `setWhere()` and `setWhereRaw()` exist. `setJoin()` exists as a setter but
`$this->joins` is never read inside `data()`; it has no observable effect (the
same is true on every other branch — `leftJoins`, not `joins`, drives the
query).

The rendering loop then walks `getCamposOrden()` per row and pulls each value:
the primary key is re-encrypted with `Crypt::encrypt()` and appended as the
last (unkeyed) element of `$cols` — this branch does not use the `DT_RowId`
key that later branches use, relation columns are read with `data_get`, and
everything else is read directly off the row. There is no `securefile`
temporary-URL handling and no `multi` field on this branch — neither feature
exists here.

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

This branch's floor is Laravel 5.1 (see `README.md`), which predates
`getRelationExistenceQuery()` by several major versions, so the
`method_exists()` guard is load-bearing here, not defensive boilerplate.

## Persistence

`store()` and `update()` are independent, minimal methods on this branch:
both strip `noGuardar` (always includes `_token`, via the global
`array_except()` helper) and save. Neither parses dates, moves file uploads,
merges hidden fields, nor touches `multi` relations — none of that exists yet.
The `store()` method carries a `//Aqui falta procesar fechas, files, images,
etc` comment acknowledging the gap. Do not "fix" this by adding that behaviour
here: per `RULES.md`, a behaviour change belongs on a new version branch, and
the newer branches (`5.4`+) already have it.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
