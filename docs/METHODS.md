# Method reference

Branch `master` — package version `8.0`. Every method of
`Csgt\Crud\CrudController` as it exists on this branch. See `ARCHITECTURE.md`
for how they fit together and `RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` and
`detail` to the standard seven; `detail` has no corresponding method on
`CrudController` — a subclass has to add it).

| Method | Route | Does |
|---|---|---|
| `setup(Request $request)` | — (called by every action below) | Base implementation `abort(400, "This method must be overriden in parent")`. A subclass overrides this to declare the model, fields, joins, wheres, permissions and buttons. This replaces `__construct` from earlier branches. |
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata, filter columns, permissions, breadcrumb, extra buttons/actions and the preserved query string. No listing query is run here; the rows arrive later over AJAX. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies the column filters, the order and the page window, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record (or a blank form when `$aId` is falsy): editable fields, combo options, breadcrumb, and which JS widgets (`selectize`, `summernote`) the form needs. |
| `create(Request $request)` | `GET /resource/create` | Delegates to `edit($request, null)`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Validates against the rules collected from `setField`, filters out the ignored fields, merges the hidden ones, normalises each value by field type (dates through Carbon when `utc => true`, uploads moved, `securefile` put on its disk and the previous one deleted, `multi` values pulled out for attach/detach), saves, then attaches (and, on update, first detaches) the `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes a translated result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by
`tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applyFiltersToQuery($query, $filters)` | Applies every valid entry of `filters[]`; returns `true` if at least one was applied, which is what decides whether `recordsFiltered` is recounted. | Silently skips a non-array payload, a malformed entry, a non-integer column index (`filter_var(..., FILTER_VALIDATE_INT)`), an unknown index, and an empty (post-trim) value. Never trusts the request. |
| `applyColumnFilter($query, $column, $searchValue)` | Turns one filter into a clause, picking by field shape: `whereHas` for a `multi` field or a relation column (`isforeign`), `whereRaw` for an expression, plain `where` otherwise. | `$searchValue` arrives already wrapped in `%`. The alias is stripped first with `stripAlias()`. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery (`Relation::getRelationExistenceQuery()`) for a relation column. | Falls back to a plain `orderBy` when `method_exists($this->model, $relationName)` is false, so a stale declaration cannot throw. |
| `stripAlias($field)` | Returns the expression without its ` AS alias` suffix (case-insensitive match on `' as '`). | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier (no `(`, space or `.`). | Expressions, already-qualified columns and aliases are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw($c['field'])` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$fields`, the list built by `setField()`.

| Method | Returns |
|---|---|
| `getShowFields()` | Every field with `show == true`, in declaration order. The index into this list is what the view sends as a filter `column`. |
| `getLocalShowFields()` | Shown fields that live on the model's own table: not `multi`, and (no `.` in the field, or it contains a quote, or `isforeign` is falsy). These go into the `select`. |
| `getForeignShowFields()` | Shown relation columns (`isforeign == true`) grouped by relation name, keyed by an incrementing index, in the shape `data()` consumes to build the eager loads. |
| `getShowMultipleFields()` | Shown fields of type `multi`, eager loaded once per page. |
| `getLocalEditFields()` | Fields with `editable == true` that have no `.` in the field name; drives the edit form. |
| `getCamposEdit()` | Every field with `editable == true`. |
| `getFieldOrder()` | The declared field names (`show == true` only) in listing order with `___id___` appended; maps a DataTables column index back to a field. |
| `getFilterColumns()` | `[['index' => int, 'label' => string], ...]` for the filter `<select>`, built from `getShowFields()`. The label is `strip_tags`ped. |

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generateBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb `<ol>` HTML for `index`, `edit` or `create`, from either the default two-item trail or the custom trail set with `setBreadcrumb`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles whether the breadcrumb renders at all. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` (from its `collection`) and `multi` field (from the model's `fetch{Field}()` relation and `fetch{Field}Column()` key) for the form. |
| `getTemplate()` | public | Returns the Blade layout the views extend. |
| `getLayout()` | public | Same value as `getTemplate()`; both return `$this->layout`. |
| `getTitle()` | public | The configured title. |
| `downLevel($aPath)` | private | Drops the last `/`-separated segment of a path; used to build the redirect and breadcrumb URLs. |
| `getQueryString($request)` | private | The current query string (URL-decoded, `?`-prefixed), preserved across redirects so filters and pages survive a save. |

## Setters — the declaration API

Called from your controller's `setup(Request $request)`. This is the public
surface most applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModel($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setField($aParams)` | Declares one field. See allowed keys and types below. Rejects unknown keys with `dd(...)`. |
| `setTitle($aTitle)` | Listing title. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setPermissions($permissions)` | Sets the `create`/`update`/`destroy` permissions array wholesale (single argument — no module flag on this branch). |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form (`$aValor` omitted) means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` | Registers an extra relation name in `$joins` (declared but not read anywhere else in `CrudController`). |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra `leftJoin` for the listing query. |
| `setOrderBy($aParams)` | Default order; accepts only `column`/`direction` keys (an `$directions = ['asc', 'desc']` list is declared in the method but never used to validate the value — any string passed as `direction` is stored as-is). |
| `setPerPage($aCuantos)` | Rows per page (default 50). |
| `setHidden($aParams)` | `['field' => ..., 'value' => ...]` injected on save without being rendered. |
| `setignoreFields($aCampo)` | Adds one request key never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setExtraButton($aParams)` | Extra button per row (`url`, `title`, `icon`, `class`, `target`, `confirm`, `confirmmessage`). |
| `setExtraAction($aParams)` | Extra action next to the Add button (`url`, `title`, `target`). |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive` when truthy. |

### `setField` allowed keys and types

The allowed keys, read directly from the `$allowed` array in `setField()`:

```
field, name, editable, show, type, class, default, validationRules,
validationRulesMessage, decimals, collection, enumarray, filepath,
filewidth, fileheight, filedisk, target, isforeign, utc, editClass
```

Any other key aborts with `dd(...)`. `field` is the only required key.

The allowed `type` values, read directly from the `$tipos` array:

```
string, multi, numeric, date, datetime, bool, combobox, password,
enum, file, image, textarea, url, summernote, securefile
```

There is no `time` type in `setField`, even though `index.blade.php`'s column
renderer branches on `$column['type'] == 'time'` alongside `date`/`datetime` —
declaring a field with `type => 'time'` is rejected before it ever reaches the
view.

Notable defaults and per-type requirements enforced in `setField()`:

- `isforeign` defaults to `true`; `utc` defaults to `true`; `editClass`
  defaults to `'col-sm-12'`; `target` defaults to `'_blank'`.
- `type => 'combobox'` requires a non-empty `collection` and forces
  `show = false`.
- `type => 'file'` / `'image'` / `'securefile'` require a non-empty
  `filepath`; `'securefile'` also requires a non-empty `filedisk`.
- A field whose `field` equals the model's key name gets `editable = false`
  and a generated `idsinenc{n}` alias (there is no id encryption on this
  branch, so this only keeps the primary key out of the edit form and gives
  it a stable DOM-safe alias).

## Where the tests live

`tests/CrudControllerTest.php` (against the fixtures and reflection helpers in
`tests/TestCase.php`) covers: `getSelect`, `getLocalShowFields`,
`getForeignShowFields`, `getShowMultipleFields`, `getFieldOrder`,
`getFilterColumns`, `stripAlias`, `qualifyRelatedColumn`,
`applyOrderToQuery` (local column, raw expression, relation subquery, unknown
relation fallback), `applyColumnFilter` (local, relation, `multi`, raw
expression), `applyFiltersToQuery` (valid filters, invalid/empty/unknown
filters, non-array payload), `downLevel`, and that `setField` rejects unknown
keys. Private methods are reached through reflection.

Not covered, and why: the lifecycle actions (`index`, `data`, `edit`,
`create`, `store`, `update`, `destroy`, `show`) need a booted application
(`view()`, `config()`, `redirect()`, a real `Request`, session flashing),
which the suite deliberately avoids so it can run without a database server or
a full Laravel application — it only needs Eloquent and `illuminate/routing`,
both `require-dev`.
