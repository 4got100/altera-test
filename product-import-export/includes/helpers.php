<?php

// Логирование в файл
function pie_log($message) {
	$date = date('d.m.Y H:i:s');
	$entry = "[{$date}] {$message}\n";
	file_put_contents(PIE_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}


// Генерация sitemap.xml
function pie_generate_sitemap() {
$sitemap_file = ABSPATH . 'sitemap.xml';

$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Добавляем страницы сайта
$pages = get_posts([
	'post_type'   => 'page',
	'numberposts' => -1,
	'post_status' => 'publish'
]);

foreach ($pages as $page) {
	$xml .= "  <url>\n";
	$xml .= "    <loc>" . esc_url(get_permalink($page->ID)) . "</loc>\n";
	$xml .= "    <lastmod>" . get_the_modified_date('c', $page->ID) . "</lastmod>\n";
	$xml .= "    <changefreq>weekly</changefreq>\n";
	$xml .= "  </url>\n";
}

// Добавляем товары
$products = get_posts([
	'post_type'   => 'product',
	'numberposts' => -1,
	'post_status' => 'publish'
]);

foreach ($products as $product) {
	$xml .= "  <url>\n";
	$xml .= "    <loc>" . esc_url(get_permalink($product->ID)) . "</loc>\n";
	$xml .= "    <lastmod>" . get_the_modified_date('c', $product->ID) . "</lastmod>\n";
	$xml .= "    <changefreq>daily</changefreq>\n";
	$xml .= "  </url>\n";
}

$xml .= '</urlset>';

file_put_contents($sitemap_file, $xml);
pie_log("Sitemap сгенерирован: {$sitemap_file}");
}


