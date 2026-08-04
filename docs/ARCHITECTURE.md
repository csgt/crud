# Architecture

Branch `5.8` — package version `5.8`. See `README.md` for the PHP and Laravel
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

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` and `detail` routes |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: DataTables 1.x config (`sDom` layout), column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config |
| `tests/` | PHPUnit suite over the query-building methods |

## Request lifecycle

```
Route::resource('clients', ClientsController::class)
        │
        │  ResourceRegistrar adds two routes to the seven standard ones:
        │      POST clients/data          -> data()
        │      GET  clients/{id}/detail   -> detail()   (not implemented on
        │                                                this branch; declaring
        │                                                it is up to your
        │                                                controller)
        ▼
GET /clients ──────────► index()
                          │ builds the column metadata for the view
                          └─► view csgtcrud::index
                                  │ DataTables 1.x boots with serverSide: true
                                  ▼
                        POST /clients/data ──────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ left joins, fixed wheres, column filters,
                                  │ order, offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clients/create ───► create()  ─────────────► view csgtcrud::edit
GET /clients/{id}/edit ► edit($request, $aId) ──► view csgtcrud::edit
POST /clients ─────────► store() ─► update($request, 0)
PUT  /clients/{id} ────► update($request, $aId)
DELETE /clients/{id} ──► destroy($request, $aId)
```

Note: on this branch `create()` does **not** delegate to `edit()`. It
duplicates the same view-building steps (resolve edit fields, fill combos,
build the breadcrumb, render `csgtcrud::edit`) with `$data = null` instead of a
found model. This is different from the newer branches, where `create()` is a
thin wrapper around `edit()`.

Every action reads `$this->fields`/`$this->model`/etc., which your subclass
populates in its own `__construct()` before any action runs — `CrudController`
itself declares no constructor, so there is no shared setup hook to override on
this branch.

## Field metadata

`setField()` is the heart of the package. Each call validates the incoming keys
and normalises them into one array with a fixed shape, appended to `$fields`:

```php
[
  'field'                  => 'country.name',   // as declared
  'name'                   => 'Country',         // column header
  'alias'                  => 'country__name',   // safe key derived from the field
  'campoReal'              => 'name',            // column without the relation
  'type'                   => 'string',          // drives rendering and casting
  'show'                   => true,              // appears in the listing
  'editable'               => true,              // appears in the form
  'isforeign'              => true,              // resolve through a relation
  'searchable'             => true,               // always true, not configurable
  ...                                            // default, class, decimals,
]                                                 // filepath, target, ...
```

Three shapes of `field` exist, and most of the query code branches on which one
it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `name` | `select` on the model's table |
| relation column | `country.name` (`isforeign => true`) | eager load via `with()` + `whereHas` + correlated subquery for ordering |
| raw expression | contains `)` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`type => 'multi'`) | `belongsToMany`-style relation, eager loaded per page |

The `getCamposShow()`/`getCamposEdit()`/`get*Fields()` helpers are just
filters over that list; see `METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->model->newQuery()
   ├── select( local fields )                            getSelect + getLocalShowFields
   ├── with( relation => fn($q) => addSelect(...) )       getForeignShowFields
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                      $leftJoins (setLeftJoin)
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

The rendering loop then walks `getFieldOrder()` per row and pulls each value:
the primary key becomes `DT_RowId` (encrypted with `Crypt::encrypt`), relation
columns are read with `data_get`, `multi` fields are imploded from the
eager-loaded relation, and `securefile` fields are turned into temporary URLs.

Two invariants hold the performance in place:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination, no Collection `filter`/`sortBy`/`splice`.
2. **No query inside the row loop.** Anything a row needs is eager loaded before
   the loop starts (relations via `with()`, `multi` fields via `$items->load()`).

## Column filters

The listing has no global search box. The view renders a list of
column/value rows and posts them as `filters[]`:

```json
{ "filters": [ { "column": 0, "value": "acme" },
               { "column": 1, "value": "mex" } ] }
```

`column` is an index into `getCamposShow()`. `applyFiltersToQuery()` validates
each entry (integer index via `filter_var(..., FILTER_VALIDATE_INT)`, known
column, non-empty trimmed value) and hands the valid ones to
`applyColumnFilter()`, which picks the clause by field shape: `whereHas` for a
relation or a `multi` field, `whereRaw` for an expression, plain `where`
otherwise. The alias is stripped first with `stripAlias()`, since `AS alias` is
only valid in a `SELECT`.

The view builds the filter `<select>` from `filterColumns`, which `index()`
gets from `getFilterColumns()`: index plus a tag-free label
(`strip_tags($column['name'])`).

## Ordering

DataTables sends column indices; `getFieldOrder()` maps them back to declared
fields (declared field names in `show` order, with `___id___` appended). Then:

- a plain column → `orderBy`
- an expression (contains `(` or a space) → `orderByRaw`, so it is not quoted
  as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation (the method does not exist on the model) → falls back to
  a plain `orderBy`, never throws

## Persistence

`store()` delegates to `update($request, $aId = 0)`, so one method handles
both create and update. It strips `ignoreFields` (`_token` always included)
with `Arr::except`, merges `hiddenFields`, then per field type: parses dates
with `Carbon::createFromFormat`, moves uploaded `file`/`image` values with
`$file->move()`, puts `securefile` uploads on their configured disk with
`Storage::disk()->putFile()` and deletes the previous one, and detaches/attaches
`multi` relations after the model is saved (`create()`/`update()` then
`attach()`, or `detach()` then `attach()` on update).

There is no `bool` casting step on this branch — `type => 'bool'` is a valid
field type accepted by `setField()`, but `update()` passes its raw request
value straight through like any other field.

## Extending

The intended extension point is your own subclass: declare fields and filters
in `__construct()`, and override a lifecycle action only when you must, calling
`parent::` where possible. The private helpers are private on purpose — see
`RULES.md` before changing any of them.
