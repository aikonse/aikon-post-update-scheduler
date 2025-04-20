<?php
/**
 * Checkbox field for the options page.
 *
 * @package Aikon\PostUpdateScheduler
 * 
 * @var array $args
 * @var array $options
 * @var string $label
 * @var string $checked
 */
?>
<label for="<?php echo esc_attr($args['label_for']); ?>">
    <input id="<?php echo esc_attr($args['label_for']); ?>"
        type="checkbox"
        name="apus_options[<?php echo esc_attr($args['label_for']); ?>]"
        <?php echo $checked; // WPCS: XSS okay. 
        ?>>
    <?php echo esc_html($label); ?>
</label>