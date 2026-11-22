# Method reference

Branch `5.5` — package version `5.5`. Every method of `Csgt\Crud\CrudController`
as it exists on this branch. See `ARCHITECTURE.md` for how they fit together
and `RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` to
the standard seven).

| Method | Route | Does |
|---|---|---|
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata, permissions, breadcrumb, extra buttons and `filterColumns`. No query is run here; the rows arrive later over AJAX. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies the column filters, the order and the page window, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record: editable fields, combo options, breadcrumb. |
| `create(Request $request)` | `GET /resource/create` | Its own method (does not delegate to `edit()`): renders `csgtcrud::edit` with `$data = null`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Filters out `noGuardar`, merges `camposHidden`, normalises each value by field type (dates through Carbon with an optional `utc` shift, uploads moved, `securefile` put on its disk and the previous one deleted), saves, then detaches/attaches the `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes the result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applyFiltersToQuery($query, $filters)` | Applies every valid entry of `filters[]`; returns `true` if at least one was applied, which is what decides whether `recordsFiltered` is recounted. | Silently skips a non-array payload, a malformed entry, a non-integer column index, an unknown index, and an empty value. Never trusts the request. |
| `applyColumnFilter($query, $column, $searchValue)` | Turns one filter into a clause, picking by field shape: `whereHas` for a `multi` field or a relation column, `whereRaw` for an expression, plain `where` otherwise. | `$searchValue` arrives already wrapped in `%`. The alias is stripped first. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model, so a stale declaration cannot throw. Calls `getRelationExistenceQuery()` directly (no `method_exists()` guard); safe because this branch's floor is Laravel 5.5, where the method already exists. |
| `stripAlias($field)` | Returns the expression without its ` as alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier. | Expressions, already-qualified columns and aliases are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$campos`, the list built by `setCampo()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. The index into this list is what the view sends as a filter `column`. |
| `getCamposShowMine()` | Shown fields that live on the model's own table: not `multi`, and not a relation column. These go into the `select`. |
| `getCamposShowForeign()` | Shown relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads. |
| `getCamposShowMulti()` | Shown fields of type `multi`, eager loaded once per page. |
| `getCamposEditMine()` | Fields with `editable => true` that are not relation columns; drives the edit form. |
| `getCamposEdit()` | Every field with `editable => true`. |
| `getCamposOrden()` | The declared field names in listing order with `___id___` appended; maps a DataTables column index back to a field. |
| `getFilterColumns()` | `[['index' => int, 'label' => string], ...]` for the filter `<select>`. The label is `strip_tags`ped. |

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

Called from your controller's `__construct`. This is the public surface most
applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModelo($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setCampo($aParams)` | Declares one field. Accepts `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `reglasmensaje`, `decimales`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `filedisk`, `target`, `isforeign`, `utc`. Types (`tipo`): `string`, `multi`, `numeric`, `date`, `datetime`, `time`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. Rejects unknown keys. |
| `setTitulo($aTitulo)` | Listing title. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setPermisos($aFuncionPermisos, $aModulo = false)` | `add` / `edit` / `delete` flags. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` / `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra joins for the listing query. |
| `setOrderBy($aParams)` | Default order. Allowed keys: `columna`, `direccion`. |
| `setPerPage($aCuantos)` | Rows per page (default 50). |
| `setHidden($aParams)` | Values injected on save without being rendered. Allowed keys: `campo`, `valor`. |
| `setNoGuardar($aCampo)` | Request keys never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setBotonExtra($aParams)` | Extra button per row (`url`, `titulo`, `icon`, `class`, `target`, `confirm`, `confirmmessage`). |
| `setAccionExtra($aParams)` | Extra action next to the Add button (`url`, `titulo`, `target`). |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive`. |

## Where the tests live

`tests/CrudControllerTest.php` covers the listing query helpers and the field
metadata helpers by asserting the SQL and the bindings they produce, with the
private methods reached through the reflection helpers in `tests/TestCase.php`.

Not covered, and why: the lifecycle actions need a booted application (`view()`,
`config()`, `redirect()`, a real `Request`), which the suite deliberately avoids
so it can run without a database server or a Laravel app.
