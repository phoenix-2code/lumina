<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verse extends Model
{
    // Default to core (KJV)
    protected $connection = 'core';
    protected $table = 'verses';
    public $timestamps = false;

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }

    /**
     * Dynamically switch connection for non-KJV versions
     */
    public static function onVersion($version = 'KJV')
    {
        $instance = new static;
        if ($version !== 'KJV') {
            $instance->setConnection('versions');
        }
        return $instance->newQuery();
    }
}
