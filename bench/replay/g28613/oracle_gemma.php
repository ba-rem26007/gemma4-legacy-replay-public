<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28613, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Import\File\FileRemoval;
use PrestaShop\PrestaShop\Core\Import\ImportDirectory;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

/**
 * Mock implementation of ConfigurationInterface to satisfy ImportDirectory.
 */
class MockImportConfig implements ConfigurationInterface
{
    private $values = [];

    public function __construct($path)
    {
        $this->values['import_directory'] = $path;
    }

    public function get($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }
}

// Setup Context
$context = Context::getContext();
$context->shop = new Shop(1);
$context->language = new Language(1);

try {
    // Create a temporary directory for the test
    // Ensure it ends with a slash as ImportDirectory is concatenated directly
    $testBaseDir = '/tmp/ps_import_test_' . uniqid() . '/';
    $testCsvDir = $testBaseDir . 'csvfromexcel/';
    
    if (!mkdir($testBaseDir, 0777, true)) {
        throw new \Exception("Could not create base directory: $testBaseDir");
    }
    if (!mkdir($testCsvDir, 0777, true)) {
        throw new \Exception("Could not create csvfromexcel directory: $testCsvDir");
    }

    $filename = 'test_import_excel.xls';
    $mainFilePath = $testBaseDir . $filename;
    $csvFilePath = $testCsvDir . $filename;

    // Create the dummy files that would exist after an Excel import
    file_put_contents($mainFilePath, 'dummy excel content');
    file_put_contents($csvFilePath, 'dummy csv content');

    echo "Files created:\n";
    echo "Main file: $mainFilePath\n";
    echo "CSV file: $csvFilePath\n";

    // Instantiate ImportDirectory using our Mock configuration
    $config = new MockImportConfig($testBaseDir);
    $importDirectory = new ImportDirectory($config);
    
    // Instantiate FileRemoval
    $fileRemoval = new FileRemoval($importDirectory);

    // Execute the removal
    echo "Calling FileRemoval::remove('$filename')...\n";
    $fileRemoval->remove($filename);

    // Check results
    $mainFileExists = file_exists($mainFilePath);
    $csvFileExists = file_exists($csvFilePath);

    echo "Result - Main file exists: " . ($mainFileExists ? 'YES' : 'NO') . "\n";
    echo "Result - CSV file exists: " . ($csvFileExists ? 'YES' : 'NO') . "\n";

    // Cleanup
    @unlink($mainFilePath);
    @unlink($csvFilePath);
    @rmdir($testCsvDir);
    @rmdir($testBaseDir);

    // The test fails if the CSV file still exists (bug)
    if ($csvFileExists) {
        echo "FAILURE: The converted CSV file was not deleted.\n";
        exit(1);
    }

    if ($mainFileExists) {
        echo "FAILURE: The main file was not deleted.\n";
        exit(1);
    }

    echo "SUCCESS: Both files were deleted.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    exit(1);
}
