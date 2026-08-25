# Architecture

Branch `5.9` — package version `5.9`. See `README.md` for the PHP and Laravel
bounds of this branch, `METHODS.md` for the method reference and `RULES.md` for
what you are allowed to change.

This is the branch where the listing-query optimization and the per-column
filters were first written (see the `optimization: move filter and order logic
to the query` and `feat: search by column, multifilter` commits in the log).
The other maintained branches (`6.0`, `7.x`, `master`) port that same query
pipeline; when in doubt about the intended shape of the pipeline, this branch
is the one to check first.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields inside `setup(Request $request)`. From that
declaration the package renders the listing, serves it to DataTables over
AJAX, renders the edit/create form, validates and persists the input, and
registers the routes.

There is no code generation at runtime: one controller subclass plus one route
line is a full CRUD. The public API on this branch is entirely in Spanish
(`setModelo`, `setCampo`, `setTitulo`, `setPermisos`, ...); this is the last
maintained branch before the API was translated to English on `6.0`.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` route |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php` | Listing: DataTables config, column renderers, the column-filter UI |
| `src/resources/views/edit.blade.php` | Edit/create form, one input per field type |
| `src/resources/lang/{en,es}/crud.php` | User-facing strings |
| `src/config/csgtcrud.php` | Package config (`usar_encripcion`, ...) |
| `tests/CrudControllerTest.php` | PHPUnit suite over the query-building methods |

There is no `detail` route on this branch, unlike the `6.0`/`master` line.

## Request lifecycle

```
Route::resource('clientes', ClientesController::class)
        │
        │  ResourceRegistrar adds one route to the seven standard ones:
        │      POST clientes/data  -> data()
        ▼
GET /clientes ─────────────► index()
                              │ calls setup($request), builds column metadata
                              └─► view csgtcrud::index
                                      │ DataTables boots with serverSide: true
                                      ▼
                        POST /clientes/data ──────► data()
                                      │ builds ONE query: select, eager loads,
                                      │ leftJoins, fixed wheres, column filters,
                                      │ order, offset/limit
                                      └─► JSON { draw, recordsTotal,
                                                 recordsFiltered, data[] }

GET /clientes/create ───► create() ─► edit($request, null)
GET /clientes/{id}/edit ► edit($request, $id)
POST /clientes ─────────► store() ─► update($request, 0)
PUT  /clientes/{id} ────► update($request, $id)
DELETE /clientes/{id} ──► destroy($request, $id)
```

