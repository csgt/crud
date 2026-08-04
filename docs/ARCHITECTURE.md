# Architecture

Branch `7.x` — package version `7.0 Beta`. See `README.md` for the PHP and
Laravel bounds of this branch, `METHODS.md` for the method reference and
`RULES.md` for what you are allowed to change.

**This branch is an incomplete rewrite.** It replaces the Blade/DataTables
frontend with Vue components and replaces the DataTables JSON envelope with a
plain Laravel paginator response, but several pieces the README promises are
not implemented, and the query-side optimizations that exist on `5.9`/`6.0`
(per-column filters resolved through the field metadata, ordering through a
correlated subquery for relation columns, alias stripping) were never ported
here. See "What is unfinished" below before relying on anything not covered by
`tests/CrudControllerTest.php`.

---

## What the package does

You declare a CRUD by extending `CrudController` and describing an Eloquent
model plus a list of fields in `__construct()`. From that declaration the
package renders a Vue-powered listing, serves it paginated JSON over AJAX,
renders a Vue edit/create form, validates and persists the input, and
registers the routes. There is no `setup(Request $request)` entry point on
this branch — declaration happens in the constructor, like `6.0`.

## File map

| File | Role |
|---|---|
| `src/CrudController.php` | Everything: lifecycle actions, query building, field metadata, setters |
| `src/CrudServiceProvider.php` | Registers views, translations, the `make:crud` command, and swaps the resource registrar |
| `src/ResourceRegistrar.php` | Extends Laravel's registrar so `Route::resource` also creates the `data` route |
| `src/Console/MakeCrudCommand.php` | `php artisan make:crud` scaffolding, template in `src/Console/stubs/crud.stub` |
| `src/resources/views/index.blade.php`, `bakindex.blade.php` | Thin Blade shell that mounts the `csgtcrud-index` Vue component |
| `src/resources/views/edit.blade.php`, `bakedit.blade.php` | Thin Blade shell that mounts the `csgtcrud-edit` Vue component |
| `src/resources/js/views/Index.vue`, `Edit.vue` | The actual listing and form UI |
| `src/resources/js/components/*.vue` | `IndexHeader`, `IndexRow`, `IndexField`, `EditField`, `SearchBar`, `Navigation` |
| `src/resources/js/mixins/Errors.js` | Shared Vue error-handling mixin |
| `src/resources/lang/{en,es}/crud.php` | User-facing strings |
| `src/config/csgtcrud.php` | Package config |
| `tests/CrudControllerTest.php` | PHPUnit suite over the field-metadata methods that do exist |

Unlike `5.9`/`6.0`, this branch ships no view-level DataTables integration:
the Blade files only exist to hand off to Vue, and the host application is
responsible for building the `.vue` sources (see README `Requirements`:
"Vue components (the host app builds them)").

## Request lifecycle

```
Route::resource('clients', ClientsController::class)
        │
        │  ResourceRegistrar adds one route to the seven standard ones:
        │      POST clients/data  -> data()
        ▼
GET /clients ───────────► index()
                            │ aborts with 400 if setModel was never called
                            └─► view csgtcrud::index, mounts <csgtcrud-index>
                                    │ Vue calls the API itself
                                    ▼
                        POST /clients/data ──────► data()
                                    │ builds a query and calls
                                    │ $query->paginate()->withQueryString()
                                    └─► JSON: the paginator's own shape
                                        (data, links, meta, ...), NOT the
                                        DataTables {draw, recordsTotal, ...}
                                        envelope used on 5.9/6.0/master

GET /clients/create ───► create() ─► edit($request, null)
GET /clients/{id}/edit ► edit($request, $id)
POST /clients ─────────► store() ─► update($request, 0)
PUT  /clients/{id} ────► update($request, $id)
DELETE /clients/{id} ──► destroy($request, $id)
```

No lifecycle action calls a `setup()` hook — everything the controller needs
must already be set by the time the constructor returns.

## Field metadata

`setField()` validates the incoming keys against `$allowed` and normalises
them into one entry appended to `$fields`, which on this branch is an Eloquent
`Collection`, not a plain PHP array like on `5.9`/`6.0`:

```php
[
  'name'       => 'Country',
  'field'      => 'country.name AS country_name',
  'alias'      => 'country__name',
  'campoReal'  => 'name',
  'type'       => 'string',
  'show'       => true,
  'editable'   => true,
  'isforeign'  => true,   // defaults to false here, unlike 5.9/6.0 where it defaults to true
  'searchable' => true,
  ...
]
```

Allowed keys: `field`, `name`, `editable`, `show`, `type`, `class`, `default`,
`decimals`, `collection`, `filepath`, `filewidth`, `fileheight`, `filedisk`,
`target`, `isforeign`, `utc`, `editClass`. Types: `string`, `multi`,
`numeric`, `date`, `datetime`, `time`, `bool`, `combobox`, `password`, `file`,
`image`, `textarea`, `url`, `summernote`, `securefile`.

