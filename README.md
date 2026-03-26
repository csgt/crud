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

## Usage
To create a new CRUD, you may use the `php artisan make:crud ExampleController` command.  This will set up a boilerplate 
```
public function setup(Request $request)
    {
        //Required. Set the model to render
        $this->setModel(new Model);
        $this->setTitle('Title');
        //This will render an extra button next to the Add button
        $this->setExtraAction(['url' => '/module/import', 'title' => 'Importar']);
        //Render the breadcrumb according to BS version
        $this->setBreadcrumb([
            ['url' => '', 'title' => 'Catálogos', 'icon' => 'fa fa-book'],
            ['url' => '', 'title' => 'Titulo', 'icon' => 'fa fa-university'],
        ]);
        //Set columns to show
        $this->setField(['name' => 'Nombre', 'field' => 'name']);
        $this->setField(['name' => 'Descripción', 'field' => 'descripcion']);

        //Set combobox field
        $this->setField([
            'name'       => 'Protocol',
            'field'      => 'protocol_id',
            'type'       => 'combobox',
            'collection' => Protocol::select('id', 'name')->get(),
        ]);

 
        //Campo Multi:
        $this->setField(['name' => 'Requirements', 'type' => 'multi', 'field' => 'relationName']);
        requiere en el modelo, crear la relation belongsToMany relationName
        requiere en el modelo, crear el método fetchRelationName
        opcional, si la columna para mostrar en el show, no se llama 'name'
        crear el método fetchRelationNameColumn y retorne el campo deseado.

        //Set hidden variables to append to inserts and updates
        $this->setHidden(['field' => 'client_id', 'value' => 1]);

        //Set extra button for each row in the Crud
        $this->setExtraButton([
            'url'    => '/configuration/sensors?gateway_id={id}',
            'icon'   => 'fa fa-thermometer',
            'title'  => 'Sensors',
            'class'  => 'btn-warning',
        ]);

        //Set permissions using the ['edit' => true, 'create' => true, 'delete' => true] syntax
        $this->setPermissions(Cancerbero::crudPermissions('module'));
    }
```

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
