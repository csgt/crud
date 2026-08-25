# Method reference

Branch `5.4` — package version `5.4`. Every method of `Csgt\Crud\CrudController`
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
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata, permissions, breadcrumb and extra buttons. No query is run here; the rows arrive later over AJAX. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies the global search, the order and the page window, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record (`$aId` decrypted with `Crypt::decrypt()`) and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record: editable fields, combo options, breadcrumb. |
| `create(Request $request)` | `GET /resource/create` | Its own method (does not delegate to `edit()`): renders `csgtcrud::edit` with `$data = null`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Filters out `noGuardar`, merges `camposHidden`, parses `date`/`datetime` fields with `Carbon::createFromFormat()`, moves `file`/`image` uploads, saves. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record (`$aId` decrypted), flashes the result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applySearchToQuery($query, $searchValue, $columns)` | Builds one `WHERE` group `orWhereRaw`ing `LOWER(field) LIKE ?` over every searchable column. | Skips non-searchable columns and the unique-id column. `$searchValue` is lower-cased and wrapped in `%` inside the method. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model. When the relation exists but the installed Eloquent has no `getRelationExistenceQuery()` (pre-5.5 Laravel), falls back to ordering by the model's primary key instead of building the subquery. |
| `stripAlias($field)` | Returns the expression without its ` as alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier. | Expressions, already-qualified columns and aliases are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$campos`, the list built by `setCampo()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. |
| `getCamposShowMine()` | Shown fields that live on the model's own table (no `.` in `campo`, or a quoted/raw expression). These go into the `select`. |
| `getCamposShowForeign()` | Shown relation columns grouped by relation name (any field containing an unquoted `.`), in the shape `data()` consumes to build the eager loads. |
| `getCamposEditMine()` | Fields with `editable => true` that are not relation columns; drives the edit form. |
| `getCamposEdit()` | Every field with `editable => true`. |
| `getCamposOrden()` | The declared field names in listing order with `___id___` appended; maps a DataTables column index back to a field. |

There is no `getCamposShowMulti()` and no `getFilterColumns()` on this branch:
`multi` fields and per-column filters do not exist here.

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generarBreadcrumb($aTipo, $aUrl='')` | private | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` field for the form (`multi` does not exist on this branch). |
| `downLevel($aPath)` | private | Drops the last segment of a path; used to build the redirect and breadcrumb URLs. |
| `getQueryString($request)` | private | The current query string, preserved across redirects so filters and pages survive a save. |

There is no `getTemplate()` or `getTitulo()` getter on this branch.

## Setters — the declaration API

Called from your controller's `__construct`. This is the public surface most
applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModelo($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setCampo($aParams)` | Declares one field. Accepts `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `reglasmensaje`, `decimales`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `target`. Types (`tipo`): `string`, `numeric`, `date`, `datetime`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. Rejects unknown keys. No `isforeign` key and no `multi` type on this branch. |
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

There is no `setAccionExtra()` and no `setResponsive()` on this branch.

## Where the tests live

`tests/CrudControllerTest.php` covers the listing query helpers and the field
metadata helpers by asserting the SQL and the bindings they produce, with the
private methods reached through the reflection helpers in `tests/TestCase.php`.

Not covered, and why: the lifecycle actions need a booted application (`view()`,
`config()`, `redirect()`, a real `Request`), which the suite deliberately avoids
so it can run without a database server or a Laravel app.
