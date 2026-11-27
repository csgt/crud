<?php
namespace Csgt\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public $timestamps = false;

    protected $table = 'countries';
}
