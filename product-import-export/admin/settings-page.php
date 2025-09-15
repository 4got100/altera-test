<?php
// Добавляем пункт меню
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Настройки импорта',
        'Настройки импорта',
        'manage_options',
        'product-import-settings',
        'render_import_settings_page'
    );
});

// Страница настроек
function render_import_settings_page() {
    if (isset($_POST['product_import_url'])) {
        check_admin_referer('product_import_settings_save', 'product_import_nonce');
        update_option('product_import_url', esc_url_raw($_POST['product_import_url']));
        echo '<div class="notice notice-success"><p>URL сохранён!</p></div>';
    }

    $url = get_option('product_import_url', '');
    ?>
    <div class="wrap">
        <h1>Настройки импорта товаров</h1>
        <form method="post">
            <?php wp_nonce_field('product_import_settings_save', 'product_import_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>URL YML для импорта</th>
                    <td><input type="url" name="product_import_url" value="<?php echo esc_attr($url); ?>" size="60" /></td>
                </tr>
            </table>
            <?php submit_button('Сохранить'); ?>
        </form>
    </div>
    <?php
}
