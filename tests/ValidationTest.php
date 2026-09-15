<?php

use Csgt\Crud\CrudController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::flushMacros();
    }

    public function testStoreValidatesBeforeWritingAndPassesLaravelRulesUnchanged(): void
    {
        $controller = new CrudController;
        $controller->setModelo(new class {
            public function getKeyName() { return 'id'; }
            public function create($fields) { throw new LogicException('Must not write'); }
        });
        $controller->setCampo([
            'campo' => 'email', 'nombre' => 'Email address',
            'reglas' => ['required', 'email'],
        ]);
        $controller->setCampo(['campo' => 'age', 'reglas' => 'nullable|integer|min:18']);
        $controller->setCampo(['campo' => 'internal', 'editable' => false, 'reglas' => ['required']]);
        Request::macro('validate', function ($rules) {
            TestCase::assertSame(['required', 'email'], $rules['email']);
            TestCase::assertSame('nullable|integer|min:18', $rules['age']);
            TestCase::assertSame(['required'], $rules['internal']);
            throw new RuntimeException('Validation stopped persistence');
        });
        $this->expectExceptionMessage('Validation stopped persistence');
        $controller->store(Request::create('/items', 'POST'));
    }

    public function testUpdateValidatesBeforeWriting(): void
    {
        $controller = new CrudController;
        $controller->setModelo(new class {
            public function getKeyName() { return 'id'; }
            public function find($id) { throw new LogicException('Must not query'); }
        });
        $controller->setCampo(['campo' => 'name', 'reglas' => ['required']]);
        Request::macro('validate', function ($rules) {
            TestCase::assertSame(['required'], $rules['name']);
            throw new RuntimeException('Validation stopped persistence');
        });
        $this->expectExceptionMessage('Validation stopped persistence');
        $controller->update(Request::create('/items/1', 'PUT'), 1);
    }
}
