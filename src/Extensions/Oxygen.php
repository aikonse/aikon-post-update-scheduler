<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Extensions;

use Aikon\PostUpdateScheduler\Options;
use \Exception;

final class Oxygen extends Extension
{

    /**
     * Constructor
     * 
     * @param Options $options Options instance
     */
    public function __construct(private Options $options)
    {
        add_action('cus_create_publishing_post_before', [$this, 'copy_oxygen_data'], 10, 2);
    }
    
    static function is_active(): bool
    {
        return in_array('oxygen/functions.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    public function copy_oxygen_data($source_id, $destination_id) {
        // Early exit if Oxygen isn't active
        if (!in_array('oxygen/functions.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return false;
        }

        try {
            $upload_dir = wp_upload_dir();
            $source_css = $upload_dir['basedir'] . '/oxygen/css/' . 
                         get_post_field('post_name', $source_id) . '-' . $source_id . '.css';
            $dest_css = $upload_dir['basedir'] . '/oxygen/css/' . 
                       get_post_field('post_name', $destination_id) . '-' . $destination_id . '.css';

            // Create destination file if it doesn't exist
            if (!file_exists($dest_css)) {
                @touch($dest_css);
            }

            // Copy CSS if source exists
            if (file_exists($source_css)) {
                @copy($source_css, $dest_css);
            }

            return true;

        } catch (Exception $e) {
            error_log(sprintf(
                'Content Update Scheduler: Error copying Oxygen data: %s',
                $e->getMessage()
            ));
            return false;
        }
    }
}