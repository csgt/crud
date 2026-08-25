# Architecture

Branch `6.0` — package version `6.0`. See `README.md` for the PHP and Laravel
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
| `src/resources/views/index.blade.php` | Listing: DataTables config, column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | All user-facing strings |
| `src/config/csgtcrud.php` | Package config (encryption of ids, ...) |
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
                                  │ DataTables boots with serverSide: true
                                  ▼
                        POST /clients/data ──────► data()
                                  │ builds ONE query: select, eager loads,
                                  │ joins, fixed wheres, column filters,
                                  │ order, offset/limit
                                  └─► JSON { draw, recordsTotal,
                                             recordsFiltered, data[] }

GET /clients/create ───► create() ─► edit(null) ─► view csgtcrud::edit
GET /clients/{id}/edit ► edit($id)  ────────────► view csgtcrud::edit
POST /clients ─────────► store() ─► update($id = 0)
PUT  /clients/{id} ────► update($id)
DELETE /clients/{id} ──► destroy($id)
```

Every action calls the controller's own setup first (`__construct` on this
branch), so the field declaration is always in place before anything reads it.

## Field metadata

`setField()` is the heart of the package. Each call validates the incoming keys
and normalises them into one array with a fixed shape, appended to `$fields`:

```php
[
  'field'      => 'country.name AS country_name',  // as declared
  'name'       => 'Country',                       // column header
  'alias'      => 'country__name',                 // safe key for the JSON/DOM
  'campoReal'  => 'name',                          // column without the relation
  'type'       => 'string',                        // drives rendering and casting
  'show'       => true,                            // appears in the listing
  'editable'   => true,                            // appears in the form
  'isforeign'  => true,                            // resolve through a relation
  'searchable' => true,
  ...                                              // default, class, decimals,
]                                                  // filepath, target, utc, ...
```

Three shapes of `field` exist, and most of the query code branches on which one
it is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `name` | `select` on the model's table |
| relation column | `country.name AS country_name` (`isforeign => true`) | eager load + `whereHas` + correlated subquery for ordering |
| raw expression | `CONCAT(name, id) AS composed` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`type => 'multi'`) | `belongsToMany`, eager loaded per page |

The `getXxxFields()` helpers are just filters over that list; see `METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->model->newQuery()
   ├── select( local fields + raw expressions )          getSelect + getLocalShowFields
   ├── with( relation => fn($q) => addSelect(...) )      getForeignShowFields
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

The rendering loop then walks `getFieldOrder()` per row and pulls each value:
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

`column` is an index into `getShowFields()`. `applyFiltersToQuery()` validates
each entry (integer index, known column, non-empty value) and hands the valid
ones to `applyColumnFilter()`, which picks the clause by field shape: `whereHas`
for a relation or a `multi` field, `whereRaw` for an expression, plain `where`
otherwise. The alias is stripped first, since `AS alias` is only valid in a
`SELECT`.

`getFilterColumns()` is what feeds the `<select>` in the view: index plus a
tag-free label.

## Ordering

DataTables sends column indices; `getFieldOrder()` maps them back to declared
fields. Then:

- a plain column → `orderBy`
- a raw expression → `orderByRaw`, so it is not quoted as an identifier
- a relation column → a correlated subquery built from
  `getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation → falls back to a plain `orderBy`, never throws

## Persistence

`store()` delegates to `update($id = 0)`, so one method handles both. It filters
out `ignoreFields`, merges `hiddenFields`, then per field type: parses dates
with Carbon, moves uploaded files, puts `securefile` uploads on their disk and
deletes the previous one, casts `bool` to `1`/`0`, and detaches/attaches `multi`
relations after the model is saved.

## Extending

The intended extension point is your own subclass: declare fields and filters in
`setup`/`__construct`, and override a lifecycle action only when you must,
calling `parent::` where possible. The private helpers are private on purpose —
see `RULES.md` before changing any of them.
