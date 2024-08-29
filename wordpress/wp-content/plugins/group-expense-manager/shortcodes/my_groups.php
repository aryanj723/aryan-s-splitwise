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
        $output = '<div class="container mt-4">';  // Bootstrap container for mobile responsiveness
        
        // Check if there are no groups found
        if (empty($groups)) {
            $output .= '<p>No groups found for you... go ahead and create one with the button above. Don\'t worry, everything is free (and will be) on this app 😊.</p>';
        } else {
            $output .= '<div class="row">';  // Start Bootstrap row
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

                // Group Card Layout for Bootstrap
                $output .= '<div class="col-md-4 col-sm-6 mb-4">';  // Bootstrap column (4 on desktop, 6 on mobile)
                $output .= '<div class="card h-100">';  // Full-height card
                $output .= '<div class="card-body text-center">';
                $output .= '<h5 class="card-title">' . htmlspecialchars($group_name) . '</h5>';
                $output .= '<p class="card-text" style="font-size: 0.75em;">Created: ' . htmlspecialchars($formatted_date) . '</p>';
                $output .= '<div class="balance-info" id="balance-info-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $group_name) . '"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>'; // Spinner for loading balance
                $output .= '<a href="' . site_url('/group-details?group_name=' . urlencode($group_raw)) . '" class="btn btn-primary w-100 mt-2">View Group</a>';
                $output .= '<button class="btn btn-danger w-100 mt-2 delete-btn" style="display:none;" id="exit-btn-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $group_name) . '" onclick="deleteGroup(this, \'' . urlencode($group_raw) . '\')" title="Exit Group">Exit Group</button>';
                $output .= '</div>';
                $output .= '</div>';
                $output .= '</div>';
                
                // Add script to fetch group details asynchronously for each group
                $output .= '<script>
                jQuery(document).ready(function($) {
                    var sanitizedGroupName = "' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $group_name) . '"; // Sanitize only for HTML ID
                
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        method: "POST",
                        data: {
                            action: "gem_get_group_details",
                            group_name: "' . $group_raw . '", // Use the raw group name (including $ and spaces) for the API call
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
                                    $("#exit-btn-" + sanitizedGroupName).show(); // Show exit button when settled
                                }
                
                                $("#balance-info-" + sanitizedGroupName).html(balanceText);
                            } else {
                                $("#balance-info-" + sanitizedGroupName).html("Error fetching balance");
                            }
                        },
                        error: function(xhr, status, error) {
                            $("#balance-info-" + sanitizedGroupName).html("Error fetching balance");
                        }
                    });
                });
                </script>';
            }
            $output .= '</div>';  // Close Bootstrap row
        }
        
        $output .= '</div>';  // Close container

        // JavaScript function for deleting groups
        $output .= '<script>';
        $output .= 'function deleteGroup(button, groupName) {';
        $output .= 'var $ = jQuery.noConflict();'; // Use jQuery in no-conflict mode
        $output .= 'var confirmDelete = confirm("Are you sure you want to leave the group?");';
        $output .= 'if (confirmDelete) {';
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
        $output .= 'window.location.reload(true);'; // Reload the page after successful deletion
        $output .= '},';
        $output .= 'error: function(xhr, status, error) {';
        $output .= 'alert("Error deleting group: " + error);';
        $output .= '}';
        $output .= '});';
        $output .= '}';
        $output .= '}';
        $output .= '</script>';
        
        return $output;
    }
}

// Add an AJAX handler for fetching group details
add_action('wp_ajax_gem_get_group_details', 'gem_get_group_details');
function gem_get_group_details() {
    $group_name_raw = urldecode(sanitize_text_field($_POST['group_name']));
    $email = sanitize_email($_POST['email']);

    // First, check if group details are in the transient cache
$group_details = get_transient($group_name_raw);

// If transient is empty, fetch from the API
if ($group_details === false) {
    // Make the API request to get group details
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
        return; // Ensure the execution stops here in case of error
    } else {
        // Decode the API response
        $group_details = json_decode(wp_remote_retrieve_body($response), true);

        // Store the API response in the transient cache for 1 day (24 hours)
        set_transient($group_name_raw, $group_details, DAY_IN_SECONDS);
    }
}

// Send the group details in the response (either from cache or API)
wp_send_json_success($group_details);


    wp_die(); // Required to terminate immediately and return a proper response
}

?>
