<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Extensions;

use Aikon\PostUpdateScheduler\Options;

final class Elementor extends Extension
{
    /**
     * Constructor
     * 
     * @param Options $options Options instance
     */
    public function __construct(private Options $options)
    {
        add_action('cus_create_publishing_post_before', [$this, 'copy_elementor_data'], 10, 2);
    }


    /**
     * Checks if Elementor is active.
     * 
     * @return bool True if Elementor is active, false otherwise.
     */
    static function is_active(): bool
    {
        return defined('ELEMENTOR_VERSION');
    }

     /**
     * Copies Elementor-specific data between posts
     * 
     * @param int $source_id Source post ID
     * @param int $destination_id Destination post ID
     * @return bool Success status
     */
    public function copy_elementor_data(int $source_id, int $destination_id): bool {

        try {
            // Core Elementor meta keys that must be preserved
            $elementor_meta_keys = [
                '_elementor_data',
                '_elementor_edit_mode', 
                '_elementor_page_settings',
                '_elementor_version',
                '_elementor_template_type',
                '_elementor_controls_usage'
            ];

            // Get all meta at once for efficiency
            $source_meta = get_post_meta($source_id);
            
            foreach ($elementor_meta_keys as $key) {
                if (!isset($source_meta[$key][0])) {
                    continue;
                }

                $value = $source_meta[$key][0];
                
                // Special handling for Elementor's JSON data
                if ($key === '_elementor_data') {
                    // Ensure valid JSON 
                    $decoded = json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log(sprintf(
                            'Content Update Scheduler: Invalid Elementor JSON in post %d',
                            $source_id
                        ));
                    } else {
                        update_post_meta($destination_id, $key, wp_slash($value));
                    }
                    continue;
                }

                // Copy other Elementor meta directly
                update_post_meta($destination_id, $key, maybe_unserialize($value));
            }

            // Copy Elementor CSS file
            $upload_dir = wp_upload_dir();
            $source_css = $upload_dir['basedir'] . '/elementor/css/post-' . $source_id . '.css';
            $dest_css = $upload_dir['basedir'] . '/elementor/css/post-' . $destination_id . '.css';
            
            if (file_exists($source_css)) {
                @copy($source_css, $dest_css);
            }

            return true;

        } catch (\Exception $e) {
            error_log(sprintf(
                'Content Update Scheduler: Error copying Elementor data: %s',
                $e->getMessage()
            ));
            return false;
        }
    }
}