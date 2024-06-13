<?php

// My Groups Shortcode
function gem_my_groups_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to view your groups.';
    }

    $user = wp_get_current_user();
    $email = $user->user_email;

    $response = wp_remote_post('http://fastapi/groups/get_groups', array(
        'method'    => 'POST',
        'body'      => json_encode(array('email' => $email)),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    } else {
        $groups = json_decode(wp_remote_retrieve_body($response), true);
        $output = '<div class="container">';
        $output .= '<div class="row">';
        foreach ($groups as $group) {
            $output .= '<div class="col-md-4 mb-3">';
            $output .= '<div class="card">';
            $output .= '<div class="card-body">';
            $output .= '<h5 class="card-title">';
            $output .= '<button class="btn btn-primary group-name-btn" onclick="window.location.href=\'';
            $output .= site_url('/group-details?group_name=' . urlencode($group));
            $output .= '\'">' . htmlspecialchars($group) . '</button>';
            $output .= '</h5>';
            $output .= '<button class="btn btn-danger delete-btn" onclick="deleteGroup(this, \'' . urlencode($group) . '\')" title="Delete Group"><i class="bi bi-trash"></i></button>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<script>';
        $output .= 'function deleteGroup(button, groupName) {';
        $output .= 'var $ = jQuery.noConflict();'; // Use jQuery in no-conflict mode
        $output .= '$.ajax({';
        $output .= 'url: "' . admin_url('admin-ajax.php') . '",';
        $output .= 'method: "POST",';
        $output .= 'data: {';
        $output .= 'action: "gem_delete_group",';
        $output .= 'group_name: groupName,';
        $output .= 'email: "' . $email . '"';
        $output .= '},';
        $output .= 'success: function(response) {';
        $output .= 'alert(response.data);';
        $output .= 'window.location.reload();'; // Reload the page after successful deletion
        $output .= '},';
        $output .= 'error: function(xhr, status, error) {';
        $output .= 'alert("Error deleting group: " + error);';
        $output .= '}';
        $output .= '});';
        $output .= '}';
        $output .= '</script>';
        return $output;
    }
}

?>
