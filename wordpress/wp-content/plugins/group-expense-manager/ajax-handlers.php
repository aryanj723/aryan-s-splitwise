<?php

add_action('wp_ajax_gem_create_group', 'gem_create_group');
add_action('wp_ajax_nopriv_gem_create_group', 'gem_create_group');

function gem_create_group() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to create a group.');
        return;
    }

    $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
    $members = isset($_POST['members']) ? array_map('sanitize_email', $_POST['members']) : array();
    $creator_email = wp_get_current_user()->user_email;

    if (empty($group_name) || empty($members) || empty($creator_email)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    // Validation for valid email addresses
    foreach ($members as $member) {
        if (!is_email($member)) {
            wp_send_json_error('Invalid email address: ' . $member);
            return;
        }
    }

    $response = wp_remote_post('http://fastapi/groups/create', array(
        'method'    => 'POST',
        'body'      => json_encode(array('name' => $group_name, 'creator_email' => $creator_email, 'members' => $members)),
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

    if (empty($group_name) || empty($email) || empty($description) || empty($amount) || empty($paid_by) || empty($shares)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_post('http://fastapi/groups/add_expense', array(
        'method'    => 'POST',
        'body'      => json_encode(array('name' => $group_name, 'email' => $email, 'expense' => array('description' => $description, 'amount' => $amount, 'paid_by' => $paid_by, 'shares' => $shares, 'date' => date('Y-m-d\TH:i:s')))),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        wp_send_json_success('Expense added successfully.');
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
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $paid_by = isset($_POST['paid_by']) ? sanitize_email($_POST['paid_by']) : '';
    $paid_to = isset($_POST['paid_to']) ? sanitize_email($_POST['paid_to']) : '';

    if (empty($group_name) || empty($email) || empty($description) || empty($amount) || empty($paid_by) || empty($paid_to)) {
        wp_send_json_error('Please provide all required fields.');
        return;
    }

    $response = wp_remote_post('http://fastapi/groups/add_payment', array(
        'method'    => 'POST',
        'body'      => json_encode(array('name' => $group_name, 'email' => $email, 'payment' => array('description' => $description, 'amount' => $amount, 'paid_by' => $paid_by, 'paid_to' => $paid_to))),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error("Something went wrong: $error_message");
    } else {
        wp_send_json_success('Payment recorded successfully.');
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

    $response = wp_remote_request('http://fastapi/groups/delete', array(
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

        if ($response_code === 200) {
            wp_send_json_success('Success');
        } elseif ($response_code === 400) {
            wp_send_json_error('Failed to delete group. You have pending balances');
        } else {
            wp_send_json_error('Invalid response from API.');
        }
    }
}

?>
