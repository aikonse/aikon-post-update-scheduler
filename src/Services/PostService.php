<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Services;

use Aikon\PostUpdateScheduler\Options;
use DateTime;
use DateTimeZone;
use WP_Post;

/**
 * Handles post status and type management
 */
class PostService
{
    /**
     * Post status name
     */
    public const STATUS = 'apus_publish';

    /**
     * Post status label
     */
    public const LABEL = 'Scheduled Content Update';

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
     * Register the custom post status
     *
     * @return void
     */
    public function register_post_status(): void
    {
        $public = false;
        if ($this->options->get('tsu_visible')) {
            // Only register as public if we're not on the search page
            $public = !is_search();
        }

        // Compatibility with CMS Tree Page View
        $exclude_from_search = !is_admin();

        register_post_status(self::STATUS, [
            'label'                     => _x('Scheduled Content Update', 'Status General Name', 'cus-scheduleupdate-td'),
            'public'                    => $public,
            'internal'                  => true,
            'publicly_queryable'        => true,
            'protected'                 => true,
            'exclude_from_search'       => $exclude_from_search,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Scheduled Content Update <span class="count">(%s)</span>',
                'Scheduled Content Update <span class="count">(%s)</span>',
                'cus-scheduleupdate-td'
            ),
        ]);
    }

    /**
     * Register post type support for the custom status
     *
     * @return void
     */
    public function register_post_type_support(): void
    {
        // Get all public post types plus 'product'
        $post_types = array_merge(
            get_post_types(['public' => true], 'names'),
            ['product']
        );

        /**
         * Filter to exclude specific post types from content update scheduling
         */
        $excluded_post_types = apply_filters('apus_excluded_post_types', []);

        // Ensure excluded_post_types is an array
        $excluded_post_types = is_array($excluded_post_types) ? $excluded_post_types : [];

        // Remove excluded post types
        $post_types = array_diff($post_types, $excluded_post_types);
        $post_types = array_unique($post_types);

        // Register post type support
        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type)) {
                add_filter("manage_edit-{$post_type}_columns", [$this, 'add_republication_column']);
                add_action("manage_{$post_type}_posts_custom_column", [$this, 'populate_republication_column'], 10, 2);
            }
        }
    }

    /**
     * Add the republication date column to post list tables
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_republication_column(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $val) {
            $new[$key] = $val;
            if ('title' === $key) {
                $new['cus_publish'] = esc_html__('Republication Date', 'aikon-post-update-scheduler');
            }
        }
        return $new;
    }

    /**
     * Populate the republication date column
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     * @return void
     */
    public function populate_republication_column(string $column, int $post_id): void
    {
        if ('cus_publish' === $column) {
            $post = get_post($post_id);
            if ($post->post_status === self::STATUS || get_post_meta($post_id, self::STATUS . '_original', true)) {
                $stamp = get_post_meta($post_id, self::STATUS . '_pubdate', true);
                if ($stamp) {
                    echo esc_html($this->format_pubdate($stamp));
                }
            }
        }
    }

    /**
     * Format a timestamp for display
     *
     * @param int $stamp Unix timestamp
     * @return string Formatted date and time
     */
    public function format_pubdate(int $stamp): string
    {
        $date = new DateTime('@' . $stamp);
        $date->setTimezone($this->get_wp_timezone());
        return $date->format(get_option('date_format') . ' ' . get_option('time_format'));
    }

    /**
     * Get WordPress timezone object
     *
     * @return DateTimeZone
     */
    public function get_wp_timezone(): DateTimeZone
    {
        $timezone_string = get_option('timezone_string');
        if (!empty($timezone_string)) {
            return new DateTimeZone($timezone_string);
        }

        $offset = get_option('gmt_offset', 0);
        $offset_string = sprintf('%+d', $offset);
        $offset_string = str_replace(['.25', '.5', '.75'], [':15', ':30', ':45'], $offset_string);
        
        return new DateTimeZone('UTC' . $offset_string);
    }

    /**
     * Prevent scheduled update posts from changing status
     *
     * @param string $new_status New post status
     * @param string $old_status Old post status
     * @param WP_Post $post Post object
     * @return void
     */
    public function prevent_status_change(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($new_status === $old_status && $new_status === self::STATUS) {
            return;
        }

        if ($old_status === self::STATUS && 'trash' !== $new_status) {
            // Prevent status change
            remove_action('save_post', [PublishingService::class, 'save_meta'], 10);

            $post->post_status = self::STATUS;
            wp_update_post($post, true);

            add_action('save_post', [PublishingService::class, 'save_meta'], 10, 2);
        } elseif ('trash' === $new_status) {
            // Clear scheduled event when post is trashed
            wp_clear_scheduled_hook('cus_publish_post', [$post->ID]);
        } elseif ('trash' === $old_status && $new_status === self::STATUS) {
            // Restore scheduled event when post is untrashed
            $scheduled_time = get_post_meta($post->ID, self::STATUS . '_pubdate', true);
            if ($scheduled_time) {
                wp_schedule_single_event($scheduled_time, 'cus_publish_post', [$post->ID]);
            }
        }
    }

    /**
     * Restrict access to scheduled content for non-administrators
     *
     * @return void
     */
    public function restrict_access_to_scheduled_content(): void
    {
        global $post;

        if (!$post instanceof WP_Post) {
            return;
        }

        if (!current_user_can('administrator')) {
            $scheduled_date = get_post_meta($post->ID, self::STATUS . '_pubdate', true);
            $original_post_id = get_post_meta($post->ID, self::STATUS . '_original', true);
            
            // Check if the post is a scheduled update and its publication time has passed
            if (!empty($scheduled_date) && $scheduled_date > current_time('timestamp') && empty($original_post_id)) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit();
            }
        }
    }
}