<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commentary extends Model
{
    protected $connection = 'commentaries';
    protected $table = 'commentaries';
    public $timestamps = false;

    public function entries()
    {
        return $this->hasMany(CommentaryEntry::class, 'commentary_id', 'id');
    }
}
