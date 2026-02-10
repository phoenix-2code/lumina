<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $connection = 'core';
    protected $table = 'books';
    public $timestamps = false;

    public function verses()
    {
        return $this->hasMany(Verse::class, 'book_id', 'id');
    }
}
