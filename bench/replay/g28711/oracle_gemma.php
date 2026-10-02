<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #28711, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

// Use /tmp to avoid undefined constant errors
$webpPath = '/tmp/test_image_webservice.webp';
$img = imagecreatetruecolor(10, 10);
imagewebp($img, $webpPath);
imagedestroy($img);

try {
    // The class requires a WebserviceOutputBuilder to avoid "Call to a member function ... on null"
    // WebserviceOutputBuilder requires a WebserviceRequest
    $wsRequest = new WebserviceRequest();
    $wsOutput = new WebserviceOutputBuilder($wsRequest);
    
    $wsImg = new WebserviceSpecificManagementImagesCore();
    $wsImg->setObjectOutput($wsOutput);
    $wsImg->setWsObject($wsRequest);

    $ref = new ReflectionClass($wsImg);

    // 1. Test the accepted mime types array
    $propMime = $ref->getProperty('acceptedImgMimeTypes');
    $propMime->setAccessible(true);
    $acceptedMimes = $propMime->getValue($wsImg);
    $hasWebpMime = in_array('image/webp', $acceptedMimes);

    echo "Mime type image/webp accepted: " . ($hasWebpMime ? 'YES' : 'NO') . "\n";

    // 2. Test getContent() logic
    // We need to set the protected property imgToDisplay
    $propImgToDisplay = $ref->getProperty('imgToDisplay');
    $propImgToDisplay->setAccessible(true);
    $propImgToDisplay->setValue($wsImg, $webpPath);

    // We ensure imgExtension is empty so it's detected via getimagesize()
    $propImgExt = $ref->getProperty('imgExtension');
    $propImgExt->setAccessible(true);
    $propImgExt->setValue($wsImg, '');

    // getContent() will try to find 'webp' in the $types array and call imagecreatefromwebp
    // If the fix is present, it will successfully create the resource and proceed to use $this->objOutput
    $content = $wsImg->getContent();
    $hasContent = !empty($content);

    echo "getContent() returned data for WebP: " . ($hasContent ? 'YES' : 'NO') . "\n";

    // Cleanup
    if (file_exists($webpPath)) {
        unlink($webpPath);
    }

    // The fix is verified if both the mime type is accepted and the content is retrieved
    if ($hasWebpMime && $hasContent) {
        exit(0);
    } else {
        exit(1);
    }

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    if (file_exists($webpPath)) {
        unlink($webpPath);
    }
    exit(1);
}