Every action calls `$this->setup($request)` first — there is no
`__construct` entry point on this branch. `setup()` on the base
`CrudController` aborts with 400 ("Este método debe ser sobreescrito en el
controlador padre"); your subclass must override it and declare the model and
fields there.

## Field metadata

`setCampo()` is the heart of the package. Each call validates the incoming keys
against `$allowed` and normalises them into one array with a fixed shape,
appended to `$campos`:

```php
[
  'campo'      => 'pais.nombre AS pais_nombre', // as declared
  'nombre'     => 'Pais',                        // column header
  'alias'      => 'pais__nombre',                // safe key for the JSON/DOM
  'campoReal'  => 'nombre',                       // column without the relation
  'tipo'       => 'string',                       // drives rendering and casting
  'show'       => true,                           // appears in the listing
  'editable'   => true,                           // appears in the form
  'isforeign'  => true,                           // resolve through a relation
  'searchable' => true,
  ...                                             // default, class, decimales,
]                                                 // filepath, target, utc, ...
```

Allowed keys for `setCampo()`: `campo`, `nombre`, `editable`, `show`, `tipo`,
`class`, `default`, `reglas`, `decimales`, `collection`, `enumarray`,
`filepath`, `filewidth`, `fileheight`, `target`, `isforeign`, `utc`,
`editClass`. Unknown keys abort with `dd()`.

Field shapes, most of the query code branches on which one a field is:

| Shape | Example | Resolved by |
|---|---|---|
| local column | `nombre` | `select` on the model's table |
| relation column | `pais.nombre AS pais_nombre` (`isforeign => true`) | eager load + `whereHas` + correlated subquery for ordering |
| raw expression | `CONCAT(nombre, id) AS composed` | `DB::raw` in the select, `whereRaw`/`orderByRaw` elsewhere |
| multi relation | `tags` (`tipo => 'multi'`) | belongs-to-many relation, eager loaded per page |

The `getCampos*()` helpers are just filters over `$campos`; see `METHODS.md`.

## The listing query

`data()` builds a single query and never materialises more than one page:

```
$this->modelo->newQuery()
   ├── select( getSelect(getCamposShowMine()) )          local + raw fields
   ├── with( relation => fn($q) => addSelect(...) )       getCamposShowForeign
   ├── addSelect( primary key AS ___id___ )
   ├── leftJoin(...)                                      $this->leftJoins
   ├── where / whereIn / whereRaw                          $this->wheres, wheresIn, wheresRaw
   │
   ├── recordsTotal = (clone $data)->count()
   │
   ├── applyFiltersToQuery( filters[] from the view )     per-column filters
   ├── recordsFiltered = (clone $data)->count()           only if a filter applied
   │
   ├── order by getCamposOrden() / applyOrderToQuery()    plain / raw / subquery
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
2. **No query inside the row loop.** Anything a row needs is eager loaded
   before the loop starts.

`$this->joins` (populated by `setJoin()`) is declared and populated but is not
read anywhere in `data()` — only `$this->leftJoins` (populated by
`setLeftJoin()`) is applied. Do not rely on `setJoin()` having any effect on
the generated query on this branch.

## Column filters

The listing has no global search box. The view renders a list of
column/value rows and posts them as `filters[]`:

```json
{ "filters": [ { "column": 0, "value": "acme" },
               { "column": 1, "value": "mex" } ] }
```

`column` is an index into `getCamposShow()`. `applyFiltersToQuery()` validates
each entry (integer index, known column, non-empty value) and hands the valid
ones to `applyColumnFilter()`, which picks the clause by field shape:
`whereHas` for a `multi` field (matched against `fetch<Campo>Column()` if the
model defines it, `nombre` otherwise) or a relation column, `whereRaw` for an
expression, plain `where` otherwise. The alias is stripped first with
`stripAlias()`, since `AS alias` is only valid in a `SELECT`.

`getFilterColumns()` is what feeds the `<select>` in the view: index plus a
tag-free label (`strip_tags($column['nombre'])`).

## Ordering

DataTables sends column indices; `getCamposOrden()` maps them back to declared
fields (in `show` order, with `___id___` appended). Then, per requested order:

- the unique-id column → `orderBy` on the model's primary key
- a plain column → `orderBy`
- a raw expression (parentheses or a space in it) → `orderByRaw`, so it is not
  quoted as an identifier
- a relation column → a correlated subquery built with
  `Relation::getRelationExistenceQuery()`, so the database sorts the page
- an unknown relation → falls back to a plain `orderBy`, never throws

## Persistence

`store()` delegates to `update($request, 0)`, so one method handles both
create and update. It filters out `$noGuardar` (`_token` plus anything added
with `setNoGuardar()`), merges `$camposHidden`, then per field type: parses
dates with Carbon, moves `file`/`image` uploads into `public_path().filepath`,
casts `bool` to `1`/`0`, and detaches/attaches `multi` relations after the
model is saved. A `securefile` upload is put on the disk declared with
`filedisk` through `putFile()`, replacing and deleting the previous file, and
the listing renders it as a temporary URL.

## Extending

The intended extension point is your own subclass: declare fields, wheres and
permissions in `setup(Request $request)`, and override a lifecycle action only
when you must, calling `parent::` where possible. The private helpers are
private on purpose — see `RULES.md` before changing any of them.
