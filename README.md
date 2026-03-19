# Crud

This package is used to generate cruds.

| Version  | BS  | Datatables | Methods | Views engine | Crypt | Constructor | UTC | Validation     |
| -------- | --- | ---------- | ------- | ------------ | ----- | ----------- | --- | -------------- |
| 5.5      | 3   | 1.x        | es      | blade        | yes   | yes         | no  | formvaldiation |
| 5.6      | 4   | 1.x        | es      | blade        | yes   | yes         | no  | formvaldiation |
| 5.9      | 4   | 1.x        | es      | blade        | yes   | no          | no  | laravel        |
| 6.0      | 4   | 1.x        | en      | blade        | yes   | yes         | no  | formvaldiation |
| 7.0 Beta | 4   | 1.x        | en      | vue          | yes   | yes         | no  | formvaldiation |
| 8.0      | 4/5 | 2.x        | en      | blade        | no    | no          | yes | laravel        |

## Upgrade from version 6 to version 8 or from 5.6 to 5.9

Add the following includes:

```
use Illuminate\Http\Request;
use Csgt\Cancerbero\Facades\Cancerbero;
```

Rename the `__construct` method to:

```
public function setup(Request $request)
```

Call the `setPermissions` directly

```
$this->setPermissions(Cancerbero::crudPermissions('module'));
```

Remove all references to `Crypt::encrypt` and `Crypt::decrypt` if any methos were overriden.
Remove all references to `validationRulesMessages` or `reglasmensaje`

Remove the enclosing middleware. It is now unnecessary.

```
$this->middleware(function ($request, $next) {

    return $next($request);
});
```
