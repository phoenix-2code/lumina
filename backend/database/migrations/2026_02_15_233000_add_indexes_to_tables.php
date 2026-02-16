<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexes for 'core' connection
        Schema::connection('core')->table('books', function (Blueprint $table) {
            $table->index('name');
            $table->index('book_number');
        });
        Schema::connection('core')->table('verses', function (Blueprint $table) {
            $table->index(['book_id', 'chapter', 'verse']);
        });

        // Indexes for 'commentaries' connection
        Schema::connection('commentaries')->table('commentary_entries', function (Blueprint $table) {
            $table->index('verse_id');
        });

        // Indexes for 'extras' connection
        Schema::connection('extras')->table('dictionaries', function (Blueprint $table) {
            $table->index(['topic', 'module']);
        });
        Schema::connection('extras')->table('lexicon', function (Blueprint $table) {
            $table->index('transliteration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes if the migration is rolled back
        Schema::connection('core')->table('books', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['book_number']);
        });
        Schema::connection('core')->table('verses', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'chapter', 'verse']);
        });

        Schema::connection('commentaries')->table('commentary_entries', function (Blueprint $table) {
            $table->dropIndex(['verse_id']);
        });

        Schema::connection('extras')->table('dictionaries', function (Blueprint $table) {
            $table->dropIndex(['topic', 'module']);
        });
        Schema::connection('extras')->table('lexicon', function (Blueprint $table) {
            $table->dropIndex(['transliteration']);
        });
    }
};
