<?php

// Hook to add shortcodes
add_action('init', 'gem_register_shortcodes');

function gem_register_shortcodes() {
    add_shortcode('gem_dashboard', 'gem_dashboard_shortcode');
    add_shortcode('gem_create_group', 'gem_create_group_shortcode');
    add_shortcode('gem_my_groups', 'gem_my_groups_shortcode');
    add_shortcode('gem_group_details', 'gem_group_details_shortcode');
}

// Include individual shortcode files
include_once 'dashboard.php';
include_once 'create_group.php';
include_once 'my_groups.php';
include_once 'group_details.php';

?>
