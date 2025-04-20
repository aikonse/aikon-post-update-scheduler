<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Services;

use Aikon\PostUpdateScheduler\Options;
use WP_Post;

/**
 * Handles meta box functionality for the Content Update Scheduler
 */
class MetaBoxService
{
    /**
     * Meta box title
     */
    private const META_BOX_TITLE = 'Scheduled Update';

    /**
     * @var Options
     */
    private Options $options;

    /**
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * Add meta box for scheduled posts
     *
     * @param string $post_type Post type
     * @param WP_Post $post Current post
     * @return void
     */
    public function add_meta_boxes_page(string $post_type, WP_Post $post): void
    {
        // Only add meta box for posts with our custom status
        if ($post->post_status !== PostService::STATUS) {
            return;
        }

        // Hide unnecessary publishing options
        add_action('admin_head', function () {
            echo '<style> #duplicate-action, #delete-action, #minor-publishing-actions, #misc-publishing-actions, #preview-action {display:none;} </style>';
        });

        // Enqueue necessary scripts and styles
        $dateTimePickerAsset = require APUS_PLUGIN_DIR . 'build/dateTimePicker.asset.php';
        wp_enqueue_script(
            'apus-datetimepicker',
            APUS_PLUGIN_URL . 'build/dateTimePicker.js',
            $dateTimePickerAsset['dependencies'],
            $dateTimePickerAsset['version'],
            true
        );
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('wp-admin');

        // Add the meta box
        add_meta_box(
            'meta_' . PostService::STATUS,
            self::META_BOX_TITLE,
            [$this, 'render_meta_box'],
            $post_type,
            'side'
        );
    }

    /**
     * Render the meta box content
     *
     * @param WP_Post $post Current post
     * @return void
     */
    public function render_meta_box(WP_Post $post): void
    {
        $metaname = PostService::STATUS . '_pubdate';
        $stamp = get_post_meta($post->ID, $metaname, true);
        
        $date = '';
        $time = '';
        
        if ($stamp) {
            // Convert timestamp to ISO 8601 format in the site's timezone for JS compatibility
            $date_obj = new \DateTime('@' . $stamp);
            $timezone = new \DateTimeZone(wp_timezone_string());
            $date_obj->setTimezone($timezone);
            
            // Format the date and time for display
            $date = $date_obj->format('Y-m-d');
            $time = $date_obj->format('H:i');
            
            // Convert to ISO 8601 format for JS
            $stamp = $date_obj->format('c');
        } else {
            // Set default date to tomorrow in site's timezone
            $date_obj = new \DateTime('now', new \DateTimeZone(wp_timezone_string()));
            $date_obj->modify('+1 day');
            
            $date = $date_obj->format('Y-m-d');
            $time = $date_obj->format('H:i');
            
            // Convert to ISO 8601 format for JS
            $stamp = $date_obj->format('c');
        }
        
        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);
        
        $months = array(
            __('January', 'aikon-post-update-scheduler'),
            __('February', 'aikon-post-update-scheduler'),
            __('March', 'aikon-post-update-scheduler'),
            __('April', 'aikon-post-update-scheduler'),
            __('May', 'aikon-post-update-scheduler'),
            __('June', 'aikon-post-update-scheduler'),
            __('July', 'aikon-post-update-scheduler'),
            __('August', 'aikon-post-update-scheduler'),
            __('September', 'aikon-post-update-scheduler'),
            __('October', 'aikon-post-update-scheduler'),
            __('November', 'aikon-post-update-scheduler'),
            __('December', 'aikon-post-update-scheduler'),
        );
        
        view('scheduled-updates-metabox', [
            'metaname'   => $metaname,
            'stamp'      => $stamp,
            'date'       => $date,
            'time'       => $time,
            'year'       => $year,
            'month'      => $month,
            'day'        => $day,
            'months'     => $months,
            'post'       => $post,
            'nonceField' => PostService::STATUS . '_nonce',
            'options'   => $this->options,
        ]);
    }
}