<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrossReference extends Model
{
    protected $connection = 'extras';
    protected $table = 'cross_references';
    public $timestamps = false;

    public function fromVerse()
    {
        return $this->belongsTo(Verse::class, 'from_verse_id', 'id');
    }

    public function toVerse()
    {
        return $this->belongsTo(Verse::class, 'to_verse_id', 'id');
    }
}
