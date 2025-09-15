<?php
// Папка для логов
if (!defined('PIE_LOG_FILE')) {
    define('PIE_LOG_FILE', get_template_directory() . '/product-import-export/product-import-export.log');
}

require_once __DIR__ . '/includes/class-product-importer.php';
require_once __DIR__ . '/includes/class-yml-exporter.php';
require_once __DIR__ . '/admin/settings-page.php';
require_once __DIR__ . '/product-importer.php';
require_once __DIR__ . '/yml-exporter.php';
require_once __DIR__ . '/includes/helpers.php';

// Флаг для отслеживания, нужно ли обновить YML (для того, чтобы yml обновлялся только один раз)
global $pie_yml_needs_update;
$pie_yml_needs_update = false;

// Регистрируем Товар

add_action('init', function() {
    register_post_type('product', [
        'labels' => [
            'name'          => 'Товары',
            'singular_name' => 'Товар',
            'add_new'       => 'Добавить товар',
            'add_new_item'  => 'Добавить новый товар',
            'edit_item'     => 'Редактировать товар',
            'new_item'      => 'Новый товар',
            'view_item'     => 'Просмотр товара',
            'search_items'  => 'Найти товар',
            'not_found'     => 'Товары не найдены',
            'menu_name'     => 'Товары',
        ],
        'public'       => true,
        'menu_icon'    => 'dashicons-cart',
        'supports'     => ['title', 'editor', 'thumbnail'],
        'has_archive'  => true,
        'show_in_rest' => true,
    ]);

    register_taxonomy('product_cat', 'product', [
        'labels' => [
            'name'          => 'Категории товаров',
            'singular_name' => 'Категория',
            'search_items'  => 'Поиск категорий',
            'all_items'     => 'Все категории',
            'edit_item'     => 'Редактировать категорию',
            'update_item'   => 'Обновить категорию',
            'add_new_item'  => 'Добавить категорию',
            'menu_name'     => 'Категории',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
    ]);
});


// Регистрируем мета-поля

add_action('init', function() {
    register_post_meta('product', 'price', [
        'type'         => 'integer',
        'single'       => true,
        'show_in_rest' => true,
    ]);
    register_post_meta('product', 'sku', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true,
    ]);
    register_post_meta('product', 'stock', [
        'type'         => 'boolean',
        'single'       => true,
        'show_in_rest' => true,
        'default'      => true,
    ]);
});


// Метабокс для редактирования
add_action('add_meta_boxes', function() {
    add_meta_box(
        'product_meta_box',
        'Данные о товаре',
        'render_product_meta_box',
        'product',
        'normal',
        'default'
    );
});

function render_product_meta_box($post) {
    $price = get_post_meta($post->ID, 'price', true);
    $sku   = get_post_meta($post->ID, 'sku', true);
    $stock = get_post_meta($post->ID, 'stock', true);
    ?>
    <p>
        <label>Цена:</label><br>
        <input type="number" name="price" value="<?= esc_attr($price) ?>" />
    </p>
    <p>
        <label>Артикул:</label><br>
        <input type="text" name="sku" value="<?= esc_attr($sku) ?>" />
    </p>
    <p>
        <label>
            <input type="checkbox" name="stock" value="1" <?= checked($stock, 1, false) ?> />
            В наличии
        </label>
    </p>
    <?php
}


// Сохраняем мета-поля
// Автогенерация YML при сохранении или изменении товара
// Сохраняем мета-поля и обновляем YML
add_action('save_post_product', function ($post_id, $post, $update) {

    if (array_key_exists('price', $_POST)) {
        update_post_meta($post_id, 'price', intval($_POST['price']));
    }
    if (array_key_exists('sku', $_POST)) {
        update_post_meta($post_id, 'sku', sanitize_text_field($_POST['sku']));
    }
    if (array_key_exists('stock', $_POST)) {
        update_post_meta($post_id, 'stock', 1);
    } else {
        update_post_meta($post_id, 'stock', 0);
    }	

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'product') return;

	global $pie_yml_needs_update;
    $pie_yml_needs_update = true;
}, 20, 3);

// Автогенерация YML при удалении товара
add_action('before_delete_post', function ($post_id) {
    if (get_post_type($post_id) !== 'product') return;
	$pie_yml_needs_update = true;
}, 20);

// 
add_action('shutdown', function() {
    global $pie_yml_needs_update;
    if (!$pie_yml_needs_update) return;

    if (class_exists('YML_Exporter')) {
        $exporter = new YML_Exporter();
        $exporter->clear_cache();
        $exporter->generate_yml();
		pie_generate_sitemap();

        pie_log("YML обновлён автоматически после изменения/удаления товаров");
    }

    // Сбрасываем флаг
    $pie_yml_needs_update = false;
});