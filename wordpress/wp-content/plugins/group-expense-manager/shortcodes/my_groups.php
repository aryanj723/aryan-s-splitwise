<?php

// My Groups Shortcode
function gem_my_groups_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to view your groups.';
    }

    $user = wp_get_current_user();
    $email = $user->user_email;

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/get_groups', array(
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
        
        foreach ($groups as $group_raw) {
            // Ensure that $group_raw contains a '$' for splitting
            if (strpos($group_raw, '$') !== false) {
                list($group_name, $creation_timestamp) = explode('$', $group_raw);

                // Validate if $creation_timestamp exists and is in expected format
                if (isset($creation_timestamp) && strpos($creation_timestamp, 'T') !== false) {
                    $creation_date_parts = explode('T', $creation_timestamp);
                    $creation_date_str = $creation_date_parts[0];
                    $creation_time_str = explode('.', $creation_date_parts[1])[0]; // Removing microseconds

                    // Format the date and time for user-friendly display
                    try {
                        $datetime = new DateTime($creation_date_str . ' ' . $creation_time_str);
                        $formatted_date = $datetime->format('M j, Y \a\t g:i:s A \U\T\C');
                    } catch (Exception $e) {
                        $formatted_date = $group_raw; // Fallback to original data if parsing fails
                    }
                } else {
                    $formatted_date = 'Unknown creation date';
                }
            } else {
                $group_name = $group_raw;
                $formatted_date = 'Unknown creation date';
            }

            // Display the group name and creation date
            $output .= '<div class="col-md-4 mb-3">';
            $output .= '<div class="card">';
            $output .= '<div class="card-body text-center">';
            $output .= '<h5 class="card-title">';
            $output .= '<button class="btn btn-primary group-name-btn" onclick="window.location.href=\'';
            $output .= site_url('/group-details?group_name=' . urlencode($group_raw));
            $output .= '\'">' . htmlspecialchars($group_name) . '</button>';
            $output .= '</h5>';
            $output .= '<p class="card-text">Created: ' . htmlspecialchars($formatted_date) . '</p>';
            // Updated the delete button to use a Bootstrap trash icon
            $output .= '<button class="btn btn-danger delete-btn" onclick="deleteGroup(this, \'' . urlencode($group_raw) . '\')" title="Delete Group">';
            $output .= '<i class="bi bi-trash"></i>'; // Using Bootstrap trash icon
            $output .= '</button>';
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
