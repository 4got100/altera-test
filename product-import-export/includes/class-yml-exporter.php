<?php

class YML_Exporter {

    private $cache_file;
    private $cache_time; // в секундах

    public function __construct($cache_file = null, $cache_time = 43200) {
        $this->cache_file = $cache_file ?? WP_CONTENT_DIR . '/yml-export.xml';
        $this->cache_time = $cache_time; // 12 часов по умолчанию
    }

    /**
     * Генерация YML
     */
    public function generate_yml() {
        // Если есть свежий кэш — возвращаем
        if (file_exists($this->cache_file) && (time() - filemtime($this->cache_file) < $this->cache_time)) {
            return file_get_contents($this->cache_file);
        }

        // Генерация YML
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><yml_catalog/>');
        $xml->addAttribute('date', date('Y-m-d H:i'));

        $shop = $xml->addChild('shop');
        $shop->addChild('name', 'Сова-Нянька.рф');
        $shop->addChild('company', 'Сова-Нянька');
        $shop->addChild('url', get_site_url());

        // Получаем категории
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false
        ]);
        $categories_xml = $shop->addChild('categories');
        $category_map = [];
        foreach ($terms as $term) {
            $cat = $categories_xml->addChild('category', $term->name);
            $cat->addAttribute('id', $term->term_id);
            if ($term->parent) {
                $cat->addAttribute('parentId', $term->parent);
            }
            $category_map[$term->term_id] = $term->term_id;
        }

        // Получаем товары
        $products = get_posts([
            'post_type'   => 'product',
            'numberposts' => -1,
        ]);

        $offers_xml = $shop->addChild('offers');

        foreach ($products as $product) {
            $price = get_post_meta($product->ID, 'price', true);
            $sku   = get_post_meta($product->ID, 'sku', true);
            $stock = get_post_meta($product->ID, 'stock', true);

            $offer = $offers_xml->addChild('offer');
            $offer->addAttribute('id', $product->ID);
            $offer->addAttribute('available', $stock ? 'true' : 'false');

            $offer->addChild('name', htmlspecialchars($product->post_title));
            $offer->addChild('vendor', 'Сова-Нянька');
            $offer->addChild('vendorCode', htmlspecialchars($sku));
            $offer->addChild('url', get_permalink($product->ID));
            $offer->addChild('price', intval($price));
            $offer->addChild('currencyId', 'RUB');

            // Категория
            $terms_ids = wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'ids']);
            if (!empty($terms_ids)) {
                $offer->addChild('categoryId', $terms_ids[0]);
            }

            // Описание
            $description = $product->post_content ?: '';
            $desc_node = $offer->addChild('description');
            $cdata = $desc_node->addChild('![CDATA[' . $description . ']]');
        }

        // Сохраняем в кэш
        $dir = dirname($this->cache_file);
        if (!file_exists($dir)) wp_mkdir_p($dir);
        $xml_content = $xml->asXML();
        file_put_contents($this->cache_file, $xml_content);

        return $xml_content;
    }
	
	// Удаление кэша YML
	public function clear_cache() {
    if (file_exists($this->cache_file)) {
        unlink($this->cache_file);
    }
}

}
