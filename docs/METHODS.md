# Method reference

Branch `7.x` — package version `7.0 Beta`. Every method of
`Csgt\Crud\CrudController` as it exists on this branch. See `ARCHITECTURE.md`
for how they fit together, including the parts that are unfinished, and
`RULES.md` before changing any of them.

Signatures are copied from the source. **Public methods are contract**: their
name, signature and observable behaviour cannot change on this branch.

---

## Lifecycle actions

Routed by `Route::resource` (the package's `ResourceRegistrar` adds `data` to
the standard seven). There is no `setup()` hook on this branch; declare
everything the controller needs in `__construct()`.

| Method | Route | Does |
|---|---|---|
| `index(Request $request)` | `GET /resource` | Aborts with 400 if `setModel` was never called. Renders `csgtcrud::index` with a `props` array (`columns`, `urldata`, `urledit`, `urldestroy`, `urlcreate`, `extrabuttons`, `extraactions`) consumed by the `csgtcrud-index` Vue component. No query is run here. |
| `data(Request $request)` | `POST /resource/data` | Builds the listing query, applies `$request->sort` and `$request->searches`, and returns `$query->paginate($this->perPage)->withQueryString()` as JSON — a Laravel paginator response, not the DataTables envelope used on `5.9`/`6.0`/`master`. |
| `show(Request $request, $aId)` | `GET /resource/{id}` | Finds the record and returns it as JSON when the request expects JSON. Does not decrypt `$aId` (no `csgtcrud.usar_encripcion` check on this branch). |
| `edit(Request $request, $aId)` | `GET /resource/{id}/edit` | Renders `csgtcrud::edit` for an existing record (or `emptyState()` for a new one): editable fields (`getLocalEditFields`), `date`/`datetime`/`time` values reformatted for the Vue inputs, breadcrumb, and a `props` array for the `csgtcrud-edit` component. |
| `create(Request $request)` | `GET /resource/create` | Delegates to `edit($request, null)`. |
| `store(Request $request)` | `POST /resource` | Delegates to `update($request, 0)`. |
| `update(Request $request, $aId)` | `PUT /resource/{id}` | Creates when `$aId === 0`, updates otherwise. Validates against a hardcoded rules array (see `ARCHITECTURE.md`), filters out `$ignoreFields`, merges `$hiddenFields`, normalises each declared field by type (dates via `Carbon::createFromFormat`, `file`/`image` moved, `securefile` stored on its disk with the previous file deleted), saves, then detaches/attaches `multi` relations. Returns JSON or a redirect. |
| `destroy(Request $request, $aId)` | `DELETE /resource/{id}` | Deletes the record and always returns `response()->json('ok')` — no flash message, no redirect, unlike `5.9`/`6.0`. |

## Listing query helpers

All private. Called from `data()` unless noted otherwise; covered by
`tests/CrudControllerTest.php` where noted.

| Method | Does | Notes |
|---|---|---|
| `getSelect($aFields)` | Maps the given fields to `DB::raw` expressions. | Covered by a test, but **not called from `data()`** — dead code on this branch. |
| `downLevel($aPath)` | Drops the last segment of a path. | Used to build redirect and breadcrumb URLs. |

There is no `applyFiltersToQuery`, `applyColumnFilter`, `applyOrderToQuery` or
`stripAlias` on this branch — `data()` filters and orders directly off
`$request->searches`/`$request->sort` without going through the field
metadata. See `ARCHITECTURE.md` for what that means in practice.

## Field metadata helpers

Private filters/maps over `$fields`, which is a `Collection` on this branch
(built by `setField()`).

| Method | Returns |
|---|---|
| `getShowFields()` | Every field with `show => true`, in declaration order. |
| `getLocalShowFields()` | Shown fields that are not `multi` and not `isforeign`; these get `addSelect`ed individually in `data()`. |
| `getForeignShowFields()` | Shown, `isforeign` relation columns grouped by relation name, in the shape `data()` consumes to build the eager loads. |
| `getMultiFields()` | The `field` values of every `multi`-typed field, as a plain array via `pluck('field')`. |
| `getLocalEditFields()` | Fields with `editable => true` whose `field` has no `.` — this keeps `multi` fields (their `field` is a bare relation name), unlike `getLocalShowFields()`. |
| `getFieldOrder()` | The declared field names (`show => true`) in listing order with `___id___` appended. **Not called anywhere** — dead code on this branch, unlike `getCamposOrden()`/`getFieldOrder()` on `5.9`/`6.0`, which drive the DataTables column-index mapping. |

## Rendering / state helpers

