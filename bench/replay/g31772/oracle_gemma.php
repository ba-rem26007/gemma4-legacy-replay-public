<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #31772, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Core\Domain\Webservice\ValueObject\Key;
use PrestaShop\PrestaShop\Core\Domain\Webservice\Exception\WebserviceConstraintException;
use PrestaShopBundle\Controller\Admin\Configure\AdvancedParameters\WebserviceController;
use PrestaShopBundle\Form\Admin\Configure\AdvancedParameters\Webservice\WebserviceKeyType;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormBuilder;

/**
 * Mock Translator to return the translation key with parameters replaced.
 * Signature matches Symfony\Contracts\Translation\TranslatorInterface.
 */
class MockTranslator implements TranslatorInterface
{
    public function trans($id, $parameters = [], $domain = null, $locale = null)
    {
        $text = $id;
        if (is_array($parameters)) {
            foreach ($parameters as $key => $value) {
                $text = str_replace($key, $value, $text);
            }
        }
        return $text;
    }

    public function getLocale() { return 'fr'; }
    public function setLocale($locale) {}
    public function setMessages($messages, $locale = null) {}
}

/**
 * CapturingBuilder extends the real Symfony FormBuilder to intercept the options
 * passed to the add() method without having to implement the entire interface.
 */
class CapturingBuilder extends FormBuilder
{
    public $capturedFields = [];

    public function add($name, $type = null, array $options = [])
    {
        $this->capturedFields[$name] = $options;
        return parent::add($name, $type, $options);
    }
}

/**
 * Mock Controller to override the trans() method which normally depends on the Symfony container.
 */
class TestWebserviceController extends WebserviceController
{
    public function trans($id, $parameters = [], $domain = null)
    {
        $text = $id;
        if (is_array($parameters)) {
            foreach ($parameters as $key => $value) {
                $text = str_replace($key, $value, $text);
            }
        }
        return $text;
    }
}

try {
    // 1. Test WebserviceKeyType (Help text and Exact Message)
    $translator = new MockTranslator();
    $keyType = new WebserviceKeyType($translator, ['fr'], false, [], []);
    $builder = new CapturingBuilder();
    $keyType->buildForm($builder, []);

    if (!isset($builder->capturedFields['key'])) {
        echo "FAIL: Field 'key' was not added to the form.\n";
        exit(1);
    }

    $keyOptions = $builder->capturedFields['key'];
    $helpText = $keyOptions['help'];
    echo "Observed help text: $helpText\n";

    // The bug is the mention of "at least"
    if (strpos($helpText, 'at least') !== false) {
        echo "FAIL: Help text still mentions 'at least'.\n";
        exit(1);
    }
    if (strpos($helpText, 'must be 32 characters long') === false) {
        echo "FAIL: Help text does not explicitly state it must be 32 characters.\n";
        exit(1);
    }

    // Check the exactMessage in constraints
    $lengthConstraint = null;
    foreach ($keyOptions['constraints'] as $constraint) {
        if ($constraint instanceof \Symfony\Component\Validator\Constraints\Length) {
            $lengthConstraint = $constraint;
            break;
        }
    }

    if (!$lengthConstraint) {
        echo "FAIL: Length constraint not found.\n";
        exit(1);
    }

    $exactMessage = $lengthConstraint->exactMessage;
    echo "Observed exact message: $exactMessage\n";

    // Old: 'Key length must be 32 character long.' (singular)
    // New: 'Key length must be 32 characters long.' (plural)
    if (strpos($exactMessage, '32 character long') !== false && strpos($exactMessage, '32 characters long') === false) {
        echo "FAIL: Exact message still uses singular 'character'.\n";
        exit(1);
    }

    // 2. Test WebserviceController (Error messages)
    $controller = new TestWebserviceController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getErrorMessages');
    $method->setAccessible(true);
    $errors = $method->invoke($controller);

    if (!isset($errors[WebserviceConstraintException::class][WebserviceConstraintException::INVALID_KEY])) {
        echo "FAIL: Expected error message for INVALID_KEY not found.\n";
        exit(1);
    }

    $invalidKeyError = $errors[WebserviceConstraintException::class][WebserviceConstraintException::INVALID_KEY];
    echo "Observed controller error: $invalidKeyError\n";

    if (strpos($invalidKeyError, '32 character long') !== false && strpos($invalidKeyError, '32 characters long') === false) {
        echo "FAIL: Controller error message still uses singular 'character'.\n";
        exit(1);
    }

    echo "SUCCESS: All text helpers and error messages are corrected.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
    exit(1);
}
