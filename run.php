<?php

# https://xdebug.org/docs/all_settings#trace_format

# read .config
$f = '';
foreach (explode("\n", trim(file_get_contents('.config'))) as $line) {
    list($k, $v) = explode("=", $line);
    if ($k == 'trace_file') {
        $f = $v;
    }
}

foreach (explode("\n", file_get_contents($f)) as $r) {
    $a = explode("\t", $r);
    # non-record line
    if (count($a) == 1) {
        continue;
    }
    $type = $a[2];
    // also a non-record line
    if ($type == '') {
        continue;
    }
    // variable assignment
    if ($type == 'A') {
        continue;
    }
    // function entry
    if ($type == '0') {

    }
    // function exit
    if ($type == '0') {

    }
    // function return
    if ($type == 'R') {

    }
}
