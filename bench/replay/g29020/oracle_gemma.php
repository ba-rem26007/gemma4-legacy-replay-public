<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29020, validé pre/post automatiquement
require 'config/config.inc.php';

use PrestaShop\PrestaShop\Adapter\Translations\TranslationRouteFinder;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Define missing interfaces and exceptions in their correct namespaces 
 * to satisfy type-hints in the TranslationRouteFinder constructor.
 */
namespace PrestaShop\PrestaShop\Core\Module {
    if (!interface_exists('PrestaShop\PrestaShop\Core\Module\ModuleRepositoryInterface')) {
        interface ModuleRepositoryInterface {
            public function getModule($moduleName);
            public function getModules();
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Module\Exception {
    if (!class_exists('PrestaShop\PrestaShop\Core\Module\Exception\InvalidModuleException')) {
        class InvalidModuleException extends \Exception {}
    }
}

namespace PrestaShop\PrestaShop\Core\Translations {
    if (!class_exists('PrestaShop\PrestaShop\Core\Translations\TranslationService')) {
        class TranslationService {
            public function langToLocale($language) { return 'fr-FR'; }
        }
    }
}

namespace {
    /**
     * Module is an abstract class. We need a concrete implementation.
     */
    class TestModule extends Module
    {
        public function __construct()
        {
            parent::__construct('test_module');
            $this->active = 1;
            $this->version = '1.0.0';
        }

        public function isUsingNewTranslationSystem()
        {
            return true;
        }
    }

    /**
     * The bug is triggered when the repository returns a wrapper object 
     * that is NOT an instance of Module, but has a getInstance() method 
     * returning a Module instance.
     */
    class ModuleWrapper
    {
        private $module;
        public function __construct($module) { $this->module = $module; }
        public function getInstance() { return $this->module; }
    }

    class MockModuleRepository implements \PrestaShop\PrestaShop\Core\Module\ModuleRepositoryInterface
    {
        public function getModule($moduleName)
        {
            return new ModuleWrapper(new TestModule());
        }

        public function getModules()
        {
            return [];
        }
    }

    class MockTranslationService extends \PrestaShop\PrestaShop\Core\Translations\TranslationService
    {
        public function langToLocale($language)
        {
            return 'fr-FR';
        }
    }

    // 1. Setup: Ensure the module exists in the database for the Module constructor
    Db::getInstance()->insert('module', [
        'name' => 'test_module',
        'active' => 1,
        'version' => '1.0.0',
    ]);

    // 2. Instantiate the target class
    $translationService = new MockTranslationService();
    $link = new Link();
    $moduleRepository = new MockModuleRepository();

    $finder = new TranslationRouteFinder(
        $translationService,
        $link,
        $moduleRepository
    );

    // 3. Prepare the query that triggers the bug
    // 'modules' is the value of TranslationRouteFinder::MODULES
    $query = new ParameterBag([
        'form' => [
            'translation_type' => 'modules',
            'module' => 'test_module',
            'language' => 1,
        ]
    ]);

    try {
        echo "Testing findRoute with module translation...\n";
        $route = $finder->findRoute($query);
        echo "Route found: $route\n";
        
        // If we reach here, the fix is working (it didn't throw InvalidModuleException)
        exit(0);
    } catch (\PrestaShop\PrestaShop\Core\Module\Exception\InvalidModuleException $e) {
        echo "Caught expected bug: InvalidModuleException - " . $e->getMessage() . "\n";
        exit(1);
    } catch (\Throwable $t) {
        echo "Caught unexpected error: " . get_class($t) . " - " . $t->getMessage() . "\n";
        exit(1);
    }
}
