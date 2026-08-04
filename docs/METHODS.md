# Method reference

**This branch is archived.** It is closed to new features and accepts fixes
only — see `RULES.md`.

Branch `5.0` — package version `5.0`. Every method of the static class
`Csgt\Crud\Crud` as it exists on this branch. There is no `CrudController` to
document: this is the whole public surface. See `ARCHITECTURE.md` for how they
fit together and `RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch.

---

## Lifecycle actions

Not routed by the package itself — there is no `ResourceRegistrar`. Your own
routes call these from your own controller, after calling the setters below.

| Method | Typical caller | Does |
|---|---|---|
| `index()` | `GET` action | Aborts (`dd()`) if `setTabla`/`setTablaId` were never called. Renders `csgtcrud::index` with the column metadata, permissions, breadcrumb-adjacent title, extra buttons and query-string preservation. No query is run here. |
| `getData($showEdit)` | a route you wire yourself, called from the listing view's DataTables config | Builds the listing query (`$camposEdit` if `$showEdit == '1'`, `$camposShow` otherwise), applies the DataTables global search and order, and returns the DataTables JSON `{draw, recordsTotal, recordsFiltered, data}`. The hot path of the package. |
| `create($aId)` | `GET` action for both new and edit | Decrypts `$aId` if `csgtcrud.usar_encripcion` is on and `$aId !== 0`; loads the row via the query builder when `$aId` is truthy, resolves every `combobox` field's options by running its raw `query` string, and renders `csgtcrud::edit`. |
| `store($id = null)` | `POST`/`PUT` action | Creates when `$id === null`, updates otherwise. Reads every editable field from the `Input` facade by type, builds a slug, stamps timestamps, inserts/updates through the query builder, and returns JSON (if the request's `Content-Type` is `application/json`) or a redirect with a flashed session message. |
| `destroy($aId)` | `DELETE` action | Decrypts `$aId` if configured, soft- or hard-deletes depending on `setSoftDelete()`, flashes a translated message, and redirects. |

## Listing query helpers

| Method | Visibility | Does | Notes |
|---|---|---|---|
| `getUrl($aPath, $aEdit = false)` | private static | Drops the last segment of a path, or the last two when `$aEdit` is true. | Used to build the redirect/breadcrumb URL after create/store/destroy. Covered by `tests/CrudTest.php`. |
| `getGetVars()` | private static | Returns the current `QUERY_STRING`, prefixed with `?`, or `''` if empty. | Preserves filters/pagination across a redirect. |

There is no `applyFiltersToQuery`, `applyColumnFilter`, `applyOrderToQuery` or
`stripAlias` on this branch. Filtering is a single DataTables global search
box (an `OR`ed `LIKE` over every `searchable` column, built inline inside
`getData()`), and ordering re-derives the real column from
`$selects[$order['column']]` inline, also inside `getData()` — none of that
logic is factored into a separate method the way it is from `5.9` onward.

## Field metadata

Unlike every later branch, field metadata is **not** re-derived through
getter methods (`getCamposShow()`/`getCamposEdit()` etc. do not exist).
`setCampo()` appends directly to `$camposShow` and/or `$camposEdit`
(`private static` arrays) depending on the `show`/`editable` flags it was
given, and `getData()`/`create()`/`store()` read those arrays straight.

## Setters — the declaration API

Static; call these on `Crud::` (or the `Crud` facade) before every static
lifecycle call, since no state survives between requests.

| Method | Purpose |
|---|---|
| `setTabla($aTabla)` | The table the CRUD is built on. Required. |
| `setTablaId($aNombre)` | The primary key column name. Required. |
| `setTitulo($aNombre)` | Listing title. |
| `setCampo($aParams)` | Declares one field. Allowed keys: `campo`, `nombre`, `editable`, `show`, `tipo`, `class`, `default`, `reglas`, `reglasmensaje`, `decimales`, `query`, `combokey`, `enumarray`, `filepath`, `filewidth`, `fileheight`, `target`, `editClass`. Rejects unknown keys. Types (`tipo`): `string`, `numeric`, `date`, `datetime`, `bool`, `combobox`, `password`, `enum`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. No `multi` type and no `isforeign` key exist on this branch. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aValor)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra left join for the listing query. |
| `setOrderBy($aParams)` | Default order. Allowed keys: `columna`, `direccion` (`asc`/`desc`). |
| `setGroupBy($aCampo)` | Adds a `GROUP BY` column. Not present on `5.9`/`6.0`/`7.x`. |
| `setPerPage($aCuantos)` | Rows per page (default 20 — `5.9`/`6.0` default to 50). |
| `setHidden($aParams)` | Values injected on save without being rendered. Allowed keys: `campo`, `valor`. |
| `setBotonExtra($aParams)` | Extra button per row. Allowed keys: `url`, `titulo`, `target`, `icon`, `class`, `confirm`, `confirmmessage`. |
| `setPermisos($aPermisos)` | `add`/`edit`/`delete` flags. Unlike later branches, there is no second `$aModulo` argument or middleware-resolved callback form. |
| `setTemplate($aTemplate)` | Blade layout to extend. |
| `setSlug($aParams)` | Configures automatic slug generation. Allowed keys: `columnas` (array, accumulates the source columns), `campo` (target column, default `slug`), `separator` (default `-`). |
| `setSoftDelete($aBool)` | Switches `destroy()` between a hard delete and setting `deleted_at`. |
| `setExport($aBool)` | Toggles the export button in the view. |
| `setSearch($aBool)` | Toggles the DataTables search box in the view. |
| `setStateSave($aBool)` | Toggles DataTables `stateSave`. |
| `setResponsive($aResponsive)` | **Not** declared `static` in the source (`public function setResponsive`, no `static` keyword) even though it writes to the `private static $responsive` property — callable as `(new Crud)->setResponsive(...)`, unlike every other setter on this class. |

## Read-only helper

| Method | Purpose |
|---|---|
| `getSoftDelete()` | Returns the current `setSoftDelete()` value. |

## Where the tests live

`tests/CrudTest.php` covers, through the reflection helpers in
`tests/TestCase.php`:

- `getUrl` (both the single- and double-segment-drop forms)
- `setTabla`/`setTablaId`/`setTitulo` storing their value
- the boolean toggles (`setExport`, `setSearch`, `setStateSave`,
  `setSoftDelete`, `setResponsive`) and `getSoftDelete()`
- `setPerPage`/`setTemplate`/`setPermisos`
- `setSlug` accumulating columns and overriding the field/separator
- `setWhere` (both the two- and three-argument forms), `setWhereIn`,
  `setWhereRaw`, `setLeftJoin`, `setGroupBy`
- `setOrderBy`, both defaulted and explicit
- `setHidden`
- `setBotonExtra`, both defaulted and with explicit values
- `setCampo`: a plain field, a dotted field (relation-style alias
  derivation), an expression (generated alias, `searchable` forced false), a
  field matching the table id (reserved alias, not editable), `show => false`
  (only added to `camposEdit`), `editable => false` (only added to
  `camposShow`), and a valid `combobox` declaration

Not covered, and why (stated directly in the test file's own docblock):
`index`, `getData`, `create`, `store` and `destroy` are driven by the
`Input`, `Request`, `Session`, `View`, `Redirect` and `DB` facades and need a
booted Laravel application, which the suite deliberately avoids so it can run
without a database server or a Laravel app. `setCampo`'s validation branches
(unknown key, unknown `tipo`, missing `filepath`/`query`/`combokey`) are not
asserted either — they all end in `dd()`, which a unit test cannot assert
against without changing the source.
