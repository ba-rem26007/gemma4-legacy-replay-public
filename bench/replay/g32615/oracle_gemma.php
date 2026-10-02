<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #32615, validé pre/post automatiquement
namespace PrestaShop\PrestaShop\Core\Image {
    if (!class_exists('PrestaShop\PrestaShop\Core\Image\ImageFormatConfiguration')) {
        class ImageFormatConfiguration {
            public function getImageFormat(): string { return 'webp'; }
        }
    }
}
namespace PrestaShopBundle\Entity\Repository {
    if (!class_exists('PrestaShopBundle\Entity\Repository\FeatureFlagRepository')) {
        class FeatureFlagRepository {
            public function isFeatureActive($flag): bool { return true; }
        }
    }
}
namespace PrestaShop\PrestaShop\Core\Hook {
    if (!interface_exists('PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface')) {
        interface HookDispatcherInterface {
            public function dispatchWithParameters(string $hook, array $params): void;
        }
    }
}
namespace PrestaShop\PrestaShop\Core\Domain\Product\Image\ValueObject {
    if (!class_exists('PrestaShop\PrestaShop\Core\Domain\Product\Image\ValueObject\ImageId')) {
        class ImageId {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function getValue(): int { return (int)$this->id; }
        }
    }
}

namespace {
    require 'config/config.inc.php';

    use PrestaShop\PrestaShop\Adapter\Image\ImageGenerator;
    use PrestaShop\PrestaShop\Adapter\Product\Image\Uploader\ProductImageUploader;
    use PrestaShop\PrestaShop\Adapter\Product\Image\ProductImagePathFactory;
    use PrestaShop\PrestaShop\Adapter\Product\Image\Repository\ProductImageRepository;
    use PrestaShop\PrestaShop\Core\Image\ImageFormatConfiguration;
    use PrestaShopBundle\Entity\Repository\FeatureFlagRepository;
    use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
    use PrestaShop\PrestaShop\Core\Domain\Product\Image\ValueObject\ImageId;

    /**
     * Mocks with strict return types to match the real classes in src/
     */
    class MockFeatureFlagRepository extends FeatureFlagRepository {
        public function isFeatureActive($flag): bool { return true; }
    }

    class MockImageFormatConfiguration extends ImageFormatConfiguration {
        public function getImageFormat(): string { return 'webp'; }
    }

    class MockHookDispatcher implements HookDispatcherInterface {
        public function dispatchWithParameters(string $hook, array $params): void { }
    }

    class MockProductImageRepository extends ProductImageRepository {
        public function getProductImageTypes(): array {
            $type = new ImageType();
            $type->name = 'cart_default';
            $type->width = 100;
            $type->height = 100;
            return [$type];
        }
    }

    class MockProductImagePathFactory extends ProductImagePathFactory {
        public function getPath($imageId): string {
            $id = ($imageId instanceof ImageId) ? $imageId->getValue() : $imageId;
            return _PS_PROD_IMG_DIR_ . 'p/' . $id . '/' . $id . '.jpg';
        }
    }

    // 1. Setup Environment
    Configuration::updateValue('PS_IMAGE_FORMAT_WEBP', 1);
    Configuration::updateValue('PS_PRODUCT_PAGE_V2', 1);

    // 2. Setup Data
    $image = new Image();
    $image->id_product = 1;
    $image->cover = 1;
    $image->position = 1;
    $image->add();
    $imageId = $image->id;

    $imgDir = _PS_PROD_IMG_DIR_ . 'p/' . $imageId . '/';
    if (!is_dir($imgDir)) {
        mkdir($imgDir, 0777, true);
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'test_img');
    $img = imagecreatetruecolor(100, 100);
    ob_start();
    imagejpeg($img);
    file_put_contents($tmpFile, ob_get_clean());
    imagedestroy($img);

    // 3. Instantiate Classes
    $ffRepo = new MockFeatureFlagRepository();
    $imgFormatConfig = new MockImageFormatConfiguration();
    $generator = new ImageGenerator($ffRepo, $imgFormatConfig);

    $pathFactory = new MockProductImagePathFactory();
    $repo = new MockProductImageRepository();
    $dispatcher = new MockHookDispatcher();

    $uploader = new ProductImageUploader(
        $pathFactory,
        1,
        $generator,
        $dispatcher,
        $repo
    );

    // 4. Execute
    try {
        $uploader->upload($image, $tmpFile);
    } catch (\Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }

    // 5. Verification
    // The bug caused the file to be named {path}.jpg-cart_default.webp 
    // because of rtrim($filePath, '.jpg') where $filePath was '.../1.jpg'
    // The fix uses dirname($filePath) . DIRECTORY_SEPARATOR . $imageId
    $expectedFile = _PS_PROD_IMG_DIR_ . 'p/' . $imageId . '/' . $imageId . '-cart_default.webp';
    $wrongFile = _PS_PROD_IMG_DIR_ . 'p/' . $imageId . '/' . $imageId . '.jpg-cart_default.webp';

    $existsExpected = file_exists($expectedFile);
    $existsWrong = file_exists($wrongFile);

    echo "Image ID: $imageId\n";
    echo "Expected file exists: " . ($existsExpected ? 'YES' : 'NO') . "\n";
    echo "Wrong file exists: " . ($existsWrong ? 'YES' : 'NO') . "\n";

    // Cleanup
    if (file_exists($tmpFile)) unlink($tmpFile);
    if (file_exists($expectedFile)) unlink($expectedFile);
    if (file_exists($wrongFile)) unlink($wrongFile);

    if ($existsExpected && !$existsWrong) {
        exit(0);
    } else {
        exit(1);
    }
}
