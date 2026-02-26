<?php

use App\Http\Controllers\BibleController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/up', function() {
    return response()->json(['status' => 'ok']);
});

Route::get('/bible/chapter', [BibleController::class, 'getChapter']);
Route::get('/bible/verse', [BibleController::class, 'getVerse']);
Route::get('/bible/versions', [BibleController::class, 'getVersions']);

Route::get('/study/commentary', [StudyController::class, 'getCommentary']);
Route::get('/study/commentary-list', [StudyController::class, 'getCommentaryList']);
Route::get('/study/xrefs', [StudyController::class, 'getCrossReferences']);
Route::get('/study/definition', [StudyController::class, 'getDefinition']);

Route::get('/search', [SearchController::class, 'search']);

Route::get('/topics', function (Illuminate\Http\Request $request) {
    $m = strtoupper($request->get('module', 'EASTON'));
    $isLex = ($m == 'HEBREW' || $m == 'GREEK');
    
    $table = $isLex ? 'lexicon' : 'dictionaries';
    $field = $isLex ? 'id' : 'topic';
    
    $results = Illuminate\Support\Facades\DB::connection('extras')
        ->table($table)
        ->when($isLex, function($q) use ($m, $field) {
            return $q->where($field, 'LIKE', ($m=='HEBREW'?'H':'G').'%');
        }, function($q) use ($m) {
            return $q->where('module', $m);
        })
        ->orderBy($field)
        ->limit(1000) // Optimization: Don't load 20,000+ words at once
        ->pluck($field);

    return response()->json(['topics' => $results->map(fn($t) => ['id' => $t, 'label' => $t])]);
});