<?php

add_action('wp_ajax_gem_create_group', 'gem_create_group');
add_action('wp_ajax_nopriv_gem_create_group', 'gem_create_group');

function gem_create_group() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to create a group.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $members = isset($_POST['members']) ? array_map('sanitize_email', array_map('strtolower', $_POST['members'])) : array();
    $local_currency = isset($_POST['local_currency']) ? sanitize_text_field($_POST['local_currency']) : '';
    $creator_email = strtolower(wp_get_current_user()->user_email);

    // Validate group name
    if (strlen($group_name) > 20 || strpos($group_name, '$') !== false) {
        wp_send_json_error('Group name should be less than 20 characters and should not contain the $ sign.');
        return;
    }

    // Validate member email addresses
    foreach ($members as $member) {
        if (strlen($member) > 70 || !is_email($member)) {
            wp_send_json_error('Invalid email address: ' . $member . ' (should be a valid email and less than 70 characters).');
            return;
        }
    }

    // Validate currency
    if (strlen($local_currency) > 15) {
        wp_send_json_error('Currency should be less than 15 characters.');
        return;
    }

    // Append timestamp to group name
    $timestamp = microtime(true);
    $timestamp_formatted = gmdate("Y-m-d\TH:i:s.", floor($timestamp)) . sprintf('%02d', ($timestamp - floor($timestamp)) * 100);
    $group_name .= '$' . $timestamp_formatted;

    if (empty($group_name) || empty($members) || empty($local_currency) || empty($creator_email)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/create', array(
        'method'    => 'POST',
        'body'      => json_encode(array(
            'name' => $group_name,
            'creator_email' => $creator_email,
            'members' => $members,
            'local_currency' => $local_currency
        )),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status_code == 200) {
            wp_send_json_success($data['message']);
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed! Try with a different group name.');
        }
    }
}

// Handle AJAX requests for adding expenses
add_action('wp_ajax_gem_add_expense', 'gem_add_expense');
add_action('wp_ajax_nopriv_gem_add_expense', 'gem_add_expense');

function gem_add_expense() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to add an expense.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paid_by = isset($_POST['paid_by']) ? sanitize_email($_POST['paid_by']) : '';
    $shares = isset($_POST['shares']) ? array_map('floatval', $_POST['shares']) : array();
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';

    if (empty($group_name) || empty($email) || empty($description) || empty($amount) || empty($paid_by) || empty($shares) || empty($currency)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/add_expense', array(
        'method'    => 'POST',
        'body'      => json_encode(array(
            'name' => $group_name,
            'email' => $email,
            'expense' => array(
                'description' => $description,
                'amount' => $amount,
                'paid_by' => $paid_by,
                'shares' => $shares,
                'currency' => $currency
            )
        )),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Error adding expense: $error_message");
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status_code == 200) {
            delete_transient($group_name);
            wp_send_json_success('Expense added successfully.');
        } elseif (isset($data['message'])) { 
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed to add expense.');
        }
    }
}

// Handle AJAX requests for adding payments
add_action('wp_ajax_gem_add_payment', 'gem_add_payment');
add_action('wp_ajax_nopriv_gem_add_payment', 'gem_add_payment');

function gem_add_payment() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to record a payment.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $description = '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paid_by = isset($_POST['paid_by']) ? sanitize_email($_POST['paid_by']) : '';
    $paid_to = isset($_POST['paid_to']) ? sanitize_email($_POST['paid_to']) : '';
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';

    if (empty($group_name) || empty($email) || empty($amount) || empty($paid_by) || empty($paid_to) || empty($currency)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/add_payment', array(
        'method'    => 'POST',
        'body'      => json_encode(array(
            'name' => $group_name,
            'email' => $email,
            'payment' => array(
                'description' => $description,
                'amount' => $amount,
                'paid_by' => $paid_by,
                'paid_to' => $paid_to,
                'currency' => $currency
            )
        )),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status_code == 200) {
            delete_transient($group_name);
            wp_send_json_success('Payment recorded successfully.');
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed to record payment.');
        }
    }
}

// Handle AJAX requests for adding users
add_action('wp_ajax_gem_add_user', 'gem_add_user');
add_action('wp_ajax_nopriv_gem_add_user', 'gem_add_user');

