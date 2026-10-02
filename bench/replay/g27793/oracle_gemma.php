<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27793, validé pre/post automatiquement
require 'config/config.inc.php';

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Range;

/**
 * The bug is that a negative value for "Maximum number of payment days" (max_payment_days)
 * causes a database exception because the column is UNSIGNED INT.
 * The fix adds a Range constraint in the Symfony form (CustomerType) and a constant in Validate.
 */

try {
    // 1. Verify the new constant exists (This will fail before the fix)
    if (!defined('Validate::MYSQL_UNSIGNED_INT_MAX')) {
        // In some PHP versions, accessing a non-existent class constant throws an Error
        // We handle this in the catch block, but we can also check explicitly.
    }
    $max = Validate::MYSQL_UNSIGNED_INT_MAX;
    echo "Constant MYSQL_UNSIGNED_INT_MAX: $max\n";

    // 2. Verify Validate::isUnsignedInt logic
    // It should return false for negative numbers and numbers exceeding 32-bit unsigned int
    $val_neg = Validate::isUnsignedInt(-1);
    $val_zero = Validate::isUnsignedInt(0);
    $val_max = Validate::isUnsignedInt($max);
    $val_over = Validate::isUnsignedInt($max + 1);

    echo "isUnsignedInt(-1): " . ($val_neg ? 'true' : 'false') . "\n";
    echo "isUnsignedInt(0): " . ($val_zero ? 'true' : 'false') . "\n";
    echo "isUnsignedInt($max): " . ($val_max ? 'true' : 'false') . "\n";
    echo "isUnsignedInt(" . ($max + 1) . "): " . ($val_over ? 'true' : 'false') . "\n";

    // 3. Verify the Range constraint logic added to CustomerType
    // Since we can't easily boot the full Symfony Form in CLI without the container,
    // we test the constraint itself with the same parameters used in the fix.
    $validator = Validation::createValidator();
    $constraint = new Range([
        'min' => 0,
        'max' => $max,
    ]);

    $violations_neg = $validator->validate(-1, $constraint);
    $violations_max = $validator->validate($max, $constraint);
    $violations_over = $validator->validate($max + 1, $constraint);

    $has_violations_neg = count($violations_neg) > 0;
    $has_violations_max = count($violations_max) > 0;
    $has_violations_over = count($violations_over) > 0;

    echo "Range constraint rejects -1: " . ($has_violations_neg ? 'yes' : 'no') . "\n";
    echo "Range constraint accepts MAX: " . (!$has_violations_max ? 'yes' : 'no') . "\n";
    echo "Range constraint rejects MAX+1: " . ($has_violations_over ? 'yes' : 'no') . "\n";

    // Final validation:
    // - Constant must be 4294967295
    // - isUnsignedInt must correctly identify boundaries
    // - Range constraint must catch negative and over-max values
    if (
        $max === 4294967295 &&
        !$val_neg && $val_zero && $val_max && !$val_over &&
        $has_violations_neg && !$has_violations_max && $has_violations_over
    ) {
        exit(0);
    } else {
        echo "Validation failed: one or more conditions not met.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Exception caught: " . $t->getMessage() . "\n";
    // If Validate::MYSQL_UNSIGNED_INT_MAX is not defined, it throws an Error/Exception
    exit(1);
}
