<?php
/*
Plugin Name: Group Expense Manager
Description: Manages groups and expenses, integrates a backend server
Version: 4.0
Author: Aryan
*/

define('GEM_API_BASE_URL', 'https://aryan-s-splitwise-fastapi-latest.onrender.com');

// Include other files
include_once 'shortcodes/shortcodes.php';
include_once 'ajax-handlers.php';
include_once 'helpers.php';

// Enqueue styles and scripts
function gem_enqueue_scripts() {
    $css_version = ceil(time() / 7200);
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
    wp_enqueue_style('custom-css', plugins_url('group-expense-manager.css', __FILE__), array(), $css_version); // Cache-busting version
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', array('jquery'), null, true);
    wp_enqueue_script('lazyload-js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyload/1.9.1/jquery.lazyload.min.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'gem_enqueue_scripts');
?>
