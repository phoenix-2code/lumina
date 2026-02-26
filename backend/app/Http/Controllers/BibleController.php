<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Verse;
use App\Services\TextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BibleController extends Controller
{
    public function getChapter(Request $request)
    {
        $bookName = $request->get('book', 'Genesis');
        $chapter = (int)$request->get('chapter', 1);
        $version = $request->get('version', 'KJV');
        $interlinear = $request->get('interlinear', 'false');

        $book = Book::where('name', $bookName)->first();

        if (!$book) {
            return response()->json(['error' => 'Book not found'], 404);
        }

        $verses = Verse::onVersion($version)
            ->where('book_id', $book->id)
            ->where('chapter', $chapter)
            ->where('version', $version)
            ->get();

        // Add commentary availability (available modules)
        foreach ($verses as &$v) {
            // Find the KJV counterpart ID for study tools
            $kjvId = ($version === 'KJV') ? $v->id : Verse::onVersion('KJV')
                ->where('book_id', $book->id)
                ->where('chapter', $chapter)
                ->where('verse', $v->verse)
                ->value('id');

            if ($kjvId) {
                $v->modules = DB::connection('commentaries')
                    ->table('commentary_entries as ce')
                    ->join('commentaries as c', 'ce.commentary_id', '=', 'c.id')
                    ->where('ce.verse_id', $kjvId)
                    ->pluck('c.abbreviation')
                    ->implode(',');
            }

            if ($interlinear === 'true') {
                $words = DB::connection('extras')
                    ->table('verse_words as vw')
                    ->leftJoin('lexicon as l', 'vw.strongs_id', '=', 'l.id')
                    ->where('vw.verse_id', $v->id)
                    ->orderBy('vw.position')
                    ->get();

                if ($words->isNotEmpty()) {
                    $html = "";
                    foreach($words as $w) {
                        $wordText = TextService::sanitizeHTML($w->word);
                        if ($w->strongs_id) {
                            $tag = $w->transliteration ?: $w->strongs_id;
                            $lexType = (strpos($w->strongs_id, 'G') === 0) ? 'strong_greek' : 'strong_hebrew';
                            $html .= "$wordText <span class='strongs-tag' onclick=\"event.stopPropagation(); showDef('{$w->strongs_id}', '$lexType')\">&lt;$tag&gt;</span> ";
                        } else {
                            $html .= "$wordText ";
                        }
                    }
                    $v->text = trim($html);
                }
            } else {
                $v->text = TextService::sanitizeHTML($v->text);
            }
        }

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
            $v2 = Verse::onVersion('NIV')->distinct()->pluck('version');
            
            return response()->json([
                'versions' => $v1->concat($v2)->unique()->values()
            ]);
        }
    
            public function getVerse(Request $request)
            {
                $bookName = $request->get('book');
                $chapter = (int)$request->get('chapter');
                $verseNum = (int)$request->get('verse');
                $endVerse = $request->get('end_verse');
                $version = $request->get('version', 'KJV');
        
                $book = Book::where('name', $bookName)->first();
                if (!$book) return response()->json(['error' => 'Book not found'], 404);
        
                $query = Verse::onVersion($version)
                    ->where('book_id', $book->id)
                    ->where('chapter', $chapter)
                    ->where('version', $version);
        
                if ($endVerse) {
                    $verses = $query->whereBetween('verse', [$verseNum, (int)$endVerse])
                        ->orderBy('verse')
                        ->get();
                    
                    $text = "";
                    foreach($verses as $v) {
                        $text .= "<sup>{$v->verse}</sup> " . TextService::sanitizeHTML($v->text) . " ";
                    }
                    return response()->json(['text' => trim($text)]);
                } else {
                    $verse = $query->where('verse', $verseNum)->first();
                    return response()->json([
                        'text' => $verse ? TextService::sanitizeHTML($verse->text) : "Verse not found."
                    ]);
                }
            }
        
    }
    