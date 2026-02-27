<?php

namespace App\Services;

use App\Models\Verse;

class TextService {
    /**
     * Sanitizes raw text from DB/User to prevent XSS while allowing specific study tags.
     */
    public static function sanitizeHTML($text) {
        if (!$text) return "";
        
        // Remove any existing script tags entirely
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $text);
        
        // Whitelist safe tags
        $allowedTags = '<span><b><i><br><h3><div><mark><a><sup>';
        $text = strip_tags($text, $allowedTags);
        
        // Remove dangerous attributes (onmouseover, etc.)
        $text = preg_replace('/on\w+="[^"]*"/i', '', $text);
        $text = preg_replace("/on\w+='[^']*'/i", '', $text);
        
        return $text;
    }

    public static function formatCommentary($text) {
        if (!$text) return "";
        
        // 1. Sanitize input
        $text = self::sanitizeHTML($text);
        
        // 2. Handle bold tags \x07
        $text = preg_replace("/\x07(.*?)\x07/", "<b>$1</b>", $text);

        // 3. Handle italic tags \x06
        $text = preg_replace("/\x06(.*?)\x06/", "<i>$1</i>", $text);

        // 4. Handle sequential reference tags \x03 (prevents Genesis 1:1Genesis 1:2)
        $text = preg_replace_callback("/((\x03[0-9A-Fa-f-]+\x03)+)/", function($matches) {
            $allHex = $matches[1];
            // Split by the delimiters and filter empty
            $parts = array_filter(explode("\x03", $allHex));
            
            $results = [];
            foreach ($parts as $p) {
                $results[] = self::resolveHexReference($p);
            }
            return implode(", ", $results);
        }, $text);

        // 5. Handle standalone reference tags \x03 (if any escaped step 4)
        $text = preg_replace_callback("/\x03([0-9A-Fa-f-]+)\x03/", function($matches) {
            return self::resolveHexReference($matches[1]);
        }, $text);

        // 6. Handle bare hex references in parentheses (e.g. (580458C3))
        $text = preg_replace_callback("/\(([0-9A-Fa-f]{4,})\)/", function($matches) {
            return "(" . self::resolveHexReference($matches[1]) . ")";
        }, $text);

        // 5. Strip remaining control characters
        $text = preg_replace('/[\x00-\x1F]/', '', $text);

        // 6. Legacy »...« logic (re-using the new resolver)
        $text = preg_replace_callback('/»([0-9A-Fa-f]+)«/', function($matches) {
            return self::resolveHexReference($matches[1]);
        }, $text);

        return $text;
    }

    private static function resolveHexReference($hex) {
        // Handle Ranges (e.g., 5749-575D)
        if (strpos($hex, '-') !== false) {
            $parts = explode('-', $hex);
            $startId = hexdec($parts[0]);
            $endId = hexdec($parts[1]);
            
            // Validate IDs
            if ($startId < 1 || $startId > 31102 || $endId < 1 || $endId > 31102) {
                \Illuminate\Support\Facades\Log::error("Invalid range resolution: {$hex} -> {$startId}-{$endId}");
                return "[$hex]";
            }

            $startVerse = Verse::onVersion('KJV')->with('book')->find($startId);
            $endVerse = Verse::onVersion('KJV')->with('book')->find($endId);
            
            if (!$startVerse || !$endVerse) return "[$hex]";
            
            $bookName = $startVerse->book->name;
            $safeBook = addslashes($bookName);
            
            if ($startVerse->book_id === $endVerse->book_id) {
                if ($startVerse->chapter === $endVerse->chapter) {
                    $label = "{$bookName} {$startVerse->chapter}:{$startVerse->verse}-{$endVerse->verse}";
                } else {
                    $label = "{$bookName} {$startVerse->chapter}:{$startVerse->verse}-{$endVerse->chapter}:{$endVerse->verse}";
                }
            } else {
                $label = "{$bookName} {$startVerse->chapter}:{$startVerse->verse} - {$endVerse->book->name} {$endVerse->chapter}:{$endVerse->verse}";
            }
            
            return "<span class=\"ref-link\" data-book=\"{$bookName}\" data-chapter=\"{$startVerse->chapter}\" data-verse=\"{$startVerse->verse}\" data-end-verse=\"{$endVerse->verse}\" onclick=\"jumpTo('{$safeBook}', {$startVerse->chapter}, {$startVerse->verse})\">{$label}</span>";
        }
        
        // Handle Lists or Single (e.g., 580458C3 or 5749)
        if (strlen($hex) >= 4 && strlen($hex) % 4 === 0) {
            $refs = [];
            for ($i = 0; $i < strlen($hex); $i += 4) {
                $id = hexdec(substr($hex, $i, 4));
                if ($id >= 1 && $id <= 31102) {
                    $verse = Verse::onVersion('KJV')->with('book')->find($id);
                    if ($verse) {
                        $bookName = $verse->book->name;
                        $safeBook = addslashes($bookName);
                        $refs[] = "<span class=\"ref-link\" data-book=\"{$bookName}\" data-chapter=\"{$verse->chapter}\" data-verse=\"{$verse->verse}\" onclick=\"jumpTo('{$safeBook}', {$verse->chapter}, {$verse->verse})\">{$verse->book->name} {$verse->chapter}:{$verse->verse}</span>";
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning("Invalid list ID: " . substr($hex, $i, 4) . " -> {$id}");
                }
            }
            return count($refs) > 0 ? implode('; ', $refs) : "[$hex]";
        }

        // Resolve single ID (ALWAYS treat as hex for legacy compatibility)
        $id = hexdec($hex);
        if ($id >= 1 && $id <= 31102) {
            $verse = Verse::onVersion('KJV')->with('book')->find($id);
            if ($verse) {
                $bookName = $verse->book->name;
                $safeBook = addslashes($bookName);
                return "<span class=\"ref-link\" data-book=\"{$bookName}\" data-chapter=\"{$verse->chapter}\" data-verse=\"{$verse->verse}\" onclick=\"jumpTo('{$safeBook}', {$verse->chapter}, {$verse->verse})\">{$verse->book->name} {$verse->chapter}:{$verse->verse}</span>";
            }
        }

        \Illuminate\Support\Facades\Log::error("Failed to resolve commentary code: {$hex} (Dec: {$id})");
        return "[$hex]";
    }
}
