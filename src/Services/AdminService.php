<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler\Services;

use Aikon\PostUpdateScheduler\Options;
use WP_Post;

/**
 * Handles admin actions for the Content Update Scheduler
 */
class AdminService
{
    /**
     * @var Options
     */
    private Options $options;

    /**
     * @var PublishingService
     */
    private PublishingService $publishing_service;

    /**
     * @param Options $options
     * @param PublishingService $publishing_service
     */
    public function __construct(Options $options, PublishingService $publishing_service)
    {
        $this->options = $options;
        $this->publishing_service = $publishing_service;
    }

    /**
     * Handles the "Copy to Publish" admin action
     *
     * @return void
     */
    public function handle_copy_to_publish(): void
    {
        if (!isset($_REQUEST['n'], $_REQUEST['post']) || !wp_verify_nonce(sanitize_key($_REQUEST['n']), 'workflow_copy_to_publish' . absint($_REQUEST['post']))) {
            return;
        }

        $post = get_post(absint(wp_unslash($_REQUEST['post'])));
        $publishing_id = $this->publishing_service->create_publishing_post($post);
        
        if ($publishing_id && !is_wp_error($publishing_id)) {
            wp_redirect(admin_url('post.php?action=edit&post=' . $publishing_id));
            exit;
        } else {
            // Error message
            $html = sprintf(
                __('Could not schedule %1$s %2$s', 'aikon-post-update-scheduler'),
                $post->post_type,
                '<i>' . htmlspecialchars($post->post_title) . '</i>'
            );
            $html .= '<br><br>';
            $html .= '<a href="' . esc_attr(admin_url('edit.php?post_type=' . $post->post_type)) . '">' . __('Back') . '</a>';
            wp_die($html);
        }
    }

    /**
     * Handles the "Publish Now" admin action
     *
     * @return void
     */
    public function handle_publish_now(): void
    {
        if (!isset($_REQUEST['n'], $_REQUEST['post']) || !wp_verify_nonce(sanitize_key($_REQUEST['n']), 'workflow_publish_now' . absint($_REQUEST['post']))) {
            return;
        }

        $post = get_post(absint(wp_unslash($_REQUEST['post'])));
        $this->publishing_service->publish_post($post->ID);
        
        wp_redirect(admin_url('edit.php?post_type=' . $post->post_type));
        exit;
    }

    /**
     * Handles AJAX request to load publication date
     *
     * @return void
     */
    public function load_pubdate(): void
    {
        if (isset($_REQUEST['postid'])) {
            $stamp = get_post_meta(absint(wp_unslash($_REQUEST['postid'])), PostService::STATUS . '_pubdate', true);
            if ($stamp) {
                $post_service = new PostService($this->options);
                $str = '<div style="margin-left:20px">';
                $str .= $post_service->format_pubdate($stamp);
                $str .= '</div>';
                die($str);
            }
        }
    }

    /**
     * Add row actions to the post list table
     *
     * @param array $actions Existing actions
     * @param WP_Post $post Current post
     * @return array Updated actions
     */
    public function add_row_actions(array $actions, WP_Post $post): array
    {
        $copy_nonce = wp_create_nonce('workflow_copy_to_publish' . $post->ID);
        $copy_url = admin_url("admin.php?action=workflow_copy_to_publish&post={$post->ID}&n={$copy_nonce}");
        
        if ($post->post_status === PostService::STATUS) {
            $publish_nonce = wp_create_nonce('workflow_publish_now' . $post->ID);
            $publish_url = admin_url("admin.php?action=workflow_publish_now&post={$post->ID}&n={$publish_nonce}");
            
            $actions['publish_now'] = '<a href="' . esc_url($publish_url) . '">' . __('Publish Now', 'aikon-post-update-scheduler') . '</a>';
            
            if ($this->options->get('tsu_recursive')) {
                $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . __('Schedule recursive', 'aikon-post-update-scheduler') . '</a>';
            } else {
                $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . __('Scheduled Content Update', 'aikon-post-update-scheduler') . '</a>';
            }
        } elseif ('trash' !== $post->post_status) {
            $actions['copy_to_publish'] = '<a href="' . esc_url($copy_url) . '">' . __('Scheduled Content Update', 'aikon-post-update-scheduler') . '</a>';
        }

        return $actions;
    }

    /**
     * Add post states to identify scheduled content updates
     *
     * @param array $states Current post states
     * @param WP_Post $post Current post
     * @return array Updated post states
     */
    public function add_post_states(array $states, WP_Post $post): array
    {
        if (!$post instanceof WP_Post) {
            return $states;
        }

        // @var string $query_post_status
        $query_post_status = get_query_var('post_status');

        // @var \WP_Post_Type[] $post_types
        $post_types = get_post_types(['public' => true], 'objects');

        // Check if the post type is supported
        if (!in_array( $post->post_type, array_keys($post_types))) {
            return $states;
        }

        // @var \WP_Post_Type $type
        $type = $post_types[$post->post_type];

        if ($query_post_status !== PostService::LABEL && $post->post_status === PostService::STATUS) {
            $states = [PostService::LABEL];

            if (!$type->hierarchical) {
                $orig_id = get_post_meta($post->ID, PostService::STATUS . '_original', true);
                $orig = get_post($orig_id);
                if ($orig) {
                    $states[] = __('Original', 'aikon-post-update-scheduler') . ': ' . $orig->post_title;
                }
            }
        }

        return $states;
    }

    /**
     * Add the custom post status to parent dropdown
     *
     * @param array $args Current arguments
     * @return array Updated arguments
     */
    public function add_to_parent_dropdown(array $args): array
    {
        if (!isset($args['post_status']) || !is_array($args['post_status'])) {
            $args['post_status'] = ['publish'];
        }

        $args['post_status'][] = PostService::STATUS;
        return $args;
    }
}