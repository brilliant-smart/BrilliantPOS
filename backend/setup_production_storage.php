<?php
/**
 * Setup production storage for shared hosting
 * Run this after deployment: php setup_production_storage.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Setting up production storage...\n";

// Create public/storage directory if it doesn't exist
$publicStorage = public_path('storage');
if (!file_exists($publicStorage)) {
    mkdir($publicStorage, 0755, true);
    echo "Created public/storage directory\n";
}

// Create products subdirectory
$productsDir = $publicStorage . '/products';
if (!file_exists($productsDir)) {
    mkdir($productsDir, 0755, true);
    echo "Created public/storage/products directory\n";
}

// Create .htaccess to protect the directory
$htaccessContent = "Options -Indexes\n";
$htaccessFile = $publicStorage . '/.htaccess';
if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, $htaccessContent);
    echo "Created .htaccess file\n";
}

// Set proper permissions
chmod($publicStorage, 0755);
chmod($productsDir, 0755);

echo "Production storage setup completed!\n";
echo "Storage directory: " . $publicStorage . "\n";
echo "Products directory: " . $productsDir . "\n";

// Check if directory is writable
if (is_writable($productsDir)) {
    echo "✓ Products directory is writable\n";
} else {
    echo "✗ Products directory is NOT writable - check permissions\n";
}

echo "\nYou can now upload images and they will be stored directly in public/storage/products/\n";