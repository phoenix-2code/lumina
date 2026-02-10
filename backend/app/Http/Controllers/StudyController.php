<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Verse;
use App\Models\Commentary;
use App\Models\CommentaryEntry;
use App\Models\CrossReference;
use App\Models\Dictionary;
use Illuminate\Http\Request;

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
            'text' => $entry ? $entry->text : "No commentary found for this verse."
        ]);
    }

    public function getCrossReferences(Request $request)
    {
        $bookName = $request->get('book');
        $chapter = (int)$request->get('chapter');
        $verseNum = (int)$request->get('verse');

        $book = Book::where('name', $bookName)->first();
        $verseId = Verse::onVersion('KJV')
            ->where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('verse', $verseNum)
            ->value('id');

        $refs = CrossReference::where('from_verse_id', $verseId)->get();
        
        $results = $refs->map(function($ref) {
            // This is a powerful feature: Laravel can "reach across" to the main DB 
            // even though the reference is in the extras DB.
            $v = Verse::onVersion('KJV')->find($ref->to_verse_id);
            return [
                'book' => $v->book->name,
                'chapter' => $v->chapter,
                'verse' => $v->verse
            ];
        });

        return response()->json(['xrefs' => $results]);
    }

    public function getCommentaryList()
    {
        return response()->json([
            'modules' => Commentary::pluck('abbreviation')->map('strtoupper')
        ]);
    }
}
