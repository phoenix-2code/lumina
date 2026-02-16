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
        
        // 2. Strip control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);

        // 3. Simple Reference Replacement (matches »123« or hex »A1B«)
        $text = preg_replace_callback('/»([0-9A-Fa-f]+)«/', function($matches) {
            $val = $matches[1];
            $id = ctype_digit($val) ? intval($val) : hexdec($val);
            
            if ($id >= 1 && $id <= 31102) {
                $verse = Verse::onVersion('KJV')->with('book')->find($id);
                if ($verse) {
                    $bookName = addslashes($verse->book->name);
                    return "<span class='ref-link' onclick=\"jumpTo('{$bookName}', {$verse->chapter}, {$verse->verse})\">{$verse->book->name} {$verse->chapter}:{$verse->verse}</span>";
                }
            }
            return "[$val]";
        }, $text);

        return $text;
    }
}
