<?php

// php variant of function in http://www.endmemo.com/unicode/script/convertuni.js
function convertDecNCR2Hex($decNCR)
{
    preg_match_all('/[0-9]+/', $decNCR, $matches);
    $ints = $matches[0];
    $codepoint = "";
    $haut = 0;

    for ($i = 0; $i < count($ints); $i++) {
        $b = (int) $ints[$i];
        if ($b < 0 || $b > 0xFFFF) {
            die('Invalid b range 1');
        }
        if ($haut != 0) {
            if (0xDC00 <= $b && $b <= 0xDFFF) {
                $codepoint .= dechex(0x10000 + (($haut - 0xD800) << 10) + ($b - 0xDC00));
                $haut = 0;
                continue;
            } else {
                die('Invalid b range 2');
            }
        }
        if (0xD800 <= $b && $b <= 0xDBFF) {
            $haut = $b;
        } else {
            $codepoint .= dechex($b);
        }
    }
    return $codepoint;
}

$in = fopen($argv[1], 'r');
$out = fopen($argv[2], 'c');

$emojiCache = [];

while ($line = fgets($in)) {

    // find emojis that are surrogate pairs and replace them with unicode representations of the format emoji[code]
    // the emoji[] wrapper is to allow for easy regex in render phase without violating XML validation (which the &#x;
    // notation does)
    if (preg_match_all('/(&#[0-9]+;){2}/', $line, $matches)) {
        echo "Old line: $line\n";
        $emojiCodes = $matches[0];
        foreach ($emojiCodes as $emojiCode) {
            if (isset($emojiCache[$emojiCode])) {
                $hex = $emojiCache[$emojiCode];
            }
            else {
                $hex = convertDecNCR2Hex($emojiCode);
                $emojiCache[$emojiCode] = $hex;
            }

            $line = str_replace($emojiCode, "emoji[$hex]", $line);
        }
        echo "New line: $line\n";
    }

    // find other NCRs that were causing XML validation errors and replace them with parseable entries or remove them
    if (preg_match_all('/(&#[0-9]+;){1}/', $line, $matches)) {
        echo "Old line: $line\n";

        $line = str_replace("&#10;", "[newline]", $line);
        $line = str_replace("&#0;", '', $line);

        echo "New line: $line\n";
    }

    fputs($out, $line);
}

fclose($in);
fflush($out);
fclose($out);
