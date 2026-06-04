<?php
$dir = 'C:/xampp/htdocs/sectorix-wholesale';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'jsx', 'ts', 'tsx', 'json'])) {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, 'Resolve billing or license mismatch') !== false) {
            echo $file->getPathname() . PHP_EOL;
        }
    }
}
