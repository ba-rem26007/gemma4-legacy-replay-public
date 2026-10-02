<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #38203, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShopBundle\Form\Admin\Sell\Product\Stock\StockOptionsType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mock of TranslatorInterface
 */
class MockTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        $res = $id;
        if (!empty($parameters)) {
            foreach ($parameters as $k => $v) {
                $res = str_replace($k, $v, $res);
            }
        }
        return $res;
    }
    public function getLocale(): string { return 'fr'; }
    public function setLocale(string $locale): void {}
    public function setFallbackLocale(string $locale): void {}
}

/**
 * Mock of RouterInterface and RequestContextAwareInterface
 */
class MockRouter implements RouterInterface
{
    public function generate($name, array $parameters = [], $referenceType = 0): string
    {
        return 'http://localhost/admin/employees';
    }
    public function match($path): array { return []; }
    public function getRouteCollection(): RouteCollection { return new RouteCollection(); }
    public function setContext(RequestContext $context): void {}
    public function getContext(): RequestContext { return new RequestContext(); }
}

/**
 * Mock of FormBuilderInterface
 * Strictly compatible with Symfony FormBuilderInterface signatures
 */
class MockFormBuilder implements FormBuilderInterface
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
        unset($this->fields[$name]);
        return $this;
    }

    public function getForm(): FormInterface
    {
        return new class implements FormInterface {
            public function get(string $name) { return null; }
            public function remove(string $name): void {}
            public function getErrors() { return new \ArrayIterator([]); }
            public function isValid(): bool { return true; }
            public function isSubmitted(): bool { return true; }
            public function getName(): string { return ''; }
            public function getExtraData() { return []; }
            public function configureOptions(array $options): void {}
            public function setExtraData($data): void {}
            public function getConfiguration() { return []; }
            public function getParent() { return null; }
            public function getChildren() { return []; }
            public function getRoot() { return $this; }
        };
    }

    public function getOption($name) { return null; }
    public function setOption($name, $value): void {}
    public function getOptions(): array { return []; }
}

try {
    $translator = new MockTranslator();
    $router = new MockRouter();
    $locales = [1 => 'fr'];

    $ref = new ReflectionClass(StockOptionsType::class);
    $constructor = $ref->getConstructor();
    $numParams = $constructor ? count($constructor->getParameters()) : 0;

    if ($numParams === 3) {
        // Old version: expects Translator, Locales, Router
        $formType = new StockOptionsType($translator, $locales, $router);
    } else {
        // New version: expects Translator, Locales
        $formType = new StockOptionsType($translator, $locales);
    }

    $builder = new MockFormBuilder();
    $formType->buildForm($builder, []);

    $options = $builder->fields['low_stock_threshold'] ?? [];
    $helpBox = $options['label_help_box'] ?? '';

    echo "Observed label_help_box: " . $helpBox . "\n";

    // The bug is the presence of HTML tags (<a>) or placeholders ([1]) in the help box
    if (strpos($helpBox, '<a') !== false || strpos($helpBox, '[1]') !== false) {
        echo "FAIL: Help box still contains HTML or placeholders.\n";
        exit(1);
    }

    echo "SUCCESS: Help box is now plain text.\n";
    exit(0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
