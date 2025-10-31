<?php

$files = glob(__DIR__ . '/adminer-*.php');
sort($files);
$file = $files ? $files[count($files) - 1] : null;

if ($file && !is_file($file) && is_readable($file)) {
    echo "Install Adminer using `composer install` and run `./build-adminer`\n";
    exit(1);
}

require $file;
