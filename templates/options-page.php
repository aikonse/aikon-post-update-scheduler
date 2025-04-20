<?php
/**
 * Options page for the plugin.
 *
 * @package Aikon\PostUpdateScheduler
 */

settings_errors('apus_messages', true);
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php settings_fields('apus_schedule_update'); ?>

        <?php do_settings_sections('apus'); ?>

        <?php submit_button(); ?>
    </form>
</div>