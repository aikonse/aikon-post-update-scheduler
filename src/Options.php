<?php

declare(strict_types=1);

namespace Aikon\PostUpdateScheduler;

final class Options
{
    /**
     * Holds all the options
     *
     * @var array
     */
    private array $options = [];

    /**
     * Plugin label for admin pages
     * 
     * @var string
     */
    private string $label = 'Aikon Post Update Scheduler';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_options();
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting('apus_schedule_update', 'apus_options', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default' => [],
        ]);

        add_settings_section(
            'apus_section',
            '',
            'intval',
            'apus'
        );

        add_settings_field(
            'apus_field_nodate',
            __('No Date Set', 'aikon-post-update-scheduler'),
            [$this, 'field_nodate_cb'],
            'apus',
            'apus_section',
            [
                'label_for' => 'apus_nodate',
                'class'     => 'apus_row',
            ]
        );

        add_settings_field(
            'apus_field_visible',
            __('Post Visibility', 'aikon-post-update-scheduler'),
            [$this, 'field_visible_cb'],
            'apus',
            'apus_section',
            [
                'label_for' => 'apus_visible',
                'class'     => 'apus_row',
            ]
        );

        add_settings_field(
            'apus_field_recursive',
            __('Recursive Scheduling', 'aikon-post-update-scheduler'),
            [$this, 'field_recursive_cb'],
            'apus',
            'apus_section',
            [
                'label_for' => 'apus_recursive',
                'class'     => 'apus_row',
            ]
        );
    }

    /**
     * Sanitizes the options array.
     *
     * @param array $options The options array to sanitize.
     *
     * @return array The sanitized options array.
     */
    public function sanitize_options($options): array
    {
        $sanitized_options = [];

        foreach ($options as $key => $value) {
            if ($key === 'apus_recursive' || $key === 'apus_visible') {
                $sanitized_options[$key] = $value === 'on' ? 'on' : '';
            } else {
                $sanitized_options[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized_options;
    }

    /**
     * Loads the saved options from the database
     *
     * @return void
     */
    public function load_options(): void
    {
        $this->options = get_option('apus_options', []);
    }

    /**
     * Get a option value
     *
     * @param string $optname name of the option.
     *
     * @return mixed Value of the requested option
     */
    public function get(string $optname)
    {
        if (isset($this->options[$optname])) {
            return $this->options[$optname];
        }
        return null;
    }

    /**
     * Set plugin label
     * 
     * @param string $label
     * @return void
     */
    public function set_label(string $label): void 
    {
        $this->label = $label;
    }

    /**
     * Get plugin label
     * 
     * @return string
     */
    public function get_label(): string
    {
        return $this->label;
    }

    /**
     * Register options page
     *
     * @return void
     */
    public function register_options_page(): void
    {
        add_options_page(
            $this->label,
            $this->label,
            'manage_options',
            'apus',
            [$this, 'render_options_page']
        );
    }

    /**
     * Renders the settings field for `nodate`
     *
     * @param array $args array of arguments, passed by do_settings_fields.
     *
     * @return void
     */
    public function field_nodate_cb(array $args): void
    {
        $options = get_option('apus_options');

        view('options-nodate-select', [
            'options' => $options,
            'args'    => $args,
        ]);
    }

    /**
     * Renders a checkbox field.
     *
     * @param array $args array of arguments, passed by do_settings_fields.
     * @param string $label The label for the checkbox.
     *
     * @return void
     */
    private function render_checkbox_field(array $args, string $label): void
    {
        $options = get_option('apus_options');
        $checked = isset($options[$args['label_for']]) ? 'checked="checked"' : '';

        view('options-checkbox-field', [
            'args'    => $args,
            'label'   => $label,
            'checked' => $checked,
            'options' => $options,
        ]);
    }

    /**
     * Renders the settings field for `visible`
     *
     * @param array $args array of arguments, passed by do_settings_fields.
     *
     * @return void
     */
    public function field_visible_cb(array $args): void
    {
        $this->render_checkbox_field(
            $args, 
            __('Scheduled posts are visible for anonymous users in the frontend', 'apus-scheduleupdate-td')
        );
    }

    /**
     * Renders the settings field for `recursive`
     *
     * @param array $args array of arguments, passed by do_settings_fields.
     *
     * @return void
     */
    public function field_recursive_cb(array $args): void
    {
        $this->render_checkbox_field(
            $args, 
            __('Allow recursive scheduling', 'apus-scheduleupdate-td')
        );
    }

    /**
     * Renders the options page
     *
     * @return void
     */
    public function render_options_page(): void
    {
        // check user capabilities.
        if (!current_user_can('manage_options')) {
            return;
        }

        view('options-page');
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    public function init_hooks(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_options_page']);
    }
}
