<?php
namespace Csgt\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;

    protected $table = 'tags';
}
