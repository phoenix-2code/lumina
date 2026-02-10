<?php

namespace App\Http\Controllers;

use App\Services\TextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q');
        $version = $request->get('version', 'KJV');
        $offset = (int)$request->get('offset', 0);

        // Sanitize search query
        $cleanQuery = preg_replace('/[^a-zA-Z0-9 ]/', '', $query);
        if (!$cleanQuery) {
            return response()->json(['results' => [], 'count' => 0]);
        }

        // Determine which database alias to use
        $dbAlias = ($version === 'KJV') ? 'main' : 'versions';

        // 1. Get Total Count
        $total = DB::connection('core')->selectOne("
            SELECT COUNT(*) as total 
            FROM $dbAlias.verses_fts 
            WHERE verses_fts MATCH ? AND version = ?
        ", [$cleanQuery, $version])->total;

        // 2. Fetch Paginated Results
        $results = DB::connection('core')->select("
            SELECT 
                b.name as book_name, 
                v.chapter, 
                v.verse, 
                highlight(verses_fts, 0, '[[MARK]]', '[[/MARK]]') as text 
            FROM $dbAlias.verses_fts v 
            JOIN main.books b ON v.book_id = b.id 
            WHERE v.verses_fts MATCH ? AND v.version = ?
            ORDER BY v.book_id, v.chapter, v.verse 
            LIMIT 200 OFFSET ?
        ", [$cleanQuery, $version, $offset]);

        // 3. Format results
        $formattedResults = collect($results)->map(function($r) {
            return [
                'book_name' => $r->book_name,
                'chapter' => $r->chapter,
                'verse' => $r->verse,
                'text' => str_replace(['[[MARK]]', '[[/MARK]]'], ['<mark>', '</mark>'], TextService::sanitizeHTML($r->text))
            ];
        });

        return response()->json([
            'results' => $formattedResults,
            'count' => $total
        ]);
    }
}