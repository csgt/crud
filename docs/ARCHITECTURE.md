# Architecture

Branch `master` — package version `8.0`. See `README.md` for the PHP and
Laravel bounds of this branch, `METHODS.md` for the method reference and
`RULES.md` for what you are allowed to change.

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
| `src/CrudServiceProvider.php` | Registers views and translations, swaps the resource registrar, and registers the `make:crud` command |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` and `detail` routes |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: DataTables 2.x config (`layout:` toolbar), column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `tests/` | PHPUnit suite over the query-building and field-metadata methods |

There is no `src/config/` directory on this branch: id encryption is gone (see
the `README.md` compatibility table, "Crypt: no" for `8.0`), so there is
nothing left to configure through a published config file.

## Request lifecycle

```
Route::resource('clients', ClientsController::class)
        │
        │  ResourceRegistrar adds two routes to the seven standard ones:
        │      POST clients/data          -> data()
        │      GET  clients/{id}/detail   -> detail()   (route is registered,
        │                                                but CrudController
        │                                                declares no detail()
        │                                                method; a subclass
        │                                                must add it)
        ▼
GET /clients ──────────► index()
                          │ builds the column metadata for the view
                          └─► view csgtcrud::index
                                  │ DataTables 2.x boots with serverSide: true
                                  ▼
                        POST /clients/data ──────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ left joins, fixed wheres, column filters,
                                  │ order, offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clients/create ───► create() ─► edit($request, null) ─► view csgtcrud::edit
GET /clients/{id}/edit ► edit($request, $id)  ───────────► view csgtcrud::edit
POST /clients ─────────► store() ─► update($request, 0)
PUT  /clients/{id} ────► update($request, $id)
DELETE /clients/{id} ──► destroy($request, $id)
```

Every action calls `$this->setup($request)` first. `setup(Request $request)`
is the lifecycle hook a subclass overrides (there is no `__construct` on this
branch); the base implementation in `CrudController::setup()` just
`abort(400, ...)`s, so an application that forgets to override it fails loudly
instead of rendering an empty CRUD.

## Field metadata

`setField()` is the heart of the package. Each call validates the incoming
keys and normalises them into one array with a fixed shape, appended to
`$fields`:

```php
[
  'name'       => 'Country',                       // column header
  'field'      => 'country.name AS country_name',  // as declared
  'alias'      => 'country__name',                 // safe key for the DOM
  'campoReal'  => 'name',                           // column without the relation
  'type'       => 'string',                         // drives rendering and casting
  'show'       => true,                              // appears in the listing
  'editable'   => true,                              // appears in the form
  'isforeign'  => true,                              // resolve through a relation
  'searchable' => true,
  ...                                                // default, class, decimals,
]                                                    // filepath, target, utc, ...
```

`isforeign` defaults to `true`, so any declared field that looks like a
relation column (contains a `.`) is treated as one unless you explicitly set
`isforeign => false`.

Three shapes of `field` exist, and most of the query code branches on which
one it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `name` | `select` on the model's table |
| relation column | `country.name AS country_name` (`isforeign => true`) | eager load + correlated subquery for ordering, `whereHas` for filtering |
| raw expression | `CONCAT(name, id) AS composed` | included via `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`type => 'multi'`) | `belongsToMany`-style relation, eager loaded once per page |

The `getXxxFields()` helpers are just filters over that list; see
`METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->model->newQuery()
   ├── select( local fields )                            getSelect(getLocalShowFields())
   ├── with( relation => fn($q) => addSelect(...) )       getForeignShowFields
   ├── addSelect( owner/foreign key for each relation )
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                      setLeftJoin
   ├── where / whereIn / whereRaw                         setWhere*, fixed filters
   │
   ├── recordsTotal = (clone $query)->count()
   │
   ├── applyFiltersToQuery( filters[] from the view )      per-column filters
   ├── recordsFiltered = filtersApplied
   │       ? (clone $query)->count() : $recordsTotal
   │
   ├── applyOrderToQuery( DataTables order )               plain / raw / subquery
   ├── offset(start)->limit(length)->get()                 ONE page
   └── $items->load( multi relations )                     one extra query, no N+1
```

The rendering loop then walks `getFieldOrder()` per row and pulls each value:
the primary key becomes `DT_RowId`, relation columns are read with
`data_get`, `multi` fields are imploded from the eager-loaded relation, and
`securefile` fields are turned into temporary URLs (`Storage::disk(...)->
temporaryUrl(...)`).

Two invariants hold the performance in place:

1. **Nothing is filtered or sorted in memory.** No `->get()` before the
   pagination, no Collection `filter`/`sortBy`/`splice`.
2. **No query inside the row loop.** Anything a row needs (relation data,
   `multi` relations) is eager loaded before the loop starts.

## Column filters

The listing has no DataTables global search box (`searching: false` in
`index.blade.php`). The view renders a list of column/value rows and posts
them as `filters[]`:

```json
{ "filters": [ { "column": 0, "value": "acme" },
               { "column": 1, "value": "mex" } ] }
```

`column` is an index into `getShowFields()`. `applyFiltersToQuery()` validates
each entry (integer index via `filter_var(..., FILTER_VALIDATE_INT)`, known
column, non-empty trimmed value) and hands the valid ones to
`applyColumnFilter()`, which picks the clause by field shape: `whereHas` for a
`multi` field or a relation column, `whereRaw` for an expression (after
stripping its alias), plain `where` otherwise. The value arrives already
wrapped in `%...%`.

Rows can be added and removed in the UI; the current set survives a
DataTables state save/reload through `stateSaveParams`/`stateLoadParams`.

## Ordering

DataTables sends column indices; `getFieldOrder()` maps them back to declared
field expressions (with `___id___` appended for the row-id column). Then, in
`applyOrderToQuery()`:

- a plain column → `orderBy`
- a raw expression (contains `(` or a space) → `orderByRaw`, so it is not
  quoted as an identifier
- a relation column → a correlated subquery built with
  `Relation::getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation (the method doesn't exist on the model) → falls back to
  a plain `orderBy`, never throws

## Persistence

`store()` delegates to `update($request, 0)`, so one method handles both
create and update. It filters out `ignoreFields` (`_token` is always ignored),
merges `hiddenFields`, then per declared field: shifts `date`/`datetime`
values with `Carbon` when `utc => true`, moves uploaded `file`/`image` uploads
into `public_path().filepath`, puts `securefile` uploads on their configured
disk with `Storage::disk($filedisk)->putFile(...)` and deletes the previous
file, and pulls `multi` values out of `$fields` to attach/detach after the
model is saved (`update` also detaches before re-attaching, so a save always
reflects the posted set).

## Extending

The intended extension point is your own subclass: declare fields, joins,
wheres, permissions and buttons in `setup(Request $request)`, and override a
lifecycle action only when you must, calling `parent::` where possible. The
private helpers are private on purpose — see `RULES.md` before changing any of
them.
