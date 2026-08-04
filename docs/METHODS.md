# Method reference

Branch `5.9` — package version `5.9`. Every method of `Csgt\Crud\CrudController`
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
| `setup(Request $request)` | — | Base implementation aborts with 400. Your controller overrides it to call `setModelo`, `setCampo`, `setTitulo`, `setPermisos`, etc. Called at the top of every lifecycle action below. |
| `index(Request $request)` | `GET /resource` | Calls `setup`, renders `csgtcrud::index` with the column metadata, permissions, breadcrumb, extra buttons/actions and `filterColumns`. No query is run here; the rows arrive later over AJAX. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies the column filters, the order and the page window, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record (decrypting `$aId` first if `csgtcrud.usar_encripcion` is on) and returns it as JSON when the request expects JSON. |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record: editable fields (`getCamposEditMine`), combo options, breadcrumb, and which JS widgets (`selectize`, `summernote`) the form needs. |
| `create(Request $request)` | `GET /resource/create` | Delegates to `edit($request, null)`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Validates against `$reglas`, filters out `$noGuardar`, merges `$camposHidden`, normalises each value by field type (dates through Carbon, `file`/`image` uploads moved into `public_path()`, `bool` cast to 1/0), saves, then detaches/attaches the `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record, flashes a translated message and redirects. |

## Listing query helpers

All private. Called from `data()`, and covered by `tests/CrudControllerTest.php`.

| Method | Does | Notes |
|---|---|---|
| `applyFiltersToQuery($query, $filters)` | Applies every valid entry of `filters[]`; returns `true` if at least one was applied, which is what decides whether `recordsFiltered` is recounted. | Silently skips a non-array payload, a malformed entry, a non-integer column index, an unknown index, and an empty value. Never trusts the request. |
| `applyColumnFilter($query, $column, $searchValue)` | Turns one filter into a clause, picking by field shape: `whereHas` for a `multi` field or a relation column, `whereRaw` for an expression, plain `where` otherwise. | `$searchValue` arrives already wrapped in `%`. The alias is stripped first. |
| `applyOrderToQuery($query, $columnName, $direction)` | Orders in the database: `orderBy` for a plain column, `orderByRaw` for an expression, and a correlated subquery for a relation column. | Falls back to a plain `orderBy` when the relation does not exist on the model, so a stale declaration cannot throw. |
| `stripAlias($field)` | Returns the expression without its ` AS alias` suffix. | `AS` is only valid in a `SELECT`; leaving it in a `WHERE`/`ORDER BY` produces invalid SQL. |
| `getSelect($aCampos)` | Maps the given fields to `DB::raw` expressions for the `select`. | Raw on purpose: a field may be an expression. |
| `downLevel($aPath)` | Drops the last segment of a path. | Used to build the redirect and breadcrumb URLs after create/update/destroy. |

## Field metadata helpers

Private filters over `$campos`, the list built by `setCampo()`.

| Method | Returns |
|---|---|
| `getCamposShow()` | Every field with `show => true`, in declaration order. The index into this list is what the view sends as a filter `column`. |
| `getCamposShowMine()` | Shown fields that live on the model's own table: not `multi`, and not a relation column (or an already-qualified/aliased expression). These go into the `select`. |
| `getCamposShowForeign()` | Shown relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads. |
| `getCamposEditMine()` | Fields with `editable => true` that are not relation columns; drives the edit form. |
| `getCamposEdit()` | Every field with `editable => true`. |
| `getCamposOrden()` | The declared field names (`show => true` only) in listing order with `___id___` appended; maps a DataTables column index back to a field. |
| `getFilterColumns()` | `[['index' => int, 'label' => string], ...]` for the filter `<select>`. The label is `strip_tags`ped. |

## Rendering helpers

| Method | Visibility | Does |
|---|---|---|
| `generarBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `fillCombos($aCampos)` | private | Resolves the option list of every `combobox` and `multi` field for the form. |
| `getTemplate()` | public | The Blade layout the views extend. |
| `getTitulo()` | public | The configured title. |
| `getQueryString($request)` | private | The current query string, preserved across redirects so filters and pages survive a save. |

## Setters — the declaration API

Called from your controller's `setup(Request $request)`. This is the public
surface most applications depend on; treat every signature as frozen.

| Method | Purpose |
|---|---|
| `setModelo($aModelo)` | The Eloquent model the CRUD is built on. Required. |
| `setCampo($aParams)` | Declares one field. Allowed keys: `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `decimales`, `collection`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `target`, `isforeign`, `utc`, `editClass`. Rejects unknown keys. Types (`tipo`): `string`, `multi`, `numeric`, `date`, `datetime`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. |
| `setTitulo($aTitulo)` | Listing title. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setPermisos($aFuncionPermisos, $aModulo = false)` | `add` / `edit` / `delete` flags, or a middleware-resolved callback when `$aModulo` is given. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` | Records a join, but it is **not applied** anywhere in `data()` on this branch — declared and populated, never read. |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra left join for the listing query; this one *is* applied in `data()`. |
| `setOrderBy($aParams)` | Default order. Allowed keys: `columna`, `direccion` (`asc`/`desc`). |
| `setPerPage($aCuantos)` | Rows per page (default 50). |
| `setHidden($aParams)` | Values injected on save without being rendered. Allowed keys: `campo`, `valor`. |
| `setNoGuardar($aCampo)` | Adds a request key never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setBotonExtra($aParams)` | Extra button per row. Allowed keys: `url`, `titulo`, `target`, `icon`, `class`, `confirm`, `confirmmessage`. |
| `setAccionExtra($aParams)` | Extra action next to the Add button. Allowed keys: `url`, `titulo`, `target`. |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive`. |

## Where the tests live

`tests/CrudControllerTest.php` covers, through the reflection helpers in
`tests/TestCase.php`:

- `getSelect`, `getCamposShow`, `getCamposShowMine`, `getCamposShowForeign`,
  `getCamposOrden`, `getFilterColumns`
- `applyOrderToQuery` for a local column, a raw expression, a relation
  (correlated subquery), and an unknown relation (fallback)
- `applyColumnFilter` for a local column, a relation column (`whereHas`), a
  `multi` field (`whereHas` through the pivot), and a raw expression
  (`whereRaw`)
- `applyFiltersToQuery` applying valid filters and ignoring empty/unknown/
  malformed ones, including a non-array payload
- `downLevel`
- that `setCampo` registers every declared field and that `getTitulo()`
  reflects `setTitulo()`

Not covered, and why: the lifecycle actions (`index`, `data`, `edit`, `store`,
`update`, `destroy`) need a booted application (`view()`, `config()`,
`redirect()`, a real `Request`, a database), which the suite deliberately
avoids so it can run without a database server or a Laravel app. `setCampo`'s
validation branches (unknown key, unknown `tipo`, missing `filepath`/
`collection`) are not asserted either — they all end in `dd()`, which is not
something a unit test can assert against without changing the source.
