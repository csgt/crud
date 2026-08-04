# Method reference

Branch `5.6` — package version `5.6`. Every method of `Csgt\Crud\CrudController`
as it exists on this branch. See `ARCHITECTURE.md` for how they fit together
and `RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch. This
branch's API is Spanish (`setModelo`, `setCampo`, `setTitulo`, `setPermisos`,
`generarBreadcrumb`, ...); it is not a synonym pair with the English names used
on later branches, it is the only spelling that exists here.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` and
`detail` to the standard seven).

| Method | Route | Does |
|---|---|---|
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata, permissions, breadcrumb, extra buttons and `filterColumns`. No query is run here; the rows arrive later over AJAX. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record (decrypting `$aId` when `csgtcrud.usar_encripcion` is on) and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit`. When `$aId` is falsy it renders a blank create form instead of looking up a record. |
| `create(Request $request)` | `GET /resource/create` | Delegates to `edit($request, null)`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Strips `noGuardar` fields with `Request::except()`, merges `camposHidden`, normalises each value by field type (dates parsed with `Carbon::parse()`, `file`/`image` uploads moved), saves, then detaches/attaches the `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes the result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applyFiltersToQuery($query, $filters)` | Applies every valid entry of `filters[]`; returns `true` if at least one was applied, which is what decides whether `recordsFiltered` is recounted. | Silently skips a non-array payload, a malformed entry, a non-integer column index, an unknown index, and an empty value. Never trusts the request. |
| `applyColumnFilter($query, $column, $searchValue)` | Turns one filter into a clause, picking by field shape: `whereHas` for a `multi` field or a relation column, `whereRaw` for an expression, plain `where` otherwise. | `$searchValue` arrives already wrapped in `%`. The alias is stripped first. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model, so a stale declaration cannot throw. |
| `stripAlias($field)` | Returns the expression without its ` AS alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier. | Expressions, already-qualified columns and aliases are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$campos`, the list built by `setCampo()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. The index into this list is what the view sends as a filter `column`. |
| `getCamposShowMine()` | Shown fields that live on the model's own table: not `multi`, and not a relation column. These go into the `select`. |
| `getCamposShowForeign()` | Shown relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads. |
| `getCamposEditMine()` | Fields with `editable => true` whose `campo` has no `.`; drives the edit form. |
| `getCamposEdit()` | Every field with `editable => true`. |
| `getCamposOrden()` | The declared field names in listing order with `___id___` appended; maps a DataTables column index back to a field. |
| `getFilterColumns()` | `[['index' => int, 'label' => string], ...]` for the filter `<select>`. The label is `strip_tags($column['nombre'])`. |

There is no method dedicated to "shown multi fields": `data()` computes the
list of `multi` relations to eager load with an inline
`array_filter`/`array_map`/`array_unique` over `$this->campos`.

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generarBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` and `multi` field for the form. |
| `getTemplate()` | public | The Blade layout the views extend. |
| `getTitulo()` | public | The configured title. |
| `downLevel($aPath)` | private | Drops the last segment of a path; used to build the redirect and breadcrumb URLs. |
| `getQueryString($request)` | private | The current query string, preserved across redirects so filters and pages survive a save. |

## Setters — the declaration API

Called from your controller's `__construct()`. This is the public surface most
applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModelo($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setCampo($aParams)` | Declares one field. Accepts `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `reglasmensaje`, `decimales`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `target`, `isforeign`, `utc`, `editClass`. Types (`tipo`): `string`, `multi`, `numeric`, `date`, `datetime`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. Rejects unknown keys. Note `filedisk` is not an accepted key even though `securefile` is a valid type — see `ARCHITECTURE.md`. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setTitulo($aTitulo)` | Listing title. |
| `setPermisos($aFuncionPermisos, $aModulo = false)` | `add` / `edit` / `delete` flags. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` | Recorded into `$this->joins`, but `data()` never reads it — has no observable effect on the listing. |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra `leftJoin` applied to the listing query. |
| `setOrderBy($aParams)` | Default DataTables order rendered into the view (`orders`); does not itself change the SQL order in `data()`. |
| `setPerPage($aCuantos)` | Rows per page (default 50). |
| `setHidden($aParams)` | Values injected on save without being rendered. Keys: `campo`, `valor`. |
| `setNoGuardar($aCampo)` | Request keys never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setBotonExtra($aParams)` | Extra button per row (`url`, `titulo`, `target`, `icon`, `class`, `confirm`, `confirmmessage`). |
| `setAccionExtra($aParams)` | Extra action next to the Add button (`url`, `titulo`, `target`). |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive`. |

## Where the tests live

`tests/CrudControllerTest.php` covers the listing query helpers and the field
metadata helpers by asserting the SQL and the bindings they produce, with the
private methods reached through the reflection helpers in `tests/TestCase.php`.

Not covered, and why: the lifecycle actions need a booted application (`view()`,
`config()`, `redirect()`, a real `Request`), which the suite deliberately avoids
so it can run without a database server or a Laravel app.
