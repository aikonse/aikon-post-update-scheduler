=== Aikon Post Update Scheduler ===
Contributors: infinitnet
Tags: cron, schedule, update, publication
Requires at least: 6.0
Tested up to: 6.7.1
Stable tag: 1.0.0
Requires PHP: 8.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Schedule post updates for any WordPress page or post type.

== Description ==

WordPress lacks the ability to schedule content updates. Keeping your posts and pages up to date manually can often be a waste of valuable time, especially when you know you'll need to update the same page again soon.

== Credits ==

Inspired by [content-update-scheduler](https://wordpress.org/plugins/content-update-scheduler/) and [tao-schedule-update](https://github.com/tao-software/tao-schedule-update) plugins.


== Extend ==

= Actions =

= Filter =

- `apus_excluded_post_types`, an array of excluded post types

= Extensions =

This plugin uses some predefined extensions that hooks into filters and actions. 
The purpose of these extensions are to hook into the plugins actions and filters to add functionality for Wordpress plugins.

Using the filter `apus_register_extensions`
you can add or remove extensions. An extension class needs to hava constructor that adds hooks and filters to it's own methods, 
but also a static method called is_active that returns a boolean

**Predefined extensions are:**
 - WooCommerce
 - Elementor
 - Oxygen
 - WPML

== Changelog ==

= 1.0 =
* First version.
