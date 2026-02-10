<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Verse;
use Illuminate\Http\Request;

class BibleController extends Controller
{
    public function getChapter(Request $request)
    {
        $bookName = $request->get('book', 'Genesis');
        $chapter = (int)$request->get('chapter', 1);
        $version = $request->get('version', 'KJV');

        $book = Book::where('name', $bookName)->first();

        if (!$book) {
            return response()->json(['error' => 'Book not found'], 404);
        }

        $verses = Verse::onVersion($version)
            ->where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('version', $version)
            ->get();

        return response()->json([
            'book' => $bookName,
            'chapter' => $chapter,
            'version' => $version,
            'verses' => $verses
        ]);
    }

    public function getVersions()
    {
        $v1 = Verse::onVersion('KJV')->distinct()->pluck('version');
        $v2 = Verse::onVersion('NIV')->distinct()->pluck('version'); // Triggers 'versions' connection
        
        return response()->json([
            'versions' => $v1->concat($v2)->unique()->values()
        ]);
    }
}
