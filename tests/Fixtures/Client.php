<?php
namespace Csgt\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    public $timestamps = false;

    protected $table = 'clients';

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
