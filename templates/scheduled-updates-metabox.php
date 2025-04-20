<?php

/**
 * Template for the scheduled updates metabox.
 *
 * @package Aikon\PostUpdateScheduler
 * 
 * @var string $metaname
 * @var mixed $stamp
 * @var string $date
 * @var string $time
 * @var string $day
 * @var string $month
 * @var string $year
 * @var array $months
 * @var WP_Post $post
 * @var string $nonceField
 * @var Aikon\PostUpdateScheduler\Options $options
 */

use Aikon\PostUpdateScheduler\Services\PostService;

wp_nonce_field(basename(__FILE__), $nonceField);
?>
<div class="block-editor-publish-date-time-picker">
    <div class="components-datetime__date">
        <input 
            type="hidden" 
            id="apos-datetime-picker-field" 
            data-label="<?php _e('Scheduled date:', 'aikon-post-update-scheduler'); ?>" 
            name="<?php echo esc_attr($metaname); ?>" 
            value="<?php echo esc_attr($stamp); ?>" 
        />
        <div id="apos-datetime-picker" class="component"></div>
    </div>
    <p>
        <?php esc_html_e('Please enter Time in the site\'s local timezone', 'aikon-post-update-scheduler'); ?>
    </p>
    <p>
    <div id="pastmsg" style="color:red; display:none;">
        <?php
        echo esc_html__('The release date is in the past.', 'aikon-post-update-scheduler');
        if ($options->get('apus_nodate') === 'nothing') {
            echo esc_html__('This post will not be published.', 'aikon-post-update-scheduler');
        } else {
            echo esc_html__('This post will be published 5 minutes from now.', 'aikon-post-update-scheduler');
        }
        ?>
    </div>
    </p>
    <div class="misc-pub-section">
        <label>
            <input 
                type="checkbox"
                name="<?php echo esc_attr(PostService::STATUS); ?>_keep_dates"
                id="<?php echo esc_attr(PostService::STATUS); ?>_keep_dates"
                <?php checked(get_post_meta($post->ID, PostService::STATUS . '_keep_dates', true), 'yes'); ?>
            >
            <?php esc_html_e('Keep original publication date', 'aikon-post-update-scheduler'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('If checked, the original publication date will be preserved when this update is published.', 'aikon-post-update-scheduler'); ?>
        </p>
    </div>
</div>
<script type="text/javascript">
    const dateField = document.getElementById('apos-datetime-picker-field');
    const dateHasPassedMessage = document.getElementById('pastmsg');

    dateField.addEventListener('change', function(e) {
        if (!this.value || isNaN(Date.parse(this.value))) {
            return;
        }

        // Get current date in local timezone
        const now = new Date();

        // Parse the selected date (ISO format from the DateTimePicker component)
        const selectedDate = new Date(this.value);

        if (selectedDate <= now) {
            dateHasPassedMessage.style.display = 'block';
        } else {
            dateHasPassedMessage.style.display = 'none';
        }
    });
</script>