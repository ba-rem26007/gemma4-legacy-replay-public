<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #27457, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Order\CreditSlip\CreditSlipOptionsType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Mock of FormBuilder by extending the real Symfony FormBuilder.
 * This avoids implementing the 50+ methods of FormBuilderInterface manually.
 */
class MockFormBuilder extends \Symfony\Component\Form\FormBuilder
{
    public $fields = [];

    /**
     * Override constructor to avoid requiring FormFactoryInterface and other dependencies.
     */
    public function __construct()
    {
        // Bypass parent constructor to avoid dependency injection requirements
    }

    /**
     * Override add to capture the options passed to the field.
     */
    public function add($name, $type = null, array $options = [])
    {
        $this->fields[$name] = $options;
    }
}

/**
 * Mock of TranslatorInterface implementing all required methods.
 */
class MockTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = 'messages', $locale = null)
    {
        return $id;
    }

    public function transChoice($id, $count, array $parameters = [], $domain = 'messages', $locale = null)
    {
        return $id;
    }

    public function getCatalogue($locale)
    {
        return [];
    }

    public function setLocale($locale)
    {
    }

    public function getLocale()
    {
        return 'fr';
    }

    public function setFallbackLocale($locale)
    {
    }

    public function getFallbackLocale()
    {
        return 'fr';
    }
}

try {
    $builder = new MockFormBuilder();
    $formType = new CreditSlipOptionsType();
    
    // TranslatorAwareType requires a translator to call $this->trans()
    if (method_exists($formType, 'setTranslator')) {
        $formType->setTranslator(new MockTranslator());
    }

    // Execute the method that defines the form
    $formType->buildForm($builder, []);

    if (!isset($builder->fields['slip_prefix'])) {
        echo "Champ 'slip_prefix' non trouvé dans le formulaire.\n";
        exit(1);
    }

    $options = $builder->fields['slip_prefix'];
    
    // The fix removes the 'options' key containing 'constraints' => [new NotBlank()]
    $constraints = $options['options']['constraints'] ?? [];
    
    $hasNotBlank = false;
    if (is_array($constraints)) {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof NotBlank) {
                $hasNotBlank = true;
                break;
            }
        }
    }

    if ($hasNotBlank) {
        echo "ÉCHEC : La contrainte NotBlank est toujours présente sur 'slip_prefix'.\n";
        exit(1);
    } else {
        echo "SUCCÈS : La contrainte NotBlank a été supprimée de 'slip_prefix'.\n";
        exit(0);
    }

} catch (\Throwable $t) {
    echo "Erreur lors de l'exécution du test : " . $t->getMessage() . "\n";
    exit(1);
}
