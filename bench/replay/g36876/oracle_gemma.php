<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #36876, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Improve\Shipping\Carrier\GeneralSettings;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;
use PrestaShop\PrestaShop\Core\Form\ChoiceProvider\GroupByIdChoiceProvider;

/**
 * Dummy Translator to satisfy GeneralSettings dependency with exact Symfony signature
 */
class MockTranslator implements TranslatorInterface
{
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id;
    }
    public function getLocale(): string
    {
        return 'fr';
    }
    public function setLocale(string $locale): void
    {
    }
}

/**
 * Mock FormBuilder to capture field configurations.
 * Signature updated to be compatible with Symfony\Component\Form\FormBuilderInterface.
 */
class MockFormBuilder implements FormBuilderInterface
{
    public $fields = [];

    public function add(FormBuilderInterface|string $child, ?string $type = null, array $options = []): static
    {
        $this->fields[$child] = [
            'type' => $type,
            'options' => $options
        ];
        return $this;
    }

    public function remove(string $name): void {}
    public function getForm(): FormInterface { throw new \Exception("Not implemented"); }
    public function getName(): string { return 'test'; }
    public function getData() { return []; }
    public function setData($data): void {}
    public function getOptions(): array { return []; }
    public function setOptions(array $options): void {}
    public function getParent(): ?FormBuilderInterface { return null; }
    public function setParent(FormBuilderInterface $parent): void {}
    public function getChildren(): array { return []; }
    public function getExtraData(): array { return []; }
    public function setExtraData($data): void {}
}

try {
    // 1. Setup dependencies
    $translator = new MockTranslator();
    $locales = ['fr' => 'fr'];
    
    // GroupByIdChoiceProvider is a final class. 
    // We instantiate it without calling the constructor to avoid dependency issues in CLI.
    $reflection = new ReflectionClass(GroupByIdChoiceProvider::class);
    $choiceProvider = $reflection->newInstanceWithoutConstructor();
    
    $builder = new MockFormBuilder();

    // 2. Instantiate the form type
    $generalSettings = new GeneralSettings($translator, $locales, $choiceProvider);

    // 3. Execute buildForm to populate the builder
    $generalSettings->buildForm($builder, []);

    // 4. Verify the 'logo' field exists
    if (!isset($builder->fields['logo'])) {
        echo "Error: 'logo' field not found in form\n";
        exit(1);
    }

    $logoOptions = $builder->fields['logo']['options'];
    
    if (!isset($logoOptions['constraints'])) {
        echo "Error: No constraints defined for 'logo' field\n";
        exit(1);
    }

    $constraints = $logoOptions['constraints'];
    $fileConstraint = null;

    foreach ($constraints as $constraint) {
        if ($constraint instanceof File) {
            $fileConstraint = $constraint;
            break;
        }
    }

    if (!$fileConstraint) {
        echo "Error: Symfony\Component\Validator\Constraints\File constraint not found for 'logo' field\n";
        exit(1);
    }

    // The fix defines MAX_IMAGE_SIZE_IN_BYTES = 8 * 1000000
    // $maximumFileSize = (int) str_replace('M', '', strval(self::MAX_IMAGE_SIZE_IN_BYTES));
    // Result is 8000000
    $expectedMaxSize = 8000000;
    $observedMaxSize = $fileConstraint->maxSize;

    echo "Observed maxSize for logo: $observedMaxSize\n";
    echo "Expected maxSize for logo: $expectedMaxSize\n";

    if ($observedMaxSize !== $expectedMaxSize) {
        echo "Error: maxSize does not match the 8MB limit\n";
        exit(1);
    }

    // Verify mimeTypes
    if (!isset($fileConstraint->mimeTypes) || !in_array('image/jpeg', $fileConstraint->mimeTypes)) {
        echo "Error: mimeTypes does not include image/jpeg\n";
        exit(1);
    }

    echo "Success: Logo constraints are correctly applied.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Fatal Error: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
