<?php

spl_autoload_register(function (string $class): void {
    $map = [
        'Herbarium\\'       => __DIR__ . '/src/',
        'Firebase\\JWT\\'   => __DIR__ . '/lib/Firebase/JWT/',
    ];

    foreach ($map as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) === 0) {
            $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
    }
});

require __DIR__ . '/src/helpers.php';
