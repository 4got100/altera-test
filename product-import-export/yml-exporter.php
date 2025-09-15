<?php
// Кнопка экспорта в админбаре
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $wp_admin_bar->add_node([
        'id'    => 'export_yml',
        'title' => 'Экспорт YML',
        'href'  => wp_nonce_url(admin_url('admin-post.php?action=run_yml_export'), 'run_yml_export_nonce'),
    ]);
}, 100);

// Обработка экспорта
add_action('admin_post_run_yml_export', function() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'run_yml_export_nonce')) {
        wp_die('Ошибка безопасности');
    }
    if (!current_user_can('manage_options')) wp_die('Нет доступа');
	
    $exporter = new YML_Exporter();
    $exporter->clear_cache();
	$yml_content = $exporter->generate_yml();

    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="yml-export.xml"');
    echo $yml_content;
    exit;
});