function gem_add_user() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to add a user.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $member_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $new_member_email = isset($_POST['new_member_email']) ? sanitize_email($_POST['new_member_email']) : '';

    if (empty($group_name) || empty($member_email) || empty($new_member_email)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    if (strlen($new_member_email) > 70 || !is_email($new_member_email)) {
        wp_send_json_error('Invalid email address: ' . $new_member_email . ' (should be a valid email and less than 70 characters).');
        return;
    }

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/add_user', array(
        'method'    => 'POST',
        'body'      => json_encode(array(
            'group_name' => $group_name,
            'member_email' => $member_email,
            'new_member_email' => $new_member_email
        )),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status_code == 200) {
            delete_transient($group_name);
            wp_send_json_success('User added successfully.');
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed to add user.');
        }
    }
}

// Handle AJAX requests for adding currencies
add_action('wp_ajax_gem_add_currency', 'gem_add_currency');
add_action('wp_ajax_nopriv_gem_add_currency', 'gem_add_currency');

function gem_add_currency() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to add a currency.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : '';
    $conversion_rate = isset($_POST['conversion_rate']) ? floatval($_POST['conversion_rate']) : 0;

    if (empty($group_name) || empty($email) || empty($currency) || empty($conversion_rate)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    if (strlen($currency) > 15) {
        wp_send_json_error('Currency should be less than 15 characters.');
        return;
    }

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/add_currency', array(
        'method'    => 'POST',
        'body'      => json_encode(array(
            'group_name' => $group_name,
            'email' => $email,
            'currency' => $currency,
            'conversion_rate' => $conversion_rate
        )),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ($status_code == 200) {
            delete_transient($group_name);
            wp_send_json_success('Currency added successfully.');
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed to add currency.');
        }
    }
}

// Handle AJAX requests for deleting a group
add_action('wp_ajax_gem_delete_group', 'gem_delete_group');
function gem_delete_group() {
    $group_name = isset($_POST['group_name']) ? urldecode($_POST['group_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (empty($group_name) || empty($email)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_request('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/delete', array(
        'method'    => 'DELETE',
        'body'      => json_encode(array('name' => $group_name, 'email' => $email)),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code === 200) {
            delete_transient($group_name);
            wp_send_json_success('Success');
        } elseif ($response_code === 400) {
            // Handle specific error for pending balances
            wp_send_json_error('Unable to leave group, you have pending balances.');
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Invalid response from API.');
        }
    }
}

// Handle AJAX requests for removing an expense
add_action('wp_ajax_gem_remove_expense', 'gem_remove_expense');
add_action('wp_ajax_nopriv_gem_remove_expense', 'gem_remove_expense');

function gem_remove_expense() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to remove an expense.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $expense_datetime = isset($_POST['expense_datetime']) ? sanitize_text_field($_POST['expense_datetime']) : '';

    if (empty($group_name) || empty($email) || empty($expense_datetime)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $payload = array(
        'group_name' => $group_name,
        'member_email' => $email,
        'expense_datetime' => $expense_datetime
    );

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/remove_expense', array(
        'method'    => 'POST',
        'body'      => json_encode($payload),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code == 200) {
            delete_transient($group_name);
            wp_send_json_success('Expense removed successfully.');
        } elseif (isset($data['message'])) { // Check if the "message" key exists
            wp_send_json_error($data['message']);
        } else {
            wp_send_json_error('Failed to remove expense.');
        }
    }
}

add_action('wp_ajax_gem_search_members', 'gem_search_members');
add_action('wp_ajax_nopriv_gem_search_members', 'gem_search_members');

function gem_search_members() {
    // Get the search term from the request and sanitize it
    $search_term = sanitize_text_field($_POST['search_term']);
    
    // Remove spaces from the search term
    $search_term = str_replace(' ', '', $search_term);

    // If search term is empty, return empty results
    if (empty($search_term)) {
        wp_send_json_success([]);
        return;
    }

    // Search for users by display_name and user_email only if they contain the search term (either of them)
    $args = array(
        'search'         => '*' . esc_attr($search_term) . '*',
        'search_columns' => array('user_email', 'display_name') // Search in display_name and email
    );

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();

    // Prepare results
    $results = array();
    foreach ($users as $user) {
        // Get the display name, or use user_login if display_name is empty
        $display_name = !empty($user->display_name) ? $user->display_name : $user->user_login;

        // Remove spaces from display_name and user_email for comparison
        $normalized_display_name = str_replace(' ', '', $display_name);
        $normalized_email = str_replace(' ', '', $user->user_email);

        // Check if the search term matches either the normalized display_name or the email
        if (stripos($normalized_display_name, $search_term) !== false || stripos($normalized_email, $search_term) !== false) {
            $results[] = array(
                'name'  => $display_name,
                'email' => $user->user_email
            );
        }
    }

    // Send back the result as JSON
    wp_send_json_success($results);
}

?>
