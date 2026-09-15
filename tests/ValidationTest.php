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
        $controller->setModel(new class {
            public function getKeyName() { return 'id'; }
            public function create($fields) { throw new LogicException('Must not write'); }
        });
        $controller->setField([
            'field' => 'email', 'name' => 'Email address',
            'validationRules' => ['required', 'email'],
        ]);
        $controller->setField(['field' => 'age', 'validationRules' => 'nullable|integer|min:18']);
        $controller->setField(['field' => 'internal', 'editable' => false, 'validationRules' => ['required']]);
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
        $controller->setModel(new class {
            public function getKeyName() { return 'id'; }
            public function find($id) { throw new LogicException('Must not query'); }
        });
        $controller->setField(['field' => 'name', 'validationRules' => ['required']]);
        Request::macro('validate', function ($rules) {
            TestCase::assertSame(['required'], $rules['name']);
            throw new RuntimeException('Validation stopped persistence');
        });
        $this->expectExceptionMessage('Validation stopped persistence');
        $controller->update(Request::create('/items/1', 'PUT'), 1);
    }

    public function testLegacyNotemptyRulesAreConvertedWithoutChangingOtherRules(): void
    {
        $controller = new CrudController;
        $controller->setModel(new class {
            public function getKeyName() { return 'id'; }
        });
        $customRule = function () {};
        $controller->setField(['field' => 'name', 'validationRules' => ['notempty', $customRule, 'regex:/notempty/']]);
        $controller->setField(['field' => 'email', 'validationRules' => 'notempty|email']);
        $controller->setField(['field' => 'code', 'validationRules' => 'string|notempty']);
        $controller->setField(['field' => 'label', 'validationRules' => 'notempty']);
        Request::macro('validate', function ($rules) use ($customRule) {
            TestCase::assertSame(['required', $customRule, 'regex:/notempty/'], $rules['name']);
            TestCase::assertSame('required|email', $rules['email']);
            TestCase::assertSame('string|required', $rules['code']);
            TestCase::assertSame('required', $rules['label']);
            throw new RuntimeException('Validation stopped persistence');
        });
        $this->expectExceptionMessage('Validation stopped persistence');
        $controller->store(Request::create('/items', 'POST'));
    }

    public function testLegacyEmailAddressRulesAreConverted(): void
    {
        $controller = new CrudController;
        $controller->setModel(new class {
            public function getKeyName() { return 'id'; }
        });
        $customRule = function () {};
        $controller->setField(['field' => 'email', 'validationRules' => ['notempty', 'EmailAddress', $customRule, 'regex:/EmailAddress/']]);
        $controller->setField(['field' => 'contact', 'validationRules' => 'notempty|emailAddress']);
        $controller->setField(['field' => 'alternate', 'validationRules' => 'emailaddress']);
        $controller->setField(['field' => 'native', 'validationRules' => 'nullable|email:rfc']);
        Request::macro('validate', function ($rules) use ($customRule) {
            TestCase::assertSame(['required', 'email', $customRule, 'regex:/EmailAddress/'], $rules['email']);
            TestCase::assertSame('required|email', $rules['contact']);
            TestCase::assertSame('email', $rules['alternate']);
            TestCase::assertSame('nullable|email:rfc', $rules['native']);
            throw new RuntimeException('Validation stopped persistence');
        });
        $this->expectExceptionMessage('Validation stopped persistence');
        $controller->store(Request::create('/items', 'POST'));
    }

}
