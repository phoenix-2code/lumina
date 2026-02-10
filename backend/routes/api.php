<?php

use App\Http\Controllers\BibleController;
use App\Http\Controllers\StudyController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/bible/chapter', [BibleController::class, 'getChapter']);
Route::get('/bible/versions', [BibleController::class, 'getVersions']);

Route::get('/study/commentary', [StudyController::class, 'getCommentary']);
Route::get('/study/commentary-list', [StudyController::class, 'getCommentaryList']);
Route::get('/study/xrefs', [StudyController::class, 'getCrossReferences']);
Route::get('/study/definition', [StudyController::class, 'getDefinition']);

Route::get('/search', [SearchController::class, 'search']);

Route::get('/topics', function (Illuminate\Http\Request $request) {
    $db = Illuminate\Support\Facades\DB::connection('core');
    $m = strtoupper($request->get('module', 'EASTON'));
    $table = ($m == 'HEBREW' || $m == 'GREEK') ? 'extras.lexicon' : 'extras.dictionaries';
    $field = ($m == 'HEBREW' || $m == 'GREEK') ? 'id' : 'topic';
    
    $results = $db->table($table)
        ->when($m == 'HEBREW' || $m == 'GREEK', function($q) use ($m, $field) {
            return $q->where($field, 'LIKE', ($m=='HEBREW'?'H':'G').'%');
        }, function($q) use ($m) {
            return $q->where('module', $m);
        })
        ->orderBy($field)
        ->pluck($field);

    return response()->json(['topics' => $results->map(fn($t) => ['id' => $t, 'label' => $t])]);
});