<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28660, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\EmployeeFormDataHandler;
use PrestaShop\PrestaShop\Core\Hashing;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// Use eval to define interfaces in their correct namespaces if the autoloader fails in CLI.
// This avoids "Namespace declaration must be first" and "Interface not found" errors.
if (!interface_exists('PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface')) {
    eval('namespace PrestaShop\PrestaShop\Core\CommandBus { interface CommandBusInterface { public function handle($command); } }');
}
if (!interface_exists('PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\EmployeeFormAccessCheckerInterface')) {
    eval('namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler { interface EmployeeFormAccessCheckerInterface { public function checkAccess($id); } }');
}
if (!interface_exists('PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\EmployeeDataProviderInterface')) {
    eval('namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler { interface EmployeeDataProviderInterface { public function getData($id); } }');
}
if (!interface_exists('PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\ImageUploaderInterface')) {
    eval('namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler { interface ImageUploaderInterface { public function upload($id, $file); } }');
}

// 1. Setup: Ensure Employee 1 exists
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee->firstname = 'Test';
    $employee->lastname = 'User';
    $employee->email = 'test@test.com';
    $employee->passwd = Tools::encrypt('123456');
    $employee->id_profile = 1;
    $employee->active = 1;
    $employee->add();
}

// 2. Mocks for dependencies
$bus = new class implements \PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface {
    public function handle($command) {
        // Simulate a validation error occurring during the command handling (e.g. invalid last name)
        throw new \RuntimeException("Validation failed: Last name is invalid");
    }
};

$checker = new class implements \PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\EmployeeFormAccessCheckerInterface {
    public function checkAccess($id) { return true; }
};

$provider = new class implements \PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\EmployeeDataProviderInterface {
    public function getData($id) { return []; }
};

$uploader = new class implements \PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler\ImageUploaderInterface {
    public $uploaded = false;
    public function upload($id, $file) {
        $this->uploaded = true;
    }
};

$hashing = new Hashing();

// 3. Prepare data with an "uploaded" file
$tmpFile = tempnam(sys_get_temp_dir(), 'avatar');
file_put_contents($tmpFile, 'fake image content');
$uploadedFile = new UploadedFile($tmpFile, 'avatar.jpg', 'image/jpeg', null, true);

$data = [
    'firstname' => 'John',
    'lastname' => 'Invalid!', 
    'email' => 'test@test.com',
    'default_page' => 1,
    'language' => 1,
    'active' => 1,
    'profile' => 1,
    'avatarUrl' => $uploadedFile,
];

// 4. Instantiate the handler
$handler = new EmployeeFormDataHandler(
    $bus,
    [], // defaultShopAssociation
    1,  // superAdminProfileId
    $checker,
    $provider,
    $hashing,
    $uploader,
    3,   // minLength
    255, // maxLength
    1    // minScore
);

// 5. Execute and test
try {
    $handler->update(1, $data);
} catch (\Throwable $t) {
    echo "Caught expected exception: " . $t->getMessage() . "\n";
}

echo "Avatar uploaded: " . ($uploader->uploaded ? 'YES' : 'NO') . "\n";

/**
 * BUG: The avatar is uploaded BEFORE the command bus handles the update.
 * If the command bus throws a validation exception, the file has already been moved.
 * 
 * FIXED: The avatar should be uploaded AFTER the command bus handles the update.
 * If the command bus throws an exception, the uploader should NOT have been called.
 */
if ($uploader->uploaded) {
    echo "FAIL: Avatar was uploaded before validation error.\n";
    exit(1);
} else {
    echo "SUCCESS: Avatar was not uploaded because validation failed.\n";
    exit(0);
}
