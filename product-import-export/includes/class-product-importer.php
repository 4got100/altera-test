<?php

class ProductImporter {

    /**
     * Импорт товаров по URL YML
     */
    public function import_from_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            pie_log("Ошибка: неверный URL — {$url}");
            wp_die('Ошибка: указан неверный URL для импорта');
        }

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            pie_log("Ошибка запроса: " . $response->get_error_message());
            wp_die('Ошибка при запросе к URL: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            pie_log("Ошибка: пустой ответ от {$url}");
            wp_die('Ошибка: пустой ответ от источника импорта');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            pie_log("Ошибка: некорректный XML по ссылке {$url}");
            wp_die('Ошибка: получен некорректный XML');
        }

        pie_log("Импорт успешно выполнен с {$url}");

        return $this->process_products($xml);
    }

    /**
     * Обработка товаров из YML
     */
    private function process_products($xml) {
        $processed_ids = [];

        // Получаем категории YML
        $categories_map = [];
        if (isset($xml->shop->categories)) {
            foreach ($xml->shop->categories->category as $cat) {
                $cat_id   = (string) $cat['id'];
                $cat_name = (string) $cat;
                $term = term_exists($cat_name, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($cat_name, 'product_cat');
                    pie_log("Создана категория: $cat_name (ID: " . ($term['term_id'] ?? 'неизвестно') . ")");
                }
                $categories_map[$cat_id] = $term['term_id'] ?? $term['term_id'];
            }
        }

        foreach ($xml->shop->offers->offer as $offer) {
            $sku         = (string) $offer->sku;
            $name        = (string) $offer->name;
            $price       = (int) ceil((float)$offer->price * 1.1); // +10%
            $stock       = ((string)$offer->store === 'true') ? 1 : 0;
            $description = (string)$offer->description;
            $category_id = (string)$offer->categoryId;
            $wp_category = $categories_map[$category_id] ?? null;

            // Ищем существующий товар по SKU
            $existing = get_posts([
                'post_type'  => 'product',
                'meta_key'   => 'sku',
                'meta_value' => $sku,
                'numberposts'=> 1,
                'fields'     => 'ids'
            ]);

            $post_data = [
                'post_title'   => $name,
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_type'    => 'product',
                'meta_input'   => [
                    'price' => $price,
                    'sku'   => $sku,
                    'stock' => $stock,
                ]
            ];

            if ($existing) {
                $post_data['ID'] = $existing[0];
                wp_update_post($post_data);
                $post_id = $existing[0];
                pie_log("Обновлён товар: $name (SKU: $sku)");
            } else {
                $post_id = wp_insert_post($post_data);
                if ($post_id) {
                    pie_log("Создан новый товар: $name (SKU: $sku)");
                } else {
                    pie_log("Не удалось создать товар: $name (SKU: $sku)");
                }
            }

            // Привязка категории
            if ($wp_category && $post_id) {
                wp_set_post_terms($post_id, [$wp_category], 'product_cat');                
            }

            if ($post_id) {
                $processed_ids[] = $post_id;
            }

            update_post_meta($post_id, 'stock', $stock);
        }

        // Переводим товары, которых нет в YML, в черновик
        $all_products = get_posts([
            'post_type'   => 'product',
            'numberposts' => -1,
            'fields'      => 'ids'
        ]);

        foreach ($all_products as $pid) {
            if (!in_array($pid, $processed_ids)) {
                wp_update_post([
                    'ID' => $pid,
                    'post_status' => 'draft'
                ]);
                pie_log("Товар ID {$pid} переведен в черновик, так как отсутствует в YML");
            }
        }

        return true;
    }
}
