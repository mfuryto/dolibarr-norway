<?php
// SPDX-License-Identifier: GPL-3.0-or-later
function assertPdfLanguage($path, $title, $expected = true)
{
    insist(is_file($path), 'PDF missing');
    $pdf = file_get_contents($path);
    insist(strpos($pdf, '%PDF-') === 0, 'Not a PDF');
    $parts = array();
    // Read the declared byte length. Treating a trailing compressed CR byte as
    // part of the newline delimiter can corrupt zlib data nondeterministically.
    preg_match_all('/\/Length\s+([0-9]+)\s*>>\s*stream(?:\r\n|\n|\r)/', $pdf, $streams, PREG_OFFSET_CAPTURE);
    foreach ($streams[0] as $i => $header) {
        $stream = substr($pdf, $header[1] + strlen($header[0]), (int) $streams[1][$i][0]);
        $decoded = @gzuncompress($stream);
        $parts[] = $decoded !== false ? $decoded : $stream;
    }
    $unicode = mb_convert_encoding($title, 'UTF-16BE', 'UTF-8');
    foreach ($parts as $part) {
        if (strpos($part, $title) !== false || strpos($part, $unicode) !== false
            || stripos($part, bin2hex($unicode)) !== false) {
            insist($expected, 'Unexpected PDF text: '.$title);
            return;
        }
    }
    if ($expected) { throw new RuntimeException('Expected PDF text missing: '.$title); }
}
