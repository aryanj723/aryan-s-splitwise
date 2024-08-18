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
            $output .= site_url('/group-details?group_name=' . urlencode($group_raw)); // Keep the full group_raw value
            $output .= '\'">' . htmlspecialchars($group_name) . '</button>';
            $output .= '</h5>';
            $output .= '<p class="card-text" style="font-size: 0.75em;">Created: ' . htmlspecialchars($formatted_date) . '</p>';
            $output .= '<p class="balance-info" id="balance-info-' . htmlspecialchars($group_name) . '">Loading balance...</p>'; // Placeholder for balance info
            $output .= '<button class="btn btn-danger delete-btn" style="display:none;" id="exit-btn-' . htmlspecialchars($group_name) . '" onclick="deleteGroup(this, \'' . urlencode($group_raw) . '\')" title="Exit Group">';
            $output .= '<i class="bi bi-trash"></i>'; // Using Bootstrap trash icon
            $output .= '</button>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';

            // Add script to fetch group details asynchronously for each group
            $output .= '<script>
                        jQuery(document).ready(function($) {
                            $.ajax({
                                url: "' . admin_url('admin-ajax.php') . '",
                                method: "POST",
                                data: {
                                    action: "gem_get_group_details",
                                    group_name: "' . $group_raw . '", // Use group_raw to keep full identifier
                                    email: "' . $email . '"
                                },
                                success: function(response) {
                                    if (response.success) {
                                        var balances = response.data.balances;
                                        var localCurrency = response.data.local_currency;
                                        var netBalance = 0;

                                        balances.forEach(function(balance) {
                                            if (balance[0] === "' . $email . '") {
                                                netBalance -= balance[2]; // The user owes this amount
                                            } else if (balance[1] === "' . $email . '") {
                                                netBalance += balance[2]; // The user is owed this amount
                                            }
                                        });

                                        var balanceText = "";
                                        if (netBalance > 0) {
                                            balanceText = "You are owed " + localCurrency + " " + netBalance.toFixed(2);
                                        } else if (netBalance < 0) {
                                            balanceText = "You owe " + localCurrency + " " + Math.abs(netBalance).toFixed(2);
                                        } else {
                                            balanceText = "Settled";
                                            $("#exit-btn-' . htmlspecialchars($group_name) . '").show(); // Show exit button when settled
                                        }

                                        $("#balance-info-' . htmlspecialchars($group_name) . '").html(balanceText);
                                    } else {
                                        $("#balance-info-' . htmlspecialchars($group_name) . '").html("Error fetching balance");
                                    }
                                },
                                error: function() {
                                    $("#balance-info-' . htmlspecialchars($group_name) . '").html("Error fetching balance");
                                }
                            });
                        });
                        </script>';
        }
        $output .= '</div>';
        $output .= '</div>';

        // JavaScript function for deleting groups
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

// Add an AJAX handler for fetching group details
add_action('wp_ajax_gem_get_group_details', 'gem_get_group_details');
function gem_get_group_details() {
    // Use the raw group name (including $ and timestamp) as passed in the original request
    $group_name_raw = sanitize_text_field($_POST['group_name']);
    $email = sanitize_email($_POST['email']);

    // Call the API with the raw group name to get the group details
    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/get_group_details', array(
        'method'    => 'POST',
        'body'      => json_encode(array('name' => $group_name_raw, 'email' => $email)), // Use the raw group name here
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    // Handle API response
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Error fetching group details'));
    } else {
        $group_details = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($group_details);
    }

    wp_die(); // Required to terminate immediately and return a proper response
}


?>
