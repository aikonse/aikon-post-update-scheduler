<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Services;

use Aikon\PostUpdateScheduler\Options;
use WP_Post;
use WP_Error;

/**
 * Handles post publishing and updating functionality
 */
class PublishingService
{
    /**
     * @var Options
     */
    private Options $options;

    /**
     * @var PostService
     */
    private PostService $post_service;

    /**
     * @param Options $options
     * @param PostService $post_service
     */
    public function __construct(Options $options, PostService $post_service)
    {
        $this->options = $options;
        $this->post_service = $post_service;
    }

    /**
     * Creates a publishing copy of a post
     *
     * @param WP_Post $post Post to be copied
     * @return int|WP_Error ID of the new post or WP_Error
     */
    public function create_publishing_post(WP_Post $post)
    {
        $original = $post->ID;
        if ($post->post_status === PostService::STATUS) {
            $original = get_post_meta($post->ID, PostService::STATUS . '_original', true);
        }
        
        $new_author = get_user_by('id', $post->post_author);

        // Create the new post
        $new_post = [
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => $new_author->ID,
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_mime_type' => $post->mime_type,
            'post_parent'    => $post->ID,
            'post_password'  => $post->post_password,
            'post_status'    => PostService::STATUS,
            'post_title'     => $post->post_title,
            'post_type'      => $post->post_type,
        ];

        // Insert the new post
        $new_post_id = wp_insert_post($new_post, true);
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }

        // Allow plugins to hook in before copying meta
        do_action('cus_create_publishing_post_before', $new_post_id, $original);

        // Copy meta and terms over to the new post
        $this->copy_meta_and_terms($post->ID, $new_post_id);

        // Reference the original post
        update_post_meta($new_post_id, PostService::STATUS . '_original', $original);
        
        // Ensure the keep_dates setting is not copied from previous scheduled updates
        delete_post_meta($new_post_id, PostService::STATUS . '_keep_dates');

        // Allow plugins to hook in after copying meta
        do_action('cus_create_publishing_post_after', $new_post_id, $original);
        
        // Handle WooCommerce products if needed
        //$this->handle_woocommerce_product($post, $new_post_id, $original);

        /**
         * Legacy action for backwards compatibility
         * 
         * @param int $new_post_id ID of the newly created post
         * @param int $original ID of the original post
         */
        do_action('ContentUpdateScheduler\\create_publishing_post', $new_post_id, $original);

