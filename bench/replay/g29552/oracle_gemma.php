<?php
// Oracle écrit par Gemma (gemma-4-31b-it) pour PR #29552, validé pre/post automatiquement
require 'config/config.inc.php';

// Setup Context
Context::getContext()->shop = new Shop(1);
Context::getContext()->language = new Language(1);

try {
    // 1. Create a hierarchy of CMS categories
    // Root Category (Active)
    $catRoot = new CMSCategory();
    $catRoot->id_parent = 0;
    $catRoot->active = 1;
    $catRoot->name = [1 => 'Root Category'];
    $catRoot->link_rewrite = [1 => 'root-category'];
    $catRoot->add();

    // Hidden Category (Inactive) - This is the one that should NOT appear in breadcrumb
    $catHidden = new CMSCategory();
    $catHidden->id_parent = $catRoot->id;
    $catHidden->active = 0;
    $catHidden->name = [1 => 'Hidden Category'];
    $catHidden->link_rewrite = [1 => 'hidden-category'];
    $catHidden->add();

    // Child Category (Active)
    $catChild = new CMSCategory();
    $catChild->id_parent = $catHidden->id;
    $catChild->active = 1;
    $catChild->name = [1 => 'Child Category'];
    $catChild->link_rewrite = [1 => 'child-category'];
    $catChild->add();

    // 2. Create a CMS page in the Child Category
    $page = new CMS();
    $page->id_cms_category = $catChild->id;
    $page->active = 1;
    $page->meta_title = [1 => 'Test CMS Page'];
    $page->add();

    // 3. Instantiate CmsController and simulate the page view
    $controller = new CmsController();
    $controller->context = Context::getContext();
    $controller->assignCase = CmsController::CMS_CASE_PAGE;
    $controller->cms = $page;

    // 4. Get breadcrumb links
    $breadcrumb = $controller->getBreadcrumbLinks();

    // 5. Verify if the hidden category is present in the breadcrumb
    $foundHidden = false;
    if (isset($breadcrumb['links'])) {
        foreach ($breadcrumb['links'] as $link) {
            if ($link['title'] === 'Hidden Category') {
                $foundHidden = true;
                break;
            }
        }
    }

    echo "Breadcrumb links count: " . count($breadcrumb['links']) . "\n";
    echo "Hidden category found in breadcrumb: " . ($foundHidden ? 'YES' : 'NO') . "\n";

    // If foundHidden is true, the bug is still present (exit 1).
    // If foundHidden is false, the bug is fixed (exit 0).
    exit($foundHidden ? 1 : 0);

} catch (\Throwable $t) {
    echo "Error: " . $t->getMessage() . "\n";
    exit(1);
}
