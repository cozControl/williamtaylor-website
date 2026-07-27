<?php

$projectRoot = dirname(__DIR__, 2);
$baselinePath = __DIR__.'/protected-php-baseline.json';
$baseline = json_decode((string) file_get_contents($baselinePath), true, flags: JSON_THROW_ON_ERROR);
$protectedRoot = $projectRoot.'/'.$baseline['root'];
$expected = array_keys($baseline['files']);
sort($expected);
$actual = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($protectedRoot));

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $actual[] = str_replace('\\', '/', substr($file->getPathname(), strlen($protectedRoot) + 1));
    }
}

sort($actual);
if ($actual !== $expected) {
    throw new RuntimeException('Protected Factory PHP file set differs from the approved baseline.');
}

foreach ($expected as $relativePath) {
    $definition = $baseline['files'][$relativePath];
    $path = $protectedRoot.'/'.$relativePath;

    if (filesize($path) !== $definition['bytes'] || hash_file('sha256', $path) !== $definition['sha256']) {
        throw new RuntimeException("Protected Factory PHP file differs from baseline: {$relativePath}");
    }
}

echo 'PROTECTED_FACTORY_PHP_PRESERVED='.count($expected).'/'.count($expected).PHP_EOL;