        return $new_post_id;
    }

    /**
     * Publishes a scheduled update
     *
     * @param int $post_id The ID of the scheduled update post
     * @return int|WP_Error The ID of the updated post or WP_Error
     */
    public function publish_post(int $post_id)
    {
        // Implement locking mechanism
        $lock_key = 'cus_publish_lock_' . $post_id;
        if (!get_transient($lock_key)) {
            set_transient($lock_key, true, 300); // Lock for 5 minutes
        } else {
            return new WP_Error('locked', 'Publish process already running for this post');
        }

        try {
            $orig_id = get_post_meta($post_id, PostService::STATUS . '_original', true);

            // Break early if given post is not an actual scheduled post created by this plugin
            if (!$orig_id) {
                return new WP_Error('no_original', 'No original post found');
            }

            $orig = get_post($orig_id);
            if (!$orig) {
                return new WP_Error('original_not_found', 'Original post not found');
            }

            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error('scheduled_not_found', 'Scheduled post not found');
            }

            // Ensure the post is not in the trash before proceeding
            if ($post->post_status === 'trash') {
                return new WP_Error('post_trashed', 'Post is in trash');
            }

            // Save original stock status and quantity for WooCommerce
            $original_stock_status = get_post_meta($orig->ID, '_stock_status', true);
            $original_stock_quantity = get_post_meta($orig->ID, '_stock', true);

            // Allow plugins to hook in before updating
            do_action('ContentUpdateScheduler\\before_publish_post', $post, $orig);
            
            // Start "transaction"
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            // Copy meta and terms, restoring references to the original post ID
            $this->copy_meta_and_terms($post->ID, $orig->ID, true);
            delete_post_meta($orig->ID, PostService::STATUS . '_pubdate');

            // Set up the post object for update
            $updated_post = clone $post;
            $updated_post->ID = $orig->ID;
            $updated_post->post_name = $orig->post_name;
            $updated_post->guid = $orig->guid;
            $updated_post->post_parent = $orig->post_parent;
            $updated_post->post_status = $orig->post_status;
            
            $keep_dates = get_post_meta($post_id, PostService::STATUS . '_keep_dates', true) === 'yes';

            if ($keep_dates) {
                // Keep original dates but update modified date
                $updated_post->post_date = $orig->post_date;
                $updated_post->post_date_gmt = $orig->post_date_gmt;
                $updated_post->post_modified = wp_date('Y-m-d H:i:s');
                $updated_post->post_modified_gmt = get_gmt_from_date($updated_post->post_modified);
            } else {
                // Use new dates
                $post_date = wp_date('Y-m-d H:i:s');
                
                /**
                 * Filter the new posts' post date
                 *
                 * @param string $post_date The date to be used
                 * @param WP_Post $post The scheduled update post
                 * @param WP_Post $orig The original post
                 */
                $post_date = apply_filters('ContentUpdateScheduler\\publish_post_date', $post_date, $post, $orig);

                $updated_post->post_date = $post_date;
                $updated_post->post_date_gmt = get_gmt_from_date($post_date);
                $updated_post->post_modified = $post_date;
                $updated_post->post_modified_gmt = $updated_post->post_date_gmt;
            }

            // Update the post
            $result = wp_update_post($updated_post, true);
            if (is_wp_error($result)) {
                $wpdb->query('ROLLBACK');
                return $result;
            }

            // Restore WooCommerce stock data
            if ($original_stock_status !== '') {
                update_post_meta($updated_post->ID, '_stock_status', $original_stock_status);
            }
            if ($original_stock_quantity !== '') {
                update_post_meta($updated_post->ID, '_stock', $original_stock_quantity);
            }

            // Delete the scheduled post
            $delete_result = wp_delete_post($post_id, true);
            if (is_wp_error($delete_result)) {
                $wpdb->query('ROLLBACK');
                return $delete_result;
            }

            $wpdb->query('COMMIT');
            return $orig->ID;
            
        } catch (\Exception $e) {
            global $wpdb;
            $wpdb->query('ROLLBACK');
            return new WP_Error('publish_exception', $e->getMessage());
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * Saves a post's publishing date
     *
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @return mixed
     */
    public function save_meta(int $post_id, WP_Post $post)
    {
        // Only process our custom post status
        if ($post->post_status !== PostService::STATUS && !get_post_meta($post_id, PostService::STATUS . '_original', true)) {
            return $post_id;
        }

        $nonce = PostService::STATUS . '_nonce';
        $pub = PostService::STATUS . '_pubdate';

        // Verify nonce and permissions
        if (!isset($_POST[$nonce]) || !wp_verify_nonce(sanitize_text_field($_POST[$nonce]), basename(__FILE__))) {
            return $post_id;
        }
        
        if (!current_user_can(get_post_type_object($post->post_type)->cap->edit_post, $post_id)) {
            return $post_id;
        }

        // Process date/time inputs
        if (isset($_POST[$pub . '_month'], $_POST[$pub . '_day'], $_POST[$pub . '_year'], $_POST[$pub . '_time'])) {
            $month = intval($_POST[$pub . '_month']);
            $day = intval($_POST[$pub . '_day']);
            $year = intval($_POST[$pub . '_year']);
            $time = sanitize_text_field($_POST[$pub . '_time']);

            // Get WordPress timezone
            $tz = wp_timezone();
            
            // Create date string and explicitly set timezone
            $date_string = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
            $date_time = \DateTime::createFromFormat('Y-m-d H:i', $date_string, $tz);

            if ($date_time === false) {
                return $post_id;
            }

            // Convert to UTC before getting timestamp
            $date_time->setTimezone(new \DateTimeZone('UTC'));
            $stamp = $date_time->getTimestamp();

            // Ensure date is in the future
            $current_time = new \DateTime('now', $tz);
            if ($date_time <= $current_time) {
                $date_time = clone $current_time;
                $date_time->modify('+5 minutes');
                $stamp = $date_time->getTimestamp();
            }

            // Clear existing schedule and set new one
            wp_clear_scheduled_hook('cus_publish_post', [$post_id]);
            update_post_meta($post_id, $pub, $stamp);
            wp_schedule_single_event($stamp, 'cus_publish_post', [$post_id]);
            
            // Save keep_dates preference
            $keep_dates_key = PostService::STATUS . '_keep_dates';
            if (isset($_POST[$keep_dates_key])) {
                update_post_meta($post_id, $keep_dates_key, 'yes');
            } else {
                delete_post_meta($post_id, $keep_dates_key);
            }
        }

        // Handle WooCommerce product stock data
        //$this->handle_woocommerce_stock_data($post_id);

        return $post_id;
    }

    /**
     * Copies meta and terms from one post to another
     *
     * @param int $source_post_id Source post ID
     * @param int $destination_post_id Destination post ID
     * @param bool $restore_references Whether to restore post ID references
     * @return void
     */
    private function copy_meta_and_terms(int $source_post_id, int $destination_post_id, bool $restore_references = false): void
    {
        $source_post = get_post($source_post_id);
        $destination_post = get_post($destination_post_id);

        // Abort if any of the ids is not a post
        if (!$source_post || !$destination_post) {
            return;
        }

        // Store current kses status and temporarily disable filters
        $should_filter = !current_filter('content_save_pre');
        if ($should_filter) {
            remove_filter('content_save_pre', 'wp_filter_post_kses');
            remove_filter('db_insert_value', 'wp_filter_kses');
        }

        try {
            // Copy meta
            $meta = get_post_meta($source_post->ID);
            foreach ($meta as $key => $values) {
                // Skip original post reference when copying back to the original
                if ($restore_references && $key === PostService::STATUS . '_original') {
                    continue;
                }
                
                delete_post_meta($destination_post->ID, $key);
                foreach ($values as $value) {
                    $processed_value = $this->process_meta_value($value);
                    
                    // Replace post ID references if needed
                    if ($restore_references && is_string($processed_value) && 
                        strpos($processed_value, (string)$source_post->ID) !== false) {
                        $processed_value = str_replace(
                            (string)$source_post->ID, 
                            (string)$destination_post->ID, 
                            $processed_value
                        );
                    }
                    
                    add_post_meta($destination_post->ID, $key, $processed_value);
                }
            }

            // Copy terms
            $taxonomies = get_object_taxonomies($source_post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($source_post->ID, $taxonomy, [
                    'orderby' => 'term_order',
                ]);
                $terms = [];
                foreach ($post_terms as $term) {
                    $terms[] = $term->slug;
                }
                wp_set_object_terms($destination_post->ID, null, $taxonomy);
                wp_set_object_terms($destination_post->ID, $terms, $taxonomy);
            }
            
            // Process integration with page builders
            //$this->process_page_builder_integrations($source_post->ID, $destination_post->ID, $restore_references);
            
        } finally {
            // Restore filters if they were active
            if ($should_filter) {
                add_filter('content_save_pre', 'wp_filter_post_kses');
                add_filter('db_insert_value', 'wp_filter_kses');
            }
        }
    }

    /**
     * Process meta value for proper storage
     *
     * @param mixed $value Meta value
     * @return mixed Processed value
     */
    private function process_meta_value($value)
    {
        // If the value is serialized, handle it carefully
        if (is_serialized($value)) {
            $unserialized = maybe_unserialize($value);
            if ($unserialized === false) {
                return $value; // Return original if unserialization fails
            }
            return $unserialized;
        }

        // Check if value is JSON encoded (Elementor uses this)
        if (is_string($value) && substr($value, 0, 1) === '{' && substr($value, -1) === '}') {
            $json_decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // If it's valid JSON, store the raw value to preserve Unicode escapes
                return $value;
            }
        }

        return $value;
    }


}