<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentaryEntry extends Model
{
    protected $connection = 'commentaries';
    protected $table = 'commentary_entries';
    public $timestamps = false;

    public function commentary()
    {
        return $this->belongsTo(Commentary::class, 'commentary_id', 'id');
    }
}
