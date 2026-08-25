<?php
namespace Csgt\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    public $timestamps = false;

    protected $table = 'clients';

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'client_tag', 'client_id', 'tag_id');
    }

    /**
     * setField('multi') calls fetch{Field}() to build the combo options.
     */
    public function fetchTags()
    {
        return new Collection;
    }

    public function fetchTagsColumn()
    {
        return 'name';
    }
}
