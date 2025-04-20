<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Services;

use Aikon\PostUpdateScheduler\Options;
use DateTime;
use DateTimeZone;

/**
 * Handles cron jobs for the Content Update Scheduler
 */
class CronService
{
    /**
     * @var PublishingService
     */
    private PublishingService $publishing_service;

    /**
     * @param PublishingService $publishing_service
     */
    public function __construct(PublishingService $publishing_service)
    {
        $this->publishing_service = $publishing_service;
    }

    /**
     * Set up the cron job to check for overdue posts
     *
     * @return void
     */
    public function setup_cron(): void
    {
        if (!wp_next_scheduled('cus_check_overdue_posts')) {
            wp_schedule_event(time(), 'five_minutes', 'cus_check_overdue_posts');
        }
    }

    /**
     * Register the five minutes cron interval
     *
     * @param array $schedules Existing schedules
     * @return array Updated schedules
     */
    public function register_cron_interval(array $schedules): array
    {
        $schedules['five_minutes'] = [
            'interval' => 300, // 5 minutes in seconds
            'display'  => __('Every Five Minutes', 'aikon-post-update-scheduler')
        ];
        return $schedules;
    }

    /**
     * Publish a post via cron
     *
     * @param int $post_id Post ID to publish
     * @return void
     */
    public function publish_post(int $post_id): void
    {
        // Disable kses filtering during post publishing to prevent content stripping
        kses_remove_filters();
        $result = $this->publishing_service->publish_post($post_id);
        kses_init_filters();
        
        if (is_wp_error($result)) {
            error_log(sprintf(
                'Content Update Scheduler: Error publishing post ID %d: %s',
                $post_id,
                $result->get_error_message()
            ));
        }
    }

    /**
     * Check for any overdue posts that need to be published
     *
     * @return void
     */
    public function check_overdue_posts(): void
    {
        global $wpdb;

        // Get the WordPress timezone
        $wp_timezone = wp_timezone();
        $current_time = new DateTime('now', $wp_timezone);
        
        // Get all posts with a publication date
        $overdue_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = %s",
                PostService::STATUS . '_pubdate'
            )
        );

        foreach ($overdue_posts as $post) {
            $scheduled_time = new DateTime('@' . $post->meta_value);
            $scheduled_time->setTimezone($wp_timezone);
            
            // If scheduled time is in the past, publish the post
            if ($scheduled_time <= $current_time) {
                $this->publish_post((int)$post->post_id);
            }
        }
    }

    /**
     * Check and log scheduled events for debugging purposes
     *
     * @return void
     */
    public function debug_scheduled_events(): void
    {
        $cron = _get_cron_array();
        $found = false;
        
        foreach ($cron as $timestamp => $cronhooks) {
            if (isset($cronhooks['cus_publish_post'])) {
                foreach ($cronhooks['cus_publish_post'] as $hash => $event) {
                    $found = true;
                    error_log(sprintf(
                        'Content Update Scheduler: Found scheduled publish event at %s for post ID: %d',
                        date('Y-m-d H:i:s', $timestamp),
                        $event['args'][0]
                    ));
                }
            }
        }
        
        if (!$found) {
            error_log('Content Update Scheduler: No scheduled publish events found');
        }
    }
}