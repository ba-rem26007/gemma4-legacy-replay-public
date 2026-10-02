<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #34419, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Product\Pricing\UnitPriceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mock of TranslatorInterface to satisfy UnitPriceType constructor.
 */
$translator = new class implements TranslatorInterface {
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string {
        return (string)$id;
    }
    public function getLocale(): string {
        return 'fr';
    }
    public function setLocale(string $locale): void {
    }
};

/**
 * Mock of FormBuilderInterface to capture the constraints added to the form.
 * Must implement IteratorAggregate to satisfy Traversable requirement.
 */
$builder = new class implements FormBuilderInterface, \IteratorAggregate {
    public $fields = [];

    public function add($child, $type = null, array $options = []) {
        $this->fields[$child] = $options;
    }

    public function remove($name) {}
    public function get($name) {}
    public function set($name, $value) {}
    public function getOptions() { return []; }
    public function setOptions(array $options) {}
    public function getData() {}
    public function setData($data) {}
    public function configureOptions(callable $resolver) {}
    
    public function getIterator(): \Traversable {
        return new \ArrayIterator([]);
    }
};

try {
    // Instantiate the form type directly
    $currency = new Currency(1);
    $unitPriceType = new UnitPriceType($translator, ['fr'], $currency);

    // Build the form to trigger the constraints definition
    $unitPriceType->buildForm($builder, []);

    $constraints = $builder->fields['price_tax_excluded']['constraints'] ?? [];
    
    $hasPositive = false;
    $hasPositiveOrZero = false;

    foreach ($constraints as $constraint) {
        if ($constraint instanceof Positive) {
            $hasPositive = true;
        }
        if ($constraint instanceof PositiveOrZero) {
            $hasPositiveOrZero = true;
        }
    }

    echo "Constraints found for price_tax_excluded:\n";
    echo "Positive: " . ($hasPositive ? 'YES' : 'NO') . "\n";
    echo "PositiveOrZero: " . ($hasPositiveOrZero ? 'YES' : 'NO') . "\n";

    // The bug is the presence of the 'Positive' constraint which forbids 0.
    // The fix is to remove 'Positive' and keep 'PositiveOrZero'.
    if ($hasPositive) {
        echo "Bug present: Positive constraint is still enforced.\n";
        exit(1);
    }

    if (!$hasPositiveOrZero) {
        echo "Error: PositiveOrZero constraint is missing.\n";
        exit(1);
    }

    echo "Success: Positive constraint removed, PositiveOrZero remains.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Exception: " . $t->getMessage() . "\n";
    exit(1);
}
