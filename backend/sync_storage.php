<?php
/**
 * Sync storage files to public directory for shared hosting
 * Run this after uploading images: php sync_storage.php
 */

$source = __DIR__ . '/storage/app/public/products';
$destination = __DIR__ . '/public/storage/products';

// Create destination directory if it doesn't exist
if (!file_exists($destination)) {
    mkdir($destination, 0755, true);
}

// Copy files from source to destination
$files = scandir($source);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }

    $sourceFile = $source . '/' . $file;
    $destFile = $destination . '/' . $file;

    // Only copy if source is newer or destination doesn't exist
    if (!file_exists($destFile) || filemtime($sourceFile) > filemtime($destFile)) {
        copy($sourceFile, $destFile);
        echo "Copied: $file\n";
    }
}

echo "Storage sync completed!\n";
echo "Total files: " . count(scandir($destination)) - 2 . "\n";