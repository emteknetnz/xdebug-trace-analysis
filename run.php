<?php

# https://xdebug.org/docs/all_settings#trace_format

// read .config
$f = '';
foreach (explode("\n", trim(file_get_contents('.config'))) as $line) {
    list($k, $v) = explode("=", $line);
    if ($k == 'trace_file') {
        $f = $v;
    }
}

$unknown_args = [];

function get_arg_types($args) {
    global $unknown_args;
    $arg_types = [];
    foreach ($args as $arg) {
        if ($arg == '???') {
            // args = [???, 'abc', 123] < variadic
            // args = ['def', ???] < using default param for 2nd arg
            $arg_types[] = 'variadic/default'; // TODO separate
            break;
        } elseif (substr($arg, 0, 1) == '[') {
            $arg_types[] = 'array';
        } elseif (substr($arg, 0, 1) == "'") {
            $arg_types[] = 'string';
        } elseif ($arg === '0' || preg_match('#^-?[1-9]+[0-9]*$#', $arg)) {
            $arg_types[] = 'int';
        } elseif (preg_match('#^-?[0-9\.]+$#', $arg) && substr_count($arg, '.') == 1) {
            $arg_types[] = 'float';
        } elseif ($arg == 'TRUE' || $arg == 'FALSE') {
            $arg_types[] = 'bool';
        } elseif ($arg == 'NULL') {
            $arg_types[] = 'null';
        } elseif (preg_match('#^class ([A-Za-z0-9_\\\]+) {#', $arg, $m)) {
            $arg_types[] = $m[1];
        } else {
            $arg_types[] = 'unknown';
            $unknown_args[] = $arg;
        }
    }
    return $arg_types;
}

$prev = [];
foreach (explode("\n", file_get_contents($f)) as $r) {
    $a = explode("\t", $r);
    // non-record line
    if (count($a) == 1) {
        continue;
    }
    // add an empty value at the start because docs use 1-index
    array_unshift($a, null);
    $level = $a[1];
    $function_num = $a[2];
    $iden = "$level-$function_num";
    $iden = $function_num;
    $type = $a[3];
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
        // $time_index = $a[4];
        // $memory_usage = $a[5];
        $function_name = $a[6]; // namespace\classname::func
        $function_type = $a[7] == 1 ? 'user-defined' : 'internal';
        $require_filename = $a[8];
        $filename = $a[9]; // the filename of what is CALLING the function
        $line_number = $a[10];
        $num_args = $a[11];
        $args = array_slice($a, 12, $num_args);
        if ($filename != '' && $require_filename != '') {
            continue;
            // print_r([
            //     $filename,
            //     $require_filename,
            //     $function_name
            // ]);
            // [0] => /var/www/vendor/composer/ClassLoader.php
            // [1] => /var/www/vendor/symfony/var-exporter/Internal/Exporter.php
            // [2] => include
        }
        if ($function_type == 'internal') {
            continue;
        }
        if (strpos($function_name, 'SilverStripe') === false) {
            continue;
        }
        
        if (preg_match('#(::|->)#', $function_name)) {
            list($class, $method) = preg_split('#(::|->)#', $function_name);
        } else {
            preg_match('#^(.+?)\\\([A-Za-z0-9_]+)$#', $function_name, $m);
            $class = $m[1];
            $method = $m[2];
        }
        $prev[$iden] = [
            'class' => $class,
            'method' => $method,
            'line_number' => $line_number,
            'arg_types' => get_arg_types($args)
        ];
    }
    // function exit
    if ($type == '1') {
        continue;
    }
    // function return
    if ($type == 'R') {
        if (!isset($prev[$iden]['method'])) {
            continue;
        }
        $prev[$iden]['return_type'] = get_arg_types([$a[6]])[0];
    }
}

$no_returns = [];

foreach ($prev as $iden => $data) {
    if (array_key_exists('return_type', $data)) {
        continue;
    }
    // could be either:
    // - no return in function
    // - return $this;
    echo "$iden\n";
    $prev[$iden]['return_type'] = 'unknown';
    $cml = "$class::$method:$line_number";
    if (!in_array($cml, $no_returns)) {
        $no_returns[] = $cml;
    }
}

$things = [];
foreach ($prev as $iden => $data) {
    $class = $data['class'];
    $method = $data['method'];
    $line_number = $data['line_number'];
    $arg_types = $data['arg_types'];
    $return_type = $data['return_type'];
    $cml = "$class::$method:$line_number";
    $things[$cml] ??= ['arg_types' => [], 'return_types' => []];
    for ($i = 0; $i < count($arg_types); $i++) {
        $arg_type = $arg_types[$i];
        $things[$cml]['arg_types'][$i] ??= [];
        if (!in_array($arg_type, $things[$cml]['arg_types'][$i])) {
            $things[$cml]['arg_types'][$i][] = $arg_type;
        }
        if (!in_array($return_type, $things[$cml]['return_types'])) {
            $things[$cml]['return_types'][] = $return_type;
        }
    }
}
//print_r($things);
print_r($no_returns);

// method calls without traced return (not sure how this is possible)

// print_r(array_keys($prev)); // << at there func calls i care about without return types?