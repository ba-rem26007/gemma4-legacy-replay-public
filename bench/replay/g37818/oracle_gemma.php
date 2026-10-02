<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #37818, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Improve\Shipping\Carrier\GeneralSettings;
use PrestaShop\PrestaShop\Core\Form\ChoiceProvider\GroupByIdChoiceProvider;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Dummy Translator to satisfy GeneralSettings dependency
 */
class DummyTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return $id;
    }
    public function getLocale(): string
    {
        return 'fr';
    }
    public function setLocale($locale): void
    {
    }
    public function fallback(): void
    {
    }
}

/**
 * CapturingBuilder allows us to inspect the constraints added to the form fields.
 * The signatures must exactly match Symfony\Component\Form\FormBuilderInterface.
 */
class CapturingBuilder implements FormBuilderInterface
{
    public $fields = [];

    public function add($child, ?string $type = null, array $options = []): static
    {
        if (is_string($child)) {
            $this->fields[$child] = $options;
        }
        return $this;
    }

    public function remove(string $name): static
    {
        return $this;
    }

    public function getForm() { return null; }
    public function setData($data): void {}
    public function setDataListener(FormEvents $event): void {}
    public function addEventListener(FormEvents $event, callable $listener): void {}
    public function getOptions(): array { return []; }
    public function setOptions(array $options): void {}
}

try {
    // 1. Setup dependencies
    $translator = new DummyTranslator();
    $locales = ['fr'];

    // GroupByIdChoiceProvider is a final class. 
    // We use Reflection to instantiate it without calling the constructor to bypass dependencies.
    $groupProviderReflection = new ReflectionClass(GroupByIdChoiceProvider::class);
    $groupProvider = $groupProviderReflection->newInstanceWithoutConstructor();

    // 2. Instantiate the form type touched by the fix
    $formType = new GeneralSettings($translator, $locales, $groupProvider);

    // 3. Build the form using our capturing builder
    $builder = new CapturingBuilder();
    $formType->buildForm($builder, []);

    // 4. Extract constraints for the 'tracking_url' field
    if (!isset($builder->fields['tracking_url'])) {
        echo "Field 'tracking_url' not found in form.\n";
        exit(1);
    }

    $options = $builder->fields['tracking_url'];
    $constraints = $options['constraints'] ?? [];

    echo "Constraints found for tracking_url: " . count($constraints) . "\n";

    // 5. Validate the problematic input '@' against the extracted constraints
    // The fix adds a Symfony\Component\Validator\Constraints\Url constraint.
    $validator = Validation::createValidator();
    $valueToTest = '@';
    $violations = $validator->validate($valueToTest, $constraints);

    $violationCount = count($violations);
    echo "Number of violations for value '@': $violationCount\n";

    if ($violationCount > 0) {
        foreach ($violations as $violation) {
            echo "Violation: " . $violation->getMessage() . "\n";
        }
        // If there are violations, the Url constraint is active and prevents the exception
        exit(0);
    } else {
        echo "Bug: Value '@' passed validation. This will lead to an exception during save.\n";
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Fatal error during test: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
