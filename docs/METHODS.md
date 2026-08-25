# Method reference

Branch `5.8` — package version `5.8`. Every method of `Csgt\Crud\CrudController`
as it exists on this branch. See `ARCHITECTURE.md` for how they fit together and
`RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` and
`detail` to the standard seven).

| Method | Route | Does |
|---|---|---|
| `index(Request $request)` | `GET /resource` | Renders `csgtcrud::index` with the column metadata (`getCamposShow()`), `filterColumns` (`getFilterColumns()`), permissions, breadcrumb, extra buttons/actions and `queryParameters`. No query is run here; the rows arrive later over AJAX. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies the column filters, the order and the page window, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record (`Crypt::decrypt($aId)`) and returns it as JSON when `$request->expectsJson()`. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record: `getLocalEditFields()`, `fillCombos()`, breadcrumb, `queryParameters`. |
| `create(Request $request)` | `GET /resource/create` | Renders `csgtcrud::edit` directly, with `$data = null` — does **not** call `edit()`. Builds edit fields, combos and breadcrumb itself. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Filters out the ignored fields (`Arr::except`), merges the hidden ones, normalises each value by field type (dates through `Carbon::createFromFormat`, `file`/`image` uploads moved with `$file->move()`, `securefile` put on its disk with `Storage::disk()->putFile()` and the previous one deleted), saves, then detaches/attaches the `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes the result message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applyFiltersToQuery($query, $filters)` | Applies every valid entry of `filters[]`; returns `true` if at least one was applied, which is what decides whether `recordsFiltered` is recounted. | Silently skips a non-array payload, a malformed entry, a non-integer column index (`filter_var(..., FILTER_VALIDATE_INT)`), an unknown index, and an empty (post-trim) value. Never trusts the request. |
| `applyColumnFilter($query, $column, $searchValue)` | Turns one filter into a clause, picking by field shape: `whereHas` for a `multi` field or a relation column, `whereRaw` for an expression, plain `where` otherwise. | `$searchValue` arrives already wrapped in `%`. The alias is stripped first. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery (via `getRelationExistenceQuery()`) for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model (`method_exists()` check), so a stale declaration cannot throw. |
| `stripAlias($field)` | Returns the expression without its ` AS alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `qualifyRelatedColumn($relatedModel, $column)` | Prefixes the related table only when the column is a plain identifier. | Expressions (contains `(`, a space, or a `.`) are returned untouched. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |

## Field metadata helpers

Private filters over `$fields`, the list built by `setField()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. The index into this list is what the view sends as a filter `column`. (Named `getCamposShow()` on this branch, not `getShowFields()`.) |
| `getLocalShowFields()` | Shown fields that live on the model's own table: not `multi`, and not a relation column. These go into the `select`. |
| `getForeignShowFields()` | Shown relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads. |
| `getShowMultipleFields()` | Shown fields of type `multi`, eager loaded once per page. |
| `getLocalEditFields()` | Fields with `editable => true` that are not relation columns (no `.` in `field`); drives the edit form. |
| `getCamposEdit()` | Every field with `editable => true`, including relation columns. |
| `getFieldOrder()` | The declared field names in listing order with `___id___` appended; maps a DataTables column index back to a field. |
| `getFilterColumns()` | `[['index' => int, 'label' => string], ...]` for the filter `<select>`, built from `getCamposShow()`. The label is `strip_tags`ped. |

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generateBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` and `multi` field for the form. |
| `getTemplate()` / `getLayout()` | public | The Blade layout the views extend. |
| `getTitle()` | public | The configured title. |
| `downLevel($aPath)` | private | Drops the last segment of a path; used to build the redirect and breadcrumb URLs. |
| `getQueryString($request)` | private | The current query string, preserved across redirects so filters and pages survive a save. |

## Setters — the declaration API

Called from your controller's `__construct()`. This is the public surface most
applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModel($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setField($aParams)` | Declares one field. Allowed keys (anything else calls `dd()`): `field`, `name`, `editable`, `show`, `type`, `class`, `default`, `validationRules`, `validationRulesMessage`, `decimals`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `filedisk`, `target`, `isforeign`. Types (`$tipos`): `string`, `multi`, `numeric`, `date`, `datetime`, `time`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. There is no `utc` or `editClass` key on this branch. |
| `setTitle($aTitle)` | Listing title. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setPermissions($aPermissionsCallback, $aModule = false)` | `create` / `update` / `destroy` flags. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` / `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra joins for the listing query. |
| `setOrderBy($aParams)` | Default order (`columna`/`direccion`, direction `asc`/`desc`). |
| `setPerPage($aCuantos)` | Rows per page (default 50). |
| `setHidden($aParams)` | `field`/`value` pairs injected on save without being rendered. |
| `setignoreFields($aCampo)` | Request keys never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setExtraButton($aParams)` | Extra button per row (`url`, `title`, `target`, `icon`, `class`, `confirm`, `confirmmessage`). |
| `setExtraAction($aParams)` | Extra action next to the Add button (`url`, `title`, `target`). |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive`. |

## Where the tests live

`tests/CrudControllerTest.php` (261 lines, plus `tests/TestCase.php` and
`tests/Fixtures`) covers the listing query helpers and the field metadata
helpers — `getSelect`, `getLocalShowFields`, `getForeignShowFields`,
`getShowMultipleFields`, `getFieldOrder`, `getFilterColumns`, `stripAlias`,
`qualifyRelatedColumn`, `applyOrderToQuery`, `applyColumnFilter`,
`applyFiltersToQuery`, `downLevel`, and `setField`'s key validation — by
asserting the SQL and the bindings they produce, with the private methods
reached through the reflection helpers in `tests/TestCase.php`.

Not covered, and why: the lifecycle actions (`index`, `data`, `edit`, `create`,
`store`, `update`, `destroy`, `show`) need a booted application (`view()`,
`config()`, `redirect()`, a real `Request`, `Crypt`, `Storage`), which the
suite deliberately avoids so it can run without a database server or a Laravel
app.
