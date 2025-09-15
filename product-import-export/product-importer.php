<?php
// Кнопка импорта в админбаре
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'update_yml',
        'title' => 'Обновить YML',
        'href'  => wp_nonce_url(admin_url('admin-post.php?action=run_product_import'), 'run_product_import_nonce'),
    ]);
}, 100);

// Обработка импорта
add_action('admin_post_run_product_import', function() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_product_import_nonce')) {
        wp_die('Ошибка безопасности');
    }
    if (!current_user_can('manage_options')) wp_die('Нет доступа');

    $import_url = get_option('product_import_url');
    if (!$import_url) {
        wp_die('URL для импорта не задан. Зайдите в Настройки импорта.');
    }

    $importer = new ProductImporter();
    $result = $importer->import_from_url($import_url);

    wp_redirect(admin_url());
    exit;
});
