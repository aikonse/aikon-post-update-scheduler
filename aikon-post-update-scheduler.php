<?php

/**
 * Content Update Scheduler
 *
 * Plugin Name: Aikon Post Update Scheduler
 * Description: Schedule content updates for any page or post type.
 * Author: Aikon
 * Author URI: https://aikon.se/
 * Version: 1.0.0
 * License: MIT
 * Text Domain: aikon-post-update-scheduler
 *
 * @package apus
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check minimum PHP version
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function () {
        ?>
		<div class="notice notice-error">
			<p><?php _e('Aikon Post Update Scheduler requires PHP 8.0 or higher.', 'aikon-role-manager');?></p>
		</div>
		<?php
    });
    exit;
}

// Define plugin constants
define('APUS_VERSION', '2.3.5');
define('APUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APUS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap the plugin
use Aikon\PostUpdateScheduler\Plugin;

// Initialize the plugin
Plugin::bootstrap();


/**
 * Handle plugin deactivation
 */
function apus_deactivation() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('apus_check_overdue_posts');
    
    $cron = _get_cron_array();
    if (!empty($cron)) {
        foreach ($cron as $timestamp => $hooks) {
            if (isset($hooks['apus_publish_post'])) {
                foreach ($hooks['apus_publish_post'] as $key => $event) {
                    wp_unschedule_event($timestamp, 'apus_publish_post', $event['args']);
                }
            }
        }
    }
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'apus_deactivation');
