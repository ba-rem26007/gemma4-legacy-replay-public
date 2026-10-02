<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29590, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);
$employee = new Employee(1);
if (!Validate::isLoadedObject($employee)) {
    $employee = new Employee();
    $employee->firstname = 'Test';
    $employee->lastname = 'Test';
    $employee->email = 'test@test.com';
    $employee->passwd = '123456';
    $employee->id_profile = 1;
    $employee->add();
}
Context::getContext()->employee = $employee;

use PrestaShopBundle\Controller\Admin\Improve\International\GeolocationController;

try {
    // The bug is located in the @AdminSecurity annotation (docblock) of the controller methods.
    // Since the Symfony container is not available in CLI, we cannot trigger the actual 
    // routing exception caused by the incorrect 'redirectRoute'.
    // We must verify the fix by inspecting the metadata (annotations) via Reflection.
    
    $controller = new GeolocationController();
    $reflector = new ReflectionClass($controller);
    $methods = $reflector->getMethods();
    
    $foundOldRoute = false;
    $foundNewRoute = false;
    $checkedMethods = [
        'processByIpAddressFormAction',
        'processWhitelistFormAction',
        'processOptionsFormAction'
    ];

    foreach ($methods as $method) {
        if (in_array($method->getName(), $checkedMethods)) {
            $docComment = $method->getDocComment();
            
            if (strpos($docComment, 'redirectRoute="admin_geolocation"') !== false) {
                echo "Bug found in method {$method->getName()}: redirectRoute is 'admin_geolocation'\n";
                $foundOldRoute = true;
            }
            if (strpos($docComment, 'redirectRoute="admin_geolocation_index"') !== false) {
                echo "Fix found in method {$method->getName()}: redirectRoute is 'admin_geolocation_index'\n";
                $foundNewRoute = true;
            }
        }
    }

    if ($foundOldRoute) {
        echo "Result: FAIL - The incorrect route 'admin_geolocation' is still present.\n";
        exit(1);
    }

    if ($foundNewRoute) {
        echo "Result: PASS - The route has been corrected to 'admin_geolocation_index'.\n";
        exit(0);
    }

    echo "Result: FAIL - No relevant redirectRoute found in annotations.\n";
    exit(1);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
