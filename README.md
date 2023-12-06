# Crud

This package is used to generate cruds.

| Package Version | Bootstrap | Methods | Views engine | Dependencies | Crypt | Constructor | UTC Default |
| --------------- | --------- | ------- | ------------ | ------------ | ----- | ----------- | ----------- |
| 5.5             | 3         | es      | blade        | js           | yes   | yes         | no          |
| 5.6             | 4         | es      | blade        | npm          | yes   | yes         | no          |
| 6.0             | 4         | en      | blade        | npm          | yes   | yes         | no          |
| 7.0 Beta        | 4         | en      | vue          | npm          | yes   | yes         | no          |
| 8.0             | 4         | en      | blade        | npm          | no    | no          | yes         |

## Upgrade from version 6 to version 8

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

Remove the enclosing middleware. It is now unnecessary.

```
$this->middleware(function ($request, $next) {

    return $next($request);
});
```
