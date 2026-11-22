<?php

declare(strict_types=1);

// Stand-in for Composer's PSR-4 autoloader (Bmailu\ => src/).
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Bmailu\\')) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');
