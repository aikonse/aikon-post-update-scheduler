<?php
/**
 * @var array $options
 * @var array $args
 */
?>
<select id="<?php echo esc_attr($args['label_for']); ?>"
    name="apus_options[<?php echo esc_attr($args['label_for']); ?>]">
    <option value="publish" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'publish', false)) : (''); ?>>
        <?php echo esc_html(__('Publish right away', 'aikon-post-update-scheduler')); ?>
    </option>
    <option value="nothing" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'nothing', false)) : (''); ?>>
        <?php echo esc_html(__('Don\'t publish', 'aikon-post-update-scheduler')); ?>
    </option>
</select>
<p class="description">
    <?php echo esc_html(__('What should happen to a post if it is saved with no date set?', 'aikon-post-update-scheduler')); ?>
</p>