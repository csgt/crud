<?php

namespace Csgt\Crud\Tests;

use Csgt\Crud\CrudController;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class CrudControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('tipos_envios', function ($table) {
            $table->increments('id');
            $table->string('nombre');
        });
        Capsule::schema()->create('ordenes', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('tipo_envio_id');
        });

        TipoEnvio::insert([
            ['id' => 1, 'nombre' => 'Standard'],
            ['id' => 2, 'nombre' => 'Express'],
            ['id' => 3, 'nombre' => 'Recoger'],
        ]);
        Orden::insert([
            ['id' => 10, 'tipo_envio_id' => 1],
            ['id' => 20, 'tipo_envio_id' => 2],
            ['id' => 30, 'tipo_envio_id' => 3],
        ]);
    }

    public function testBelongsToUsesTheOwnerKeyAndPaginatesInTheDatabase(): void
    {
        Capsule::connection()->enableQueryLog();

        $response = $this->controller()->data($this->request([
            'search' => ['value' => ''],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'start' => 1,
            'length' => 1,
            'draw' => 1,
        ]));
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(3, $payload['recordsTotal']);
        $this->assertSame(3, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Recoger', $payload['data'][0][1]);

        $pageQuery = collect(Capsule::connection()->getQueryLog())->first(function ($query) {
            return strpos($query['query'], 'from "ordenes"') !== false
                && strpos($query['query'], 'limit 1 offset 1') !== false;
        });

        $this->assertNotNull($pageQuery, 'The requested page must be limited in SQL.');
    }

    public function testGlobalSearchFiltersBelongsToColumnsInTheDatabase(): void
    {
        $response = $this->controller()->data($this->request([
            'search' => ['value' => 'Standard'],
            'order' => [],
            'start' => 0,
            'length' => 10,
            'draw' => 2,
        ]));
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(3, $payload['recordsTotal']);
        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertSame('Standard', $payload['data'][0][1]);
    }

    private function controller()
    {
        $controller = new CrudController();
        $controller->setModelo(new Orden());
        $controller->setCampo(['campo' => 'id', 'nombre' => 'ID']);
        $controller->setCampo(['campo' => 'tipoenvio.nombre', 'nombre' => 'Tipo de envío']);

        return $controller;
    }

    private function request(array $parameters)
    {
        return Request::create('/ordenes/data', 'POST', $parameters);
    }
}

class Orden extends Model
{
    protected $table = 'ordenes';

    public $timestamps = false;

    protected $guarded = [];

    public function tipoenvio()
    {
        return $this->belongsTo(TipoEnvio::class, 'tipo_envio_id', 'id');
    }
}

class TipoEnvio extends Model
{
    protected $table = 'tipos_envios';

    public $timestamps = false;

    protected $guarded = [];
}