Two keys the README's "Known differences from 6.0" section promises do not
exist in `$allowed` or `$tipos`: there is no `validationRules`/`reglas` key
(validation is hardcoded in `update()`, see below) and there is no `combo`
type (only `combobox`); `enum` is indeed gone, as the README says, but nothing
replaces it.

## The listing query

`data()` builds a query and paginates it with Eloquent's own paginator instead
of a hand-rolled DataTables page window:

```
$this->model->query()
   ├── select( primary key AS ___id___ )
   ├── addSelect( local show fields )               getLocalShowFields, one at a time
   ├── with( relation => fn($q) => addSelect(...) )  getForeignShowFields
   ├── leftJoin(...)                                 $this->leftJoins
   ├── where / whereIn / whereRaw                    $this->wheres, wheresIn, wheresRaw
   ├── orderBy($request->sort['field'], ...)         if $request->sort['field'] is set
   ├── where/orWhere per entry of $request->searches
   └── paginate($this->perPage)->withQueryString()
```

There is no `getFieldOrder()`/`getSelect()` call inside `data()` even though
both methods exist on the class — see "What is unfinished". The JSON returned
is whatever `LengthAwarePaginator::toJson()` produces (`data`, `links`,
`meta`, ...), not the `{draw, recordsTotal, recordsFiltered, data}` shape the
other branches return; a Vue frontend built for this branch cannot talk to a
`5.9`/`6.0`/`master` backend or vice versa.

## Filtering

There is no `applyFiltersToQuery()`/`applyColumnFilter()`/`stripAlias()` on
this branch — those methods, and the per-column filter UI they support on
`5.9`/`6.0`, were not ported. Instead `data()` reads `$request->searches`, an
array of `{field, operator, conjunction, value}`, and applies each entry
directly as `where($field, $operator, $value)` or `orWhere(...)` (wrapping the
value in `%...%` only when `$operator === 'LIKE'`). There is no validation of
`field` against the declared columns, no alias stripping, and no special
casing for a relation column (`country.name`) or a `multi` field (`tags`): a
search on either would be handed to Eloquent's `where()` as a literal column
name, which does not resolve a dotted relation path and would need the caller
to already know the real underlying column. `$request->searches` is also
accessed without a default and without an `isset`/`is_array` guard, so a
request that omits it fails before any filtering logic runs.

## Ordering

Only a single order is supported (`$request->sort['field']` /
`$request->sort['direction']`), applied with a plain `orderBy()`. There is no
`getFieldOrder()`-driven column-index mapping, no `orderByRaw` for raw
expressions, and no correlated subquery for ordering by a relation column —
ordering by `country.name` would need the raw SQL of that expression passed as
`sort[field]` by the caller, which is not how the `5.9`/`6.0` filter/order
columns are addressed.

## Persistence

`store()` delegates to `update($request, 0)`, so one method handles both. It
validates against a **hardcoded** rules array —

```php
['email' => 'email|unique:usuarios', 'nombre' => 'numeric', 'roles' => 'required|min:1']
```

— unrelated to whatever fields the concrete controller actually declares; the
README's promised `$this->setValidation()` does not exist anywhere in the
source. Field normalisation then runs per declared field: dates are parsed
with `Carbon::createFromFormat('d/m/Y'[' H:i'], ...)` (not the free-form
`Carbon::parse()` used on `5.9`), `file`/`image` uploads are moved into
`public_path()`, `securefile` uploads are stored on their disk and the
previous file deleted (unlike `5.9`, where this is only implemented for
`file`/`image`), and `multi` relations are detached/attached after save.
There is no `bool` cast to `1`/`0` here, unlike `5.9`/`6.0`.

## What is unfinished

Read this before trusting anything not covered by
`tests/CrudControllerTest.php`:

- The DataTables-style per-column filters and their query-side validation
  (`applyFiltersToQuery`, `applyColumnFilter`, `stripAlias`,
  `getFilterColumns`) do not exist on this branch.
- Ordering by a relation column has no correlated-subquery support.
- `getFieldOrder()` and `getSelect()` are defined but never called from
  `data()` or anywhere else — dead code.
- `update()`'s validation rules are hardcoded and unrelated to the declared
  fields; `$this->setValidation()`, mentioned in the README, does not exist.
- `$request->searches` and `$request->sort` are read without checking they
  are present, so a request missing either fails outside of any try/catch.
- The Vue components under `src/resources/js` are the actual UI; there is no
  documented build step in this repository for compiling them, and the
  package's own `.blade.php` views only mount a component name — you need the
  host application's own asset pipeline to produce a working page.

## Extending

The intended extension point is your own subclass: declare fields, wheres and
permissions in `__construct()`, and override a lifecycle action only when you
must, calling `parent::` where possible. The private helpers are private on
purpose — see `RULES.md` before changing any of them, and see "What is
unfinished" before building on any behaviour this section describes as
missing.
