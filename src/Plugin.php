<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler;

use Aikon\PostUpdateScheduler\Extensions\Elementor;
use Aikon\PostUpdateScheduler\Extensions\Oxygen;
use Aikon\PostUpdateScheduler\Extensions\WooCommerce;
use Aikon\PostUpdateScheduler\Extensions\WPML;
use Aikon\PostUpdateScheduler\Services\AdminService;
use Aikon\PostUpdateScheduler\Services\CronService;
use Aikon\PostUpdateScheduler\Services\MetaBoxService;
use Aikon\PostUpdateScheduler\Services\PostService;
use Aikon\PostUpdateScheduler\Services\PublishingService;

class Plugin
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
     * @var PublishingService
     */
    private PublishingService $publishing_service;

    /**
     * @var AdminService
     */
    private AdminService $admin_service;

    /**
     * @var MetaBoxService
     */
    private MetaBoxService $metabox_service;

    /**
     * @var CronService
     */
    private CronService $cron_service;

    /**
     * @var array<Extension>
     */
    private array $extensions = [];

    /**
     * Plugin constructor
     */
    public function __construct()
    {
        $this->options = new Options();
        $this->post_service = new PostService($this->options);
        $this->publishing_service = new PublishingService($this->options, $this->post_service);
        $this->admin_service = new AdminService($this->options, $this->publishing_service);
        $this->metabox_service = new MetaBoxService($this->options);
        $this->cron_service = new CronService($this->publishing_service);

        // Load extensions
        $extensions = [
            Elementor::class,
            Oxygen::class,
            WPML::class,
            WooCommerce::class,
        ];

        $extensions = apply_filters('apus_register_extensions', $extensions);

        if (!is_array($extensions)) {
            $extensions = [];
        }

        //$this->register_extensions($extensions);
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init(): void
    {
        // Load translations
        load_plugin_textdomain('aikon-post-update-scheduler', false, dirname(plugin_basename(__FILE__)) . '/language/');

        // Register custom status
        add_action('init', [$this->post_service, 'register_post_status']);
        add_action('init', [$this->post_service, 'register_post_type_support']);

        // Setup meta boxes
        add_action('add_meta_boxes', [$this->metabox_service, 'add_meta_boxes_page'], 10, 2);

        // Handle scheduled updates
        add_action('save_post', [$this->publishing_service, 'save_meta'], 10, 2);
        add_action('transition_post_status', [$this->post_service, 'prevent_status_change'], 10, 3);

        // Setup admin actions
        add_action('admin_action_workflow_copy_to_publish', [$this->admin_service, 'handle_copy_to_publish']);
        add_action('admin_action_workflow_publish_now', [$this->admin_service, 'handle_publish_now']);
        add_action('wp_ajax_load_pubdate', [$this->admin_service, 'load_pubdate']);

        // Register row actions
        add_filter('post_row_actions', [$this->admin_service, 'add_row_actions'], 10, 2);
        add_filter('page_row_actions', [$this->admin_service, 'add_row_actions'], 10, 2);

        // Add post states
        add_filter('display_post_states', [$this->admin_service, 'add_post_states'], 10, 2);

        // Add dropdown support
        add_filter('wp_dropdown_pages_args', [$this->admin_service, 'add_to_parent_dropdown']);

        // Setup cron
        add_filter('cron_schedules', [$this->cron_service, 'register_cron_interval']);
        add_action('init', [$this->cron_service, 'setup_cron']);
        add_action('apus_check_overdue_posts', [$this->cron_service, 'check_overdue_posts']);
        add_action('apus_publish_post', [$this->cron_service, 'publish_post']);

        // Frontend access restriction
        add_action('template_redirect', [$this->post_service, 'restrict_access_to_scheduled_content']);
    }

    /**
     * Register extensions
     * 
     * @param array $extensions Array of extension class names
     * @return void
     */
    public function register_extensions(array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (class_exists($extension)) {
                $this->extensions[] = new $extension($this->options);
            }
        }
    }

    /**
     * Static bootstrap method
     *
     * @return Plugin
     */
    public static function bootstrap(): Plugin
    {
        $instance = new self();
        $instance->init();
        return $instance;
    }
}