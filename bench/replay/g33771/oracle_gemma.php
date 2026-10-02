<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #33771, validé pre/post automatiquement
require 'config/config.inc.php';

/**
 * The bug is that Validate::OBJECT_CLASS_NAME_REGEXP does not allow backslashes,
 * which prevents ObjectModels using namespaces from being validated correctly
 * (e.g. when logged by PrestaShopLogger).
 */

$namespacedClass = 'MyModule\MyObjectModel';
$simpleClass = 'Product';

echo "Testing simple class '$simpleClass': " . (preg_match(Validate::OBJECT_CLASS_NAME_REGEXP, $simpleClass) ? 'VALID' : 'INVALID') . "\n";
echo "Testing namespaced class '$namespacedClass': " . (preg_match(Validate::OBJECT_CLASS_NAME_REGEXP, $namespacedClass) ? 'VALID' : 'INVALID') . "\n";

$isValid = (bool)preg_match(Validate::OBJECT_CLASS_NAME_REGEXP, $namespacedClass);

if ($isValid) {
    echo "Result: Namespaced class is accepted. Correctif is working.\n";
    exit(0);
} else {
    echo "Result: Namespaced class is rejected. Bug is still present.\n";
    exit(1);
}
