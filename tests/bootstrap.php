<?php
declare(strict_types=1);

// Autoloads our own Sunburstfs\* classes straight from the real
// install-tree path (usr/share/openmediavault/engined/rpc/sunburstfs/)
// so tests exercise the exact same files that get packaged -- no
// separate "source" copy to drift out of sync with what ships.
spl_autoload_register(static function (string $class): void {
    $prefix = "Sunburstfs\\";
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . "/../usr/share/openmediavault/engined/rpc/sunburstfs/"
        . str_replace("\\", "/", $relative) . ".php";
    if (is_file($path)) {
        require $path;
    }
});
