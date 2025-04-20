<?php

namespace Aikon\PostUpdateScheduler\Extensions;

use Aikon\PostUpdateScheduler\Options;

final class WooCommerce extends Extension
{

    /**
     * Constructor
     * 
     * @param Options $options Options instance
     */
    public function __construct(private Options $options)
    {

    }
    
    /**
     * Checks if WooCommerce is active.
     *
     * @return bool True if WooCommerce is active, false otherwise.
     */
    static function is_active(): bool
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Copies grouped products from one product to another.
     *
     * @param int $source_product_id      The product from which to copy grouped products.
     * @param int $destination_product_id The product which will get the grouped products.
     *
     * @return void
     */
    private static function copy_grouped_products(int $source_product_id, int $destination_product_id): void
    {
        $grouped_products = get_post_meta($source_product_id, '_children', true);
        if (!empty($grouped_products)) {
            update_post_meta($destination_product_id, '_children', $grouped_products);
        }
    }

    /**
     * Copies simple product data from one product to another.
     *
     * @param int $source_product_id      The product from which to copy simple product data.
     * @param int $destination_product_id The product which will get the simple product data.
     *
     * @return void
     */
    private static function copy_simple_product(int $source_product_id, int $destination_product_id): void
    {
        $regular_price = get_post_meta($source_product_id, '_regular_price', true);
        $sale_price = get_post_meta($source_product_id, '_sale_price', true);
        $stock_status = get_post_meta($source_product_id, '_stock_status', true);
        $stock_quantity = get_post_meta($source_product_id, '_stock', true);
        $manage_stock = get_post_meta($source_product_id, '_manage_stock', true);
        $backorders = get_post_meta($source_product_id, '_backorders', true);

        if (!empty($regular_price)) {
            update_post_meta($destination_product_id, '_regular_price', $regular_price);
        }
        if (!empty($sale_price)) {
            update_post_meta($destination_product_id, '_sale_price', $sale_price);
        }
        update_post_meta($destination_product_id, '_stock_status', $stock_status);
        update_post_meta($destination_product_id, '_stock', $stock_quantity);
        update_post_meta($destination_product_id, '_manage_stock', $manage_stock);
        update_post_meta($destination_product_id, '_backorders', $backorders);
    }


        /**
     * Copies variations from one product to another.
     *
     * @param int $source_product_id      The product from which to copy variations.
     * @param int $destination_product_id The product which will get the variations.
     *
     * @return void
     */
    private static function copy_product_variations($source_product_id, $destination_product_id)
    {
        $variations = get_children(array(
            'post_parent' => $source_product_id,
            'post_type'   => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'any',
        ));

        foreach ($variations as $variation) {
            $new_variation = array(
                'post_title'   => $variation->post_title,
                'post_name'    => $variation->post_name,
                'post_status'  => $variation->post_status,
                'post_parent'  => $destination_product_id,
                'post_type'    => 'product_variation',
                'menu_order'   => $variation->menu_order,
                'post_excerpt' => $variation->post_excerpt,
                'post_content' => $variation->post_content,
            );

            $new_variation_id = wp_insert_post($new_variation);

            // Copy variation meta data
            $meta_data = get_post_meta($variation->ID);
            foreach ($meta_data as $key => $value) {
                update_post_meta($new_variation_id, $key, maybe_unserialize($value[0]));
            }

            // Ensure stock status, stock quantity, manage stock, and backorders are correctly copied
            $original_stock_status = get_post_meta($variation->ID, '_stock_status', true);
            $original_stock_quantity = get_post_meta($variation->ID, '_stock', true);
            $original_manage_stock = get_post_meta($variation->ID, '_manage_stock', true);
            $original_backorders = get_post_meta($variation->ID, '_backorders', true);

            if ($original_stock_status !== '') {
                update_post_meta($new_variation_id, '_stock_status', $original_stock_status);
            }

            if ($original_stock_quantity !== '') {
                update_post_meta($new_variation_id, '_stock', $original_stock_quantity);
            }
            
            if ($original_manage_stock !== '') {
                update_post_meta($new_variation_id, '_manage_stock', $original_manage_stock);
            }
            
            if ($original_backorders !== '') {
                update_post_meta($new_variation_id, '_backorders', $original_backorders);
            }
        }
    }
}