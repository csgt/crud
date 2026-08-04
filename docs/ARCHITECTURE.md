# Architecture

Branch `5.5` — package version `5.5`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md` for
what you are allowed to change.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields, in Spanish-named methods (`setModelo`, `setCampo`,
`setTitulo`, ...). From that declaration the package renders the listing, serves
it to DataTables over AJAX, renders the edit/create form, validates and
persists the input, and registers the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` route |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: DataTables config, column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config (encryption of ids, ...) |
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
                                  │ joins, fixed wheres, column filters,
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
Unlike the newer branches, `create()` does not delegate to `edit()`: it is its
own method with a duplicated body (`$data = null` instead of a model lookup).

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
  'isforeign'     => true,                // resolve through a relation
  'searchable'    => true,
  ...                                     // default, class, decimales,
]                                        // filepath, target, utc, ...
```

Three shapes of `campo` exist, and most of the query code branches on which one
it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre` (`isforeign => true`) | eager load + `whereHas` + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`tipo => 'multi'`) | `belongsToMany`, eager loaded per page |

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
   ├── applyFiltersToQuery( filters[] from the view )     per-column filters
   ├── recordsFiltered = (clone $query)->count()          only if a filter applied
   │
   ├── applyOrderToQuery( DataTables order )              plain / raw / subquery
   ├── offset(start)->limit(length)->get()                ONE page
   └── $items->load( multi relations )                    one extra query, no N+1
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

## Column filters

The listing has no global search box. The view renders a list of
column/value rows and posts them as `filters[]`:

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
tag-free label.

## Ordering

DataTables sends column indices; `getCamposOrden()` maps them back to declared
fields. Then:

- a plain column → `orderBy`
- a raw expression → `orderByRaw`, so it is not quoted as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation → falls back to a plain `orderBy`, never throws

`getRelationExistenceQuery()` only exists on Eloquent from Laravel 5.5 onward.
This branch's floor is Laravel 5.5 (see `README.md`), so the method is called
directly and unconditionally — there is no `method_exists()` guard around it,
unlike on the older `5.3`/`5.4` branches where the floor is lower and the guard
is required.

## Persistence

`store()` delegates to `update($request, 0)`, so one method handles both create
and update. It filters out `noGuardar` (always includes `_token`), merges
`camposHidden`, then per field type: parses dates with Carbon and applies the
`utc` shift, moves uploaded `file`/`image` uploads, puts `securefile` uploads on
their disk and deletes the previous one, and detaches/attaches `multi`
relations after the model is saved. There is no `bool` cast in `update()` on
this branch.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