| Method | Visibility | Does |
|---|---|---|
| `generateBreadcrumb($aTipo, $aUrl = '')` | public | Builds the breadcrumb HTML for `index`, `edit` or `create`. |
| `mostrarBreadcrumb($aBool)` | public | Toggles the breadcrumb. |
| `emptyState()` | private | Builds the default state for a new record: primary key `0`, one entry per `getLocalEditFields()` using its `default`, and an empty array for every multi field. |
| `getTemplate()` | public | The Blade layout the views extend. |
| `getLayout()` | public | Same value as `getTemplate()`, exposed under a second name. |
| `getTitle()` | public | The configured title. |
| `getQueryString($request)` | private | The current query string, preserved across redirects. |

## Setters — the declaration API

Called from your controller's `__construct()`. Treat every signature as
frozen.

| Method | Purpose |
|---|---|
| `setModel(Model $aModelo)` | The Eloquent model the CRUD is built on. Required, and type-hinted to `Illuminate\Database\Eloquent\Model` (not type-hinted on `5.9`/`6.0`). |
| `setField($aParams)` | Declares one field. Allowed keys: `field`, `name`, `editable`, `show`, `type`, `class`, `default`, `decimals`, `collection`, `filepath`, `filewidth`, `fileheight`, `filedisk`, `target`, `isforeign`, `utc`, `editClass`. Rejects unknown keys. Types: `string`, `multi`, `numeric`, `date`, `datetime`, `time`, `bool`, `combobox`, `password`, `file`, `image`, `textarea`, `url`, `summernote`, `securefile`. `isforeign` defaults to `false` here (defaults to `true` on `5.9`/`6.0`). No `validationRules`/`reglas` key exists. |
| `setTitle($aTitle)` | Listing title. |
| `setLayout($aLayout)` | Blade layout to extend. |
| `setPermissions($aPermissionsCallback, $aModule = false)` | `create`/`update`/`destroy` flags, or a middleware-resolved callback when `$aModule` is given. |
| `setWhere($aColumna, $aOperador, $aValor = null)` | Fixed `WHERE` on the listing. Two-argument form means equality. |
| `setWhereIn($aColumna, $aArray)` | Fixed `WHERE IN`. |
| `setWhereRaw($aStatement)` | Fixed raw `WHERE`. |
| `setJoin($aRelation)` | Records a join, but it is **not applied** anywhere in `data()` — declared and populated, never read, same as on `5.9`. |
| `setLeftJoin($aTabla, $aCol1, $aOperador, $aCol2)` | Extra left join for the listing query; this one *is* applied in `data()`. |
| `setOrderBy($aParams)` | Default order. Allowed keys: `column`, `direction` (`asc`/`desc`). Populates `$this->orders`, which is **never read** by `data()` — the actual order comes only from `$request->sort`. |
| `setPerPage($aCuantos)` | Rows per page for the paginator. |
| `setHidden($aParams)` | Values injected on save without being rendered. Allowed keys: `field`, `value`. |
| `setignoreFields($aField)` | Adds a request key never persisted (`_token` is always ignored). |
| `setBreadcrumb($aArray)` | Custom breadcrumb trail. |
| `setExtraButton($aParams)` | Extra button per row. Allowed keys: `url`, `title`, `target`, `icon`, `class`, `confirm`, `confirmmessage`. |
| `setExtraAction($aParams)` | Extra action next to the Add button. Allowed keys: `url`, `title`, `target`, `class`, `icon` (adds `icon` and `class`, which `5.9`'s `setAccionExtra` does not). |
| `setResponsive($aResponsive)` | Wraps the table in `table-responsive`; unused by the Vue index view. |

## Where the tests live

`tests/CrudControllerTest.php` covers, through the reflection helpers in
`tests/TestCase.php`:

- `getShowFields`, `getLocalShowFields`, `getForeignShowFields`,
  `getMultiFields`, `getLocalEditFields`, `getFieldOrder`, `getSelect`
- `downLevel`
- `emptyState`, for both the default values of local editable fields and the
  empty-array reset of multi fields
- that `setField` registers every declared field and that `getTitle()`
  reflects `setTitle()`

Not covered, and explicitly out of scope per the branch's own test comment
("`fields` here is a Collection... which changes several of the method
shapes"): the lifecycle actions (`index`, `data`, `edit`, `store`, `update`,
`destroy`), which need a booted application and a database. Because this
branch has no `applyFiltersToQuery`/`applyColumnFilter`/`applyOrderToQuery`,
there are also no tests for column filtering or relation ordering — those
methods simply do not exist here, unlike the equivalent suites on `5.9` and
`6.0`.
