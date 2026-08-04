# Method reference

Branch `5.6.bs4` — package version `5.6 (bs4 variant)`. Every method of
`Csgt\Crud\CrudController` as it exists on this branch. See `ARCHITECTURE.md`
for how they fit together and `RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch. This
branch's API is Spanish (`setModelo`, `setCampo`, `setTitulo`, `setPermisos`,
`generarBreadcrumb`, ...), same as `5.6`.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` and
`detail` to the standard seven).

| Method | Route | Does |
|---|---|---|
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata, permissions, breadcrumb, extra buttons, and the full DataTables `options` array (ajax url, language strings, default order, export buttons) built in PHP. No query is run here; the rows arrive later over AJAX. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record (`$aId` always decrypted with `Crypt::decrypt()`) and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record: editable fields, combo options, breadcrumb, query string. |
| `create(Request $request)` | `GET /resource/create` | Renders `csgtcrud::edit` directly, with `$data = null`; it does not delegate to `edit()`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Validates the request with rules built from each editable field's `reglas`, strips `noGuardar` fields with `Arr::except()`, merges `camposHidden`, normalises each value by field type (dates via `Carbon::createFromFormat()`, `file`/`image` moved, `securefile` put on its `filedisk` and the previous one deleted), saves, then detaches/attaches the `multi` relations. Returns JSON (`{data, redirect}`) or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes the result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applySearchToQuery($query, $searchValue, $columns, $foreigns)` | Applies the DataTables global search as one grouped `where`: `orWhereRaw('LOWER(column) LIKE ?', ...)` for every local shown column, `orWhereHas($relation, ...)` for every relation. | Replaces `applyFiltersToQuery`/`applyColumnFilter` from `5.6`/`5.7` — there is no per-column filter path on this branch. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model, so a stale declaration cannot throw. |
| `stripAlias($field)` | Returns the expression without its ` AS alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier. | Expressions, already-qualified columns and aliases are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$campos`, the list built by `setCampo()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. |
| `getCamposShowMine()` | Shown fields that live on the model's own table: not `multi`, and not a relation column. These go into the `select`. |
| `getCamposShowForeign()` | Shown relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads and the global search's `whereHas`. |
| `getCamposShowMulti()` | Shown fields of type `multi`, used to build the eager-loaded relations. Unlike `5.6`, this is a dedicated method rather than an inline filter. |
| `getCamposEditMine()` | Fields with `editable => true` whose `campo` has no `.`; drives the edit form and the `update()` validation rules. |
| `getCamposEdit()` | Every field with `editable => true`. |
| `getCamposOrden()` | The declared field names in listing order with `___id___` appended; maps a DataTables column index back to a field. |

There is no `getFilterColumns()` on this branch: without per-column filters
there is no filter `<select>` to feed.

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generarBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` and `multi` field for the form. |
| `getTemplate()` | public | The Blade layout the views extend. |
| `getTitulo()` | public | The configured title. |
| `downLevel($aPath)` | private | Drops the last segment of a path; used to build the redirect and breadcrumb URLs. |
| `getQueryString($request)` | private | The current query string, preserved across redirects so the page survives a save. |

## Setters — the declaration API

Called from your controller's `__construct()`. This is the public surface most
applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModelo($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setCampo($aParams)` | Declares one field. Accepts `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `reglasmensaje`, `decimales`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `filedisk`, `target`, `isforeign`, `utc`. Types (`tipo`): `string`, `multi`, `numeric`, `date`, `datetime`, `time`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. Rejects unknown keys. Adds `filedisk` and the `time` type compared to `5.6`. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setTitulo($aTitulo)` | Listing title. |
| `setPermisos($aFuncionPermisos, $aModulo = false)` | `add` / `edit` / `delete` flags. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` | Recorded into `$this->joins`, but `data()` never reads it — has no observable effect on the listing. |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra `leftJoin` applied to the listing query. |
| `setOrderBy($aParams)` | Default order, turned directly into the DataTables `options['order']` array by `index()`. |
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
It specifically tests `applySearchToQuery` (local columns, foreign columns via
`whereHas`, and that the `___id___` alias is skipped) rather than
`applyFiltersToQuery`/`applyColumnFilter`, which do not exist on this branch.

Not covered, and why: the lifecycle actions need a booted application (`view()`,
`config()`, `redirect()`, a real `Request`), which the suite deliberately avoids
so it can run without a database server or a Laravel app.
