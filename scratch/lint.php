<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/..');
$it = new RecursiveIteratorIterator($dir);
$errors = 0;
$checked = 0;
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, 'node_modules') !== false || strpos($path, 'vendor') !== false) continue;
        $checked++;
        $out = [];
        $ret = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $ret);
        if ($ret !== 0) {
            echo "LINT ERROR: $path\n  " . implode("\n  ", $out) . "\n";
            $errors++;
        }
    }
}
echo "Checked $checked PHP files. Total errors found: $errors\n";

