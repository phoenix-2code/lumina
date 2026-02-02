<?php
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
        $text = preg_replace('/on\w+=\'[^\']*\'/i', '', $text);
        
        return $text;
    }

    public static function formatCommentary($text) {
        if (!$text) return "";
        
        // 1. Sanitize input first
        $text = self::sanitizeHTML($text);
        
        // 2. Strip legacy control characters (non-printable binary junk)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);

        // 3. Atomic Replacement: Match EITHER Chevrons OR Hex Strings
        return preg_replace_callback('/(»([0-9A-F]+)«)|([0-9A-F]{3,})/', function($matches) {
            
            // CASE A: Chevron Match
            if (!empty($matches[1])) {
                $val = $matches[2];
                $id = ctype_digit($val) ? intval($val) : hexdec($val);
                
                if ($id >= 1 && $id <= 31102) {
                    $ref = Helper::getVerseRefFromID($id);
                    if ($ref) {
                        $b = addslashes($ref['book']);
                        return "<span class='ref-link' onclick='jumpTo(\"{$b}\", {$ref['chapter']}, {$ref['verse']})'>{$ref['book']} {$ref['chapter']}:{$ref['verse']}</span>";
                    }
                }
                return "[$val]";
            }
            
            // CASE B: Hex Block
            $raw = $matches[0];
            if (in_array($raw, ['THE', 'AND', 'GOD', 'BAD', 'DAD', 'FADE', 'FEED', 'FACE', 'DEAD', 'BED', 'BEEF', 'ACE', 'ADD'])) return $raw;

            $res = ""; $i = 0; $len = strlen($raw);
            while ($i < $len) {
                $vid = -1; $step = 1;
                if ($i + 4 <= $len) {
                    $v = hexdec(substr($raw, $i, 4));
                    if ($v >= 1 && $v <= 31102) { $vid = $v; $step = 4; }
                }
                if ($vid == -1 && $i + 3 <= $len) {
                    $v = hexdec(substr($raw, $i, 3));
                    if ($v >= 1 && $v <= 31102) { $vid = $v; $step = 3; }
                }

                if ($vid != -1) {
                    $ref = Helper::getVerseRefFromID($vid);
                    if ($ref) {
                        $b = addslashes($ref['book']);
                        $res .= "<span class='ref-link' onclick='jumpTo(\"{$b}\", {$ref['chapter']}, {$ref['verse']})'>{$ref['book']} {$ref['chapter']}:{$ref['verse']}</span> ";
                        $i += $step;
                        continue;
                    }
                }
                $res .= $raw[$i];
                $i++;
            }
            return $res;
        }, $text);
    }
}
