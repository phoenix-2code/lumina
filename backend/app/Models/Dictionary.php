<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dictionary extends Model
{
    protected $connection = 'extras';
    protected $table = 'dictionaries';
    public $timestamps = false;
}
