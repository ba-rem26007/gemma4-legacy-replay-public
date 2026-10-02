<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34351, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context for the main process
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Admin';
    $employee->lastname = 'Admin';
    $employee->email = 'admin@example.com';
    $employee->passwd = 'password';
    $employee->add();
}

Context::getContext()->employee = $employee;
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

// Ensure product 1 exists
$product = new Product(1);
if (!Validate::isLoadedObject($product)) {
    $product = new Product();
    $product->price = 10.0;
    $product->id_category_default = 2;
    $product->name = [1 => 'Test Product'];
    $product->link_rewrite = [1 => 'test-product'];
    $product->add();
}

// Clean up previous test images for product 1 to avoid false positives
Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'image WHERE id_product = 1');

// Create a valid image file
$tmpFile = tempnam(sys_get_temp_dir(), 'ps_img');
$img = imagecreatetruecolor(200, 200);
imagejpeg($img, $tmpFile);
imagedestroy($img);
chmod($tmpFile, 0644);

// The controller method ajaxProcessaddProductImage calls die(json_encode(...)).
// To verify the results (thumbnails) after the call, we must execute the call
// in a separate process.
$runnerFile = tempnam(sys_get_temp_dir(), 'ps_runner');
$configPath = __DIR__ . '/config/config.inc.php';

$runnerCode = '<?php
require ' . var_export($configPath, true) . ';

$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = "Admin";
    $employee->lastname = "Admin";
    $employee->email = "admin@example.com";
    $employee->passwd = "password";
    $employee->add();
}

Context::getContext()->employee = $employee;
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
Context::getContext()->currency = new Currency(1);

$_FILES["file"] = [
    "name" => "test_image.jpg",
    "type" => "image/jpeg",
    "tmp_name" => ' . var_export($tmpFile, true) . ',
    "error" => 0,
    "size" => ' . filesize($tmpFile) . ',
];
$_POST["legend"] = ["1" => "Test Legend"];

try {
    $controller = new AdminProductsController();
    $controller->ajaxProcessaddProductImage(1, "file");
} catch (\Throwable $t) {
    echo "FATAL: " . $t->getMessage();
    exit(1);
}
';
file_put_contents($runnerFile, $runnerCode);

try {
    // Execute the controller call in a separate process to handle the die()
    $output = shell_exec('php ' . escapeshellarg($runnerFile));
    echo "Controller output: $output\n";

    // Check if the image was created in the database
    $idImage = (int) Db::getInstance()->getValue('SELECT MAX(id_image) FROM ' . _DB_PREFIX_ . 'image WHERE id_product = 1');
    if ($idImage <= 0) {
        echo "Error: No image was created in the database. Uploader might have failed.\n";
        exit(1);
    }

    // Determine the path to the image (PrestaShop standard: img/p/1/2/3/123.jpg)
    $idStr = (string)$idImage;
    $path = 'img/p/';
    for ($i = 0; $i < strlen($idStr) - 1; $i++) {
        $path .= $idStr[$i] . '/';
    }
    $path .= $idStr;

    echo "Original image path: $path.jpg\n";
    if (!file_exists($path . '.jpg')) {
        echo "Original image file not found on disk.\n";
        exit(1);
    }

    // Check for thumbnails.
    // If the bug is present, the script crashes/fails before generating thumbnails.
    // If fixed, thumbnails (files with suffixes like -small_default.jpg) are created.
    $files = glob($path . '-*.jpg');
    $thumbnailCount = count($files);
    echo "Thumbnails found: $thumbnailCount\n";

    if ($thumbnailCount > 0) {
        exit(0); // Fixed
    } else {
        echo "No thumbnails were generated. Bug is still present.\n";
        exit(1); // Bug present
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    exit(1);
} finally {
    if (file_exists($tmpFile)) unlink($tmpFile);
    if (file_exists($runnerFile)) unlink($runnerFile);
}
