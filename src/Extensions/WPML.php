<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Extensions;

use Aikon\PostUpdateScheduler\Options;

final class WPML extends Extension
{

    /**
     * Constructor
     * 
     * @param Options $options Options instance
     */
    public function __construct(private Options $options)
    {
        add_action('cus_create_publishing_post_before', [$this, 'handle_wpml_relationships'], 10, 2);
    }

    /**
     * Checks if WPML is active.
     *
     * @return bool True if WPML is active, false otherwise.
     */
    static function is_active(): bool
    {
        return defined('ICL_SITEPRESS_VERSION');
    }

    /**
     * Handles the Oxygen builder CSS copying
     *
     * @param int $source_id Source post ID
     * @param int $destination_id Destination post ID
     * @return bool Success status
     */
    public function handle_wpml_relationships($source_id, $destination_id, $is_publishing = false) {
        // Early exit if WPML isn't active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return false;
        }

        try {
            // Basic validation
            $post_type = get_post_type($source_id);
            if (!$post_type || $post_type !== get_post_type($destination_id)) {
                return false;
            }

            $element_type = 'post_' . $post_type;

            // Get source language details
            $source_details = apply_filters('wpml_element_language_details', null, array(
                'element_id' => $source_id,
                'element_type' => $element_type
            ));

            if (!$source_details) {
                return false;
            }

            /**
             * Filter whether to create new translation group
             * 
             * @param bool $create_new_group Whether to create new translation group
             * @param int $source_id Source post ID
             * @param int $destination_id Destination post ID
             * @param bool $is_publishing Whether this is a publish operation
             */
            $create_new_group = apply_filters(
                'content_update_scheduler_wpml_new_translation_group',
                !$is_publishing,
                $source_id,
                $destination_id,
                $is_publishing
            );

            // Set language details
            do_action('wpml_set_element_language_details', array(
                'element_id' => $destination_id,
                'element_type' => $element_type,
                'trid' => $create_new_group ? null : apply_filters('wpml_element_trid', null, $source_id, $element_type),
                'language_code' => $source_details->language_code,
                'source_language_code' => $source_details->source_language_code
            ));

            // Copy essential WPML meta
            $wpml_meta_keys = array(
                '_wpml_media_featured',
                '_wpml_media_duplicate',
                '_wpml_media_processed'
            );

            foreach ($wpml_meta_keys as $meta_key) {
                $value = get_post_meta($source_id, $meta_key, true);
                if ($value !== '') {
                    update_post_meta($destination_id, $meta_key, $value);
                }
            }

            /**
             * Fires after WPML relationships have been handled
             * 
             * @param int $source_id Source post ID
             * @param int $destination_id Destination post ID
             * @param bool $is_publishing Whether this is a publish operation
             */
            do_action('content_update_scheduler_after_wpml_handling', 
                $source_id, 
                $destination_id, 
                $is_publishing
            );

            return true;

        } catch (\Exception $e) {
            error_log(sprintf(
                'Content Update Scheduler: WPML handling error for posts %d->%d: %s',
                $source_id,
                $destination_id,
                $e->getMessage()
            ));
            return false;
        }
    }
}