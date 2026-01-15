<?php
// lib/TextConverter.php

class TextConverter {
    public static function smiToSrt($content) {
        // 기존 로직 유지
        $smi = preg_replace('/<[^>]+>/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($smi));

        $srt = '';
        $i = 1;
        $time = 0;

        foreach ($lines as $line) {
            if (!trim($line)) continue;
            $srt .= $i++ . "\n";
            $srt .= gmdate("H:i:s", $time) . ",000 --> " . gmdate("H:i:s", $time + 2) . ",000\n";
            $srt .= $line . "\n\n";
            $time += 3;
        }
        return $srt;
    }

    public static function srtToSmi($content) {
        // 기존 로직 유지
        $lines = preg_split("/\r\n|\n|\r/", trim($content));
        $smi = "<SAMI>\n<BODY>\n";

        foreach ($lines as $line) {
            if (preg_match('/^\d+$/', $line)) continue;
            if (strpos($line, '-->') !== false) continue;
            if (!trim($line)) continue;
            $smi .= "<SYNC Start=0><P>$line\n";
        }

        return $smi . "</BODY>\n</SAMI>";
    }
}