<?php
// Enqueue styles and scripts
function splitwise_theme_scripts() {
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js', array('jquery'), '4.5.0', true);
    wp_enqueue_style('style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'splitwise_theme_scripts');

// Redirect user after login
function custom_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('subscriber', $user->roles)) {
            return home_url('/dashboard');
        } else {
            return admin_url();
        }
    } else {
        return $redirect_to;
    }
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

// Redirect user after logout
function custom_logout_redirect() {
    wp_redirect(home_url('/login'));
    exit();
}
add_action('wp_logout', 'custom_logout_redirect');

// Restrict access to certain pages
function restrict_access_to_pages() {
    if (!is_user_logged_in() && (is_page('dashboard') || is_page('create-group') || is_page('my-groups') || is_page('group-details') || is_page('add-entry') || is_page('settle'))) {
        wp_redirect(home_url('/login'));
        exit();
    }
}
add_action('template_redirect', 'restrict_access_to_pages');

// Redirect after registration
function custom_registration_redirect() {
    return home_url('/login');
}
add_filter('um_registration_redirect_url__final', 'custom_registration_redirect');
?>
