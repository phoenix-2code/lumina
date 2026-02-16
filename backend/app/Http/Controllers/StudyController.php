<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Verse;
use App\Models\Commentary;
use App\Models\CommentaryEntry;
use App\Models\CrossReference;
use App\Models\Dictionary;
use App\Services\TextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyController extends Controller
{
    public function getCommentary(Request $request)
    {
        $bookName = $request->get('book');
        $chapter = (int)$request->get('chapter');
        $verse = (int)$request->get('verse');
        $abbr = strtolower($request->get('module', 'mhc'));

        // 1. Get Canonical ID
        $book = Book::where('name', $bookName)->first();
        if (!$book) return response()->json(['error' => 'Book not found'], 404);

        $verseId = Verse::onVersion('KJV')
            ->where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('verse', $verse)
            ->value('id');

        if (!$verseId) return response()->json(['error' => 'Verse not found'], 404);

        // 2. Fetch Commentary
        $entry = CommentaryEntry::where('verse_id', $verseId)
            ->whereHas('commentary', function($q) use ($abbr) {
                $q->where('abbreviation', $abbr);
            })
            ->first();

        return response()->json([
            'text' => $entry ? TextService::formatCommentary($entry->text) : "No commentary found for this verse."
        ]);
    }

    public function getCrossReferences(Request $request)
    {
        $bookName = $request->get('book');
        $chapter = (int)$request->get('chapter');
        $verseNum = (int)$request->get('verse');

        $book = Book::where('name', $bookName)->first();
        if (!$book) return response()->json(['error' => 'Book not found'], 404);

        $verseId = Verse::onVersion('KJV')
            ->where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('verse', $verseNum)
            ->value('id');

        $refs = CrossReference::where('from_verse_id', $verseId)->get();
        
        $results = $refs->map(function($ref) {
            $v = Verse::onVersion('KJV')->with('book')->find($ref->to_verse_id);
            return [
                'book' => $v->book->name,
                'chapter' => $v->chapter,
                'verse' => $v->verse
            ];
        });

        return response()->json(['xrefs' => $results]);
    }

    public function getDefinition(Request $request)
    {
        $term = $request->get('term', '');
        $type = $request->get('type', 'dictionary');
        $module = $request->get('module', 'EASTON');

        if (!$term) return response()->json(['error' => 'Missing term'], 400);

        if ($type == 'strong_hebrew' || $type == 'strong_greek') {
            $text = DB::connection('extras')->table('lexicon')->where('id', $term)->value('definition');
        } else {
            $text = Dictionary::where('topic', $term)->where('module', strtoupper($module))->value('definition');
        }
        
        return response()->json([
            'definition' => $text ? TextService::formatCommentary($text) : "Not found."
        ]);
    }

    public function getCommentaryList()
    {
        $modules = Commentary::pluck('abbreviation')->map('strtoupper');
        
        // Fallback if DB is empty or missing modules
        if ($modules->isEmpty()) {
            $modules = collect(['MHC', 'BARNES', 'JFB', 'ACC', 'RWP']);
        }
        
        return response()->json([
            'modules' => $modules
        ]);
    }
}