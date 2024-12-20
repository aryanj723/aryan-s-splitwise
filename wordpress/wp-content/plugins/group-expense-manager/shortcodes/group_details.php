<?php

// Group Details Shortcode
function gem_group_details_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to view group details.';
    }

    if (!isset($_GET['group_name'])) {
        return 'No group specified.';
    }

    $group_name_raw = sanitize_text_field($_GET['group_name']);
    $user = wp_get_current_user();
    $email = $user->user_email;

    $group_details = get_transient($group_name_raw);

    if ($group_details === false) {
        // If not found in the transient, proceed with the API call
        $response = wp_remote_post(GEM_API_BASE_URL . '/groups/get_group_details', array(
            'method'    => 'POST',
            'body'      => json_encode(array('name' => $group_name_raw, 'email' => $email)),
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Error fetching group details: $error_message");
            return "Something went wrong: $error_message";
        } else {
            $group_details = json_decode(wp_remote_retrieve_body($response), true);
            // Store the API response in the cache for one day
            set_transient($group_name_raw, $group_details, DAY_IN_SECONDS);
        }
    }

        // Parse the group name and creation date
        $group_name_parts = explode('$', $group_name_raw);
        
        // Check if $group_name_raw contains the expected number of parts
        if (count($group_name_parts) == 2) {
            $group_name = $group_name_parts[0];
            $creation_timestamp = $group_name_parts[1];
            $creation_date = explode('T', $creation_timestamp)[0]; // Get the date and ignore the time

            // Display the formatted group name and creation date
            $output = '<h2>Group: ' . htmlspecialchars($group_name) . ' ; Created: ' . htmlspecialchars($creation_date) . '</h2>';
        } else {
            // Fallback if the group name does not contain a valid timestamp
            $group_name = $group_name_raw;
            $output = '<h2>Group: ' . htmlspecialchars($group_name) . ' ; Created: Unknown</h2>';
        }

        $group_name = sanitize_text_field($_GET['group_name']);

        $output .= '<div class="table-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">';
        $output .= '<table class="table table-striped table-responsive" style="width: 100%; border-collapse: collapse;">';

        // Sticky Header
        $output .= '<thead class="sticky-table-header" style="position: sticky; top: 0; z-index: 2; background-color: #f2f2f2;">';
        $output .= '<tr>';
        $output .= '<th style="border-bottom: 2px solid #ccc; text-align: center;">Currency Information</th>';
        $output .= '<th style="border-bottom: 2px solid #ccc; text-align: center;">Members & Spends</th>';
        $output .= '<th style="border-bottom: 2px solid #ccc; text-align: center;">Balances in ' . htmlspecialchars($group_details['local_currency']) . ' (simplified)</th>';
        $output .= '</tr>';
        $output .= '</thead>';

        // Table Body
        $output .= '<tbody>';

        // Row Content
        $output .= '<tr>';
        $output .= '<td style="vertical-align: top;">';
        $output .= '<p>Local Currency: ' . htmlspecialchars($group_details['local_currency']) . '</p>';
        $output .= '<ul>';
        foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
            $output .= '<li>1 ' . htmlspecialchars($currency) . ' = ' . htmlspecialchars($rate) . ' ' . htmlspecialchars($group_details['local_currency']) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</td>';

        $output .= '<td style="vertical-align: top;">';
        $output .= '<ul>';
        foreach ($group_details['members'] as $member) {
            $spend = 0;
            foreach ($group_details['spends'] as $spend_entry) {
                if ($spend_entry[0] == $member) {
                    $spend = $spend_entry[1];
                    break;
                }
            }
            $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member, $group_details['members']) : $member);
            $output .= '<li>' . htmlspecialchars($display_name) . ': ' . htmlspecialchars(number_format($spend, 2)) . ' ' . htmlspecialchars($group_details['local_currency']) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</td>';

        $output .= '<td style="vertical-align: top;">';
        if (!empty($group_details['balances'])) {
            $output .= '<ul>';
            foreach ($group_details['balances'] as $balance) {
                $debtor = ($balance[0] == $email) ? 'You' : get_user_display_name($balance[0], $group_details['members']);
                $creditor = ($balance[1] == $email) ? 'You' : get_user_display_name($balance[1], $group_details['members']);
                $currency = isset($balance[3]) ? htmlspecialchars($balance[3]) : '';
                $output .= '<li>' . htmlspecialchars($debtor ?? '') . ' should pay ' . htmlspecialchars($balance[2] ?? '') . ' ' . $currency . ' to ' . htmlspecialchars($creditor ?? '') . '</li>';
            }
            $output .= '</ul>';
        } else {
            $output .= '<p>Everyone is settled</p>';
        }
        $output .= '</td>';
        $output .= '</tr>';

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        // Button Container with added buttons
        $output .= '<div class="button-container">';
        $output .= '<button id="add-currency-btn" class="btn btn-primary">Add Currency</button>';
        $output .= '<button id="add-entry-btn" class="btn btn-primary">Add Expense</button>';
        $output .= '<button id="settle-btn" class="btn btn-primary">Record Payment</button>';
        $output .= '<button id="add-user-btn" class="btn btn-primary">Add User</button>';
        $output .= '<button id="remove-expense-btn" class="btn btn-danger">Remove Expense</button>';
        $output .= '<button id="delete-group-btn" class="btn btn-danger">Leave Group</button>';
        $output .= '</div>';

        $output .= '<div id="group-entries">';
        $output .= '<h4 class="sticky-section-heading">Expenses</h4><div class="table-container">';
        $output .= gem_display_expenses(array_reverse($group_details['entries']), $group_details['members']);
        $output .= '</div>';

        $output .= '<h4 class="sticky-section-heading">Payments</h4><div class="table-container">';
        $output .= gem_display_payments(array_reverse($group_details['entries']), $group_details['members']);
        $output .= '</div>';

        $output .= '</div>';

        // Logs Section
        $output .= '<div id="group-logs">';
        $output .= '<h4>Logs</h4>';
        $output .= '<div class="table-container">';
        $output .= '<table class="table table-striped table-responsive">';

        $output .= '<tbody>';
        foreach ($group_details['logs'] as $log) {
            // Break the log into words by spaces
            $words = explode(' ', $log);
            
            // Iterate through each word to check if it's a valid email
            foreach ($words as &$word) {
                // If the word is a valid email address, replace it with the display name
                if (filter_var($word, FILTER_VALIDATE_EMAIL)) {
                    // If the email matches the current user's email, replace with 'You'
                    if ($word === $email) {
                        $word = 'You';
                    } else {
                        // Otherwise, replace it with the user's display name if they exist in the group
                        $user = get_user_by('email', $word);
                        $word = ($user && isset($group_details['members']) && in_array($word, $group_details['members'])) 
                            ? get_user_display_name($word, $group_details['members']) 
                            : $word; // If user is not found, leave the email as-is
                    }
                }
            }
            
            // Rebuild the log string from the modified words
            $modified_log = implode(' ', $words);
        
            // Output the modified log
            $output .= '<tr><td>' . htmlspecialchars($modified_log) . '</td></tr>';
        }
        
        $output .= '</tbody></table>';
        $output .= '</div>'; // Close table-container
        $output .= '</div>';

        // Modals for Add Expense, Record Payment, Add Currency, Add User, and Remove Expense
        $output .= '<div class="modal fade" id="add-entry-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-entry-form" class="needs-validation" novalidate>
                    <div class="form-group">
                        <label for="entry-description">Description:</label>
                        <input type="text" id="entry-description" class="form-control" required maxlength="20" placeholder="Enter description">
                        <div class="invalid-feedback">
                            Please enter a description.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry-amount">Amount:</label>
                        <input type="number" id="entry-amount" class="form-control" required min="0.01" step="0.01" placeholder="Enter amount">
                        <div class="invalid-feedback">
                            Please enter a valid amount.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry-paid-by">Paid By:</label>
                        <select id="entry-paid-by" class="form-control" style="padding: 8px; line-height: 1.5;">';
foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member, $group_details['members']) : $member);
    $output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}

$output .= '</select>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry-currency">Currency:</label>
                        <select id="entry-currency" class="form-control" style="padding: 8px; line-height: 1.5;">';

// Always include the local currency as an option
$output .= '<option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';

// Iterate over other currencies and add them to the dropdown
foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
    $output .= '<option value="' . htmlspecialchars($currency) . '">' . htmlspecialchars($currency) . '</option>';
}

$output .= '</select>
                    </div>
                    
                    <div class="form-group">
                        <label for="entry-shares">Shares:</label>';
foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member, $group_details['members']) : $member);
    $output .= '<div class="form-check mb-2 user-row" data-user-id="' . htmlspecialchars($member) . '">
                    <input type="checkbox" class="form-check-input user-checkbox bigger-checkbox" data-user-id="' . htmlspecialchars($member) . '" id="checkbox-' . htmlspecialchars($member) . '">
                    <label class="form-check-label user-name" for="checkbox-' . htmlspecialchars($member) . '" style="margin-left: 10px;">' . htmlspecialchars($display_name) . '</label>
                    <input type="number" class="form-control form-control-sm share-input d-inline-block" style="width: 100px; margin-left: 10px;" name="share-' . htmlspecialchars($member) . '" placeholder="Share" min="0" step="0.01" disabled>
                </div>';
}

$output .= '</div>

                    <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
                </form>
            </div>
            <div class="modal-footer">
                <div class="spinner-border text-primary d-none" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>';




$output .= '<div class="modal fade" id="settle-modal" tabindex="-1" role="dialog">
<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Record Payment</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <form id="settle-form">
                <div class="form-group">
                    <label for="payment-amount">Amount:</label>
                    <input type="number" id="payment-amount" class="form-control" required min="0.01" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="payment-paid-by">Paid By:</label>
                    <select id="payment-paid-by" class="form-control">';
foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member, $group_details['members']) : $member);
    $output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}
$output .= '</select>
                </div>

                <div class="form-group">
                    <label for="payment-paid-to">Paid To:</label>
                    <select id="payment-paid-to" class="form-control">';
foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member, $group_details['members']) : $member);
    $output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}
$output .= '</select>
                </div>

                <div class="form-group">
                    <label for="payment-currency">Currency:</label>
                    <select id="payment-currency" class="form-control">
                        <option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';
foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
    $output .= '<option value="' . htmlspecialchars($currency) . '">' . htmlspecialchars($currency) . '</option>';
}
$output .= '</select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
            </form>
        </div>
        <div class="modal-footer">
            <div class="spinner-border text-primary d-none" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>
</div>
</div>';

// Prefill data
$first_balance = null;
foreach ($group_details['balances'] as $balance) {
    if ($balance[0] == $email || $balance[1] == $email) {
        $first_balance = $balance;
        break;
    }
}
$output .= "<script>
    jQuery(document).ready(function($) {
        // Prefill when modal is opened
        $('#settle-modal').on('show.bs.modal', function() {
            var balance = " . json_encode($first_balance) . ";
            if (balance) {
                var currentUserEmail = '" . esc_js($email) . "';
                var amount = balance[2];
                var paidBy, paidTo;

                // If the current user is the debtor (the first entry in the balance array)
                if (balance[0] === currentUserEmail) {
                    paidBy = balance[0];  // The current user is paying
                    paidTo = balance[1];  // Paying to the second user
                } else if (balance[1] === currentUserEmail) {
                    paidBy = balance[0];  // The first user is paying
                    paidTo = balance[1];  // Paying to the current user
                } else {
                    // No relevant balance found, clear fields
                    $('#payment-amount').val('');
                    $('#payment-paid-by').val('');
                    $('#payment-paid-to').val('');
                    return; // Stop further processing
                }

                // Prefill the form fields
                $('#payment-amount').val(amount);
                $('#payment-paid-by').val(paidBy);
                $('#payment-paid-to').val(paidTo);
            } else {
                // Clear fields if no balance is found
                $('#payment-amount').val('');
                $('#payment-paid-by').val('');
                $('#payment-paid-to').val('');
            }
        });
    });
</script>";


$output .= '<div class="modal fade" id="add-currency-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Currency</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-currency-form">
                    <div class="form-group">
                        <label for="currency-name">Currency Name:</label>
                        <input type="text" id="currency-name" class="form-control form-control-sm" required placeholder="Enter new currency (e.g., EUR)" maxlength="10">
                        <div id="currency-suggestions" class="list-group" style="display:none; max-height: 100px; overflow-y: auto; background: #fff; border: 1px solid #ccc;"></div>
                    </div>
                    
                    <div class="form-group" id="conversion-rate-group" style="display: none;">
                        <label for="conversion-rate-display">Exchange Rate: (as of today)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">1&nbsp;<span id="currency-display">[Currency]</span> =</span>
                            </div>
                            <input type="number" id="conversion-rate" class="form-control form-control-sm" required placeholder="Conversion rate" step="0.000001" min="0.000001" max="9999999999.999999">
                            <div class="input-group-append">
                                <span class="input-group-text">' . htmlspecialchars($group_details['local_currency']) . '</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
                </form>
            </div>
            <div class="modal-footer">
                <div class="spinner-border text-primary d-none" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>';



        // Add User Modal
        $output .= '<div class="modal fade" id="add-user-modal" tabindex="-1" role="dialog">
<div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Add User</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <form id="add-user-form" class="needs-validation" novalidate>
                <div class="form-group">
                    <label for="new-member-email">New Member Email:</label>
                    <input type="email" id="new-member-email" class="form-control form-control-sm" required placeholder="Enter user email">
                    <div class="invalid-feedback">
                        Please enter a valid email address.
                    </div>
                    <div id="email-suggestions" class="list-group" style="display:none; max-height: 100px; overflow-y: auto; background: #fff; border: 1px solid #ccc;"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
            </form>
        </div>
        <div class="modal-footer">
            <div class="spinner-border text-primary d-none" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>
</div>
</div>';


        // Remove Expense Modal
        $output .= '<div class="modal fade" id="remove-expense-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Remove Expense</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="remove-expense-form">
                        <label for="expense-select">Select Expense:</label>
                        <select id="expense-select" class="form-control" required>';

            foreach (array_reverse($group_details['entries']) as $entry) {
                if ($entry['type'] === 'expense' && !$entry['cancelled']) {
                    
                    // Extract the date part before the microseconds (i.e., before the last colon)
                    $raw_date = $entry['date'];
                    $date_parts = explode(':', $raw_date); // Split by colon
                    if (count($date_parts) >= 3) {
                        // Join the first 3 parts to form a valid datetime string
                        $datetime_str = $date_parts[0] . ':' . $date_parts[1] . ':' . $date_parts[2];
                        try {
                            $date = new DateTime($datetime_str);
                            $formatted_date = ' - ' . $date->format('M j, Y \a\t g:i:s A');
                        } catch (Exception $e) {
                            $formatted_date = ''; // If date is invalid, do not display any date
                        }
                    } else {
                        $formatted_date = ''; // If the date format is invalid
                    }

                    // Display description, amount, currency, and the formatted date (if valid)
                    $output .= '<option value="' . htmlspecialchars($entry['date']) . '">'
                            . htmlspecialchars($entry['description']) . ' - ' 
                            . number_format($entry['amount'], 2) . ' ' 
                            . htmlspecialchars($entry['currency']) 
                            . $formatted_date // Add date if valid, else it remains blank
                            . '</option>';
                }
            }

            $output .= '</select>
                        <button type="submit" class="btn btn-danger mt-3">Remove</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <div class="spinner-border text-primary d-none" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        </div>';

        $output .= '<div id="response-message"></div>';

        $output .= '<script>
                        jQuery(document).ready(function($) {
                            // Pre-fill the "Paid By" field with the current logged-in user email when the modal is opened
                            $("#add-entry-modal").on("show.bs.modal", function() {
                                var userEmail = "' . esc_js($email) . '"; // Current logged-in user email
                                $("#entry-paid-by").val(userEmail); 
                            });

                            // Handle row click for checkbox toggle (except for the input field)
                            $(document).on("click", ".user-row", function(e) {
                                // Avoid toggling the checkbox when interacting with the share input box
                                if (!$(e.target).is(".share-input") && !$(e.target).is(".user-checkbox")) {
                                    var checkbox = $(this).find(".user-checkbox");
                                    checkbox.prop("checked", !checkbox.prop("checked")).trigger("change");
                                }
                            });

                            // Ensure checkbox toggle works independently when directly clicked
                            $(document).on("click", ".user-checkbox", function(e) {
                                e.stopPropagation(); // Prevent the row click event from being triggered
                            });

                            // Add Entry Form Submission
                            $("#add-entry-form").on("submit", function(event) {
                                event.preventDefault();

                                var groupName = "' . esc_js($group_name) . '";
                                var userEmail = "' . esc_js($email) . '";
                                var description = $("#entry-description").val().trim();
                                var amount = parseFloat($("#entry-amount").val());
                                var paidBy = $("#entry-paid-by").val();
                                var currency = $("#entry-currency").val();
                                var validShares = true;
                                var totalShare = 0;

                                // Validate description
                                if (description === "") {
                                    alert("Description cannot be empty.");
                                    return;
                                }

                                // Check if description exceeds 20 characters
                                if (description.length > 20) {
                                    alert("Description must not exceed 20 characters.");
                                    return;
                                }

                                // Validate amount
                                if (isNaN(amount) || amount < 1) {
                                    alert("Amount must be at least 1.");
                                    return;
                                }

                                // Collect shares
                                var shares = {};
                                $(".user-checkbox:checked").each(function() {
                                    var userId = $(this).data("user-id");
                                    var shareAmount = parseFloat($(this).closest(".form-check").find(".share-input").val()); // Adjusted for Bootstrap

                                    if (isNaN(shareAmount) || shareAmount < 0) {
                                        alert("Shares must be at least 0 and must be valid.");
                                        validShares = false;
                                        return false;
                                    }

                                    shares[userId] = shareAmount;
                                    totalShare += shareAmount;
                                });

                                // Validate if shares add up correctly
                                if (!validShares || Math.abs(totalShare - amount) > 1) {
                                    alert("Shares do not add up to the total amount.");
                                    return;
                                }

                                // Show spinner
                                $(".modal-footer .spinner-border").removeClass("d-none");

                                // AJAX call to add the expense
                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_expense",
                                        group_name: groupName,
                                        email: userEmail,
                                        description: description,
                                        amount: amount,
                                        paid_by: paidBy,
                                        shares: shares,
                                        currency: currency
                                    },
                                    success: function(response) {
                                        // Hide spinner
                                        $(".modal-footer .spinner-border").addClass("d-none");

                                        if (response.success) {
                                            // Close the modal first
                                            $("#add-entry-modal").modal("hide");

                                            // After modal is closed, show the success message
                                            alert("Expense added successfully!");
                                            location.reload(true);
                                        } else {
                                            alert("Failed to add expense: " + response.data);
                                        }
                                    },
                                    error: function(error) {
                                        // Hide spinner in case of error
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        alert("An error occurred: " + error.statusText);
                                    }
                                });
                            });



                            $("#settle-form").on("submit", function(event) {
                                event.preventDefault();

                                var groupName = "' . esc_js($group_name) . '";
                                var userEmail = "' . esc_js($email) . '";
                                var description = ""; // Set description to empty string
                                var amount = parseFloat($("#payment-amount").val()).toFixed(2);
                                var paidBy = $("#payment-paid-by").val();
                                var paidTo = $("#payment-paid-to").val();
                                var currency = $("#payment-currency").val();

                                // Validate amount
                                if (isNaN(amount) || amount <= 0) {
                                    alert("Amount must be greater than 0.");
                                    return;
                                }

                                // Ensure only up to 2 decimal places
                                if (amount.toString().split(".")[1] && amount.toString().split(".")[1].length > 2) {
                                    alert("Amount must be a valid number with up to 2 decimal places.");
                                    return;
                                }

                                // Check that payer and payee are different
                                if (paidBy === paidTo) {
                                    alert("The payer and payee must be different.");
                                    return;
                                }

                                // Show spinner
                                $(".modal-footer .spinner-border").removeClass("d-none");

                                // AJAX call to record the payment
                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_payment",
                                        group_name: groupName,
                                        email: userEmail,
                                        description: description, // Pass empty string
                                        amount: amount,
                                        paid_by: paidBy,
                                        paid_to: paidTo,
                                        currency: currency
                                    },
                                    success: function(response) {
                                        // Hide spinner
                                        $(".modal-footer .spinner-border").addClass("d-none");

                                        if (response.success) {
                                            // Close the modal first
                                            $("#settle-modal").modal("hide");

                                            // After modal is closed, show the success message
                                            alert("Payment recorded successfully!");
                                            location.reload(true);
                                        } else {
                                            alert("Failed to record payment: " + response.data);
                                        }
                                    },
                                    error: function(error) {
                                        // Hide spinner in case of error
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        alert("An error occurred: " + error.statusText);
                                    }
                                });
                            });




                            $("#add-currency-btn").click(function() {
                                $("#add-currency-modal").modal("show");
                            });

                            $("#add-entry-btn").click(function() {
                                $("#add-entry-modal").modal("show");
                            });

                            $("#settle-btn").click(function() {
                                $("#settle-modal").modal("show");
                            });

                            $("#add-user-btn").click(function() {
                                $("#add-user-modal").modal("show");
                            });

                            $("#remove-expense-btn").click(function() {
                                $("#remove-expense-modal").modal("show");
                            });

                            $("#delete-group-btn").click(function() {
                                var confirmDelete = confirm("Are you sure you want to leave the group?");
                                if (confirmDelete) {
                                    $.ajax({
                                        url: "' . admin_url('admin-ajax.php') . '",
                                        type: "POST",
                                        data: {
                                            action: "gem_delete_group",
                                            group_name: "' . $group_name . '",
                                            email: "' . $email . '"
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                alert("Successfully left the group.");
                                                window.location.href = "' . site_url('/my-groups') . '";
                                            } else if (response.data && response.data.status === 400) {
                                                alert("Unable to leave group, you have pending balances.");
                                            } else {
                                                alert(response.data || "An unexpected error occurred.");
                                            }
                                        },
                                        error: function(error) {
                                            alert("An error occurred: " + error.statusText);
                                        }
                                    });
                                }
                            });

                            // Add Currency Form Submission
                            $("#add-currency-form").on("submit", function(event) {
                                event.preventDefault();

                                var currencyName = $("#currency-name").val().trim().toUpperCase();
                                var conversionRate = parseFloat($("#conversion-rate").val()).toFixed(6); // Updated to 6 decimal places

                                // List of existing currencies and the local currency for validation
                                var existingCurrencies = ' . json_encode(array_keys($group_details["currency_conversion_rates"])) . ';
                                var localCurrency = "' . esc_js($group_details["local_currency"]) . '";

                                // Validate that the currency name does not exceed 20 characters
                                if (currencyName.length > 20) {
                                    alert("Currency name must not exceed 20 characters.");
                                    return;
                                }

                                // Validate that the currency name does not match previously added currencies or the local currency
                                if (existingCurrencies.includes(currencyName) || currencyName === localCurrency) {
                                    alert("Currency name already exists or matches the local currency.");
                                    return;
                                }

                                // Validate that the conversion rate is greater than 0 and has up to 6 decimal places
                                if (isNaN(conversionRate) || conversionRate <= 0) {
                                    alert("Conversion rate must be greater than 0 and valid up to 6 decimal places.");
                                    return;
                                }

                                // Show spinner
                                $(".modal-footer .spinner-border").removeClass("d-none");

                                // AJAX call to add the currency
                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_currency",
                                        group_name: "' . esc_js($group_name) . '",
                                        email: "' . esc_js($email) . '",
                                        currency: currencyName,
                                        conversion_rate: conversionRate
                                    },
                                    success: function(response) {
                                        // Hide spinner
                                        $(".modal-footer .spinner-border").addClass("d-none");

                                        if (response.success) {
                                            // Close the modal first
                                            $("#add-currency-modal").modal("hide");

                                            // After modal is closed, show the success message
                                            alert("Currency added successfully!");
                                            location.reload(true);
                                        } else {
                                            alert("Failed to add currency: " + response.data);
                                        }
                                    },
                                    error: function(error) {
                                        // Hide spinner in case of error
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        alert("An error occurred: " + error.statusText);
                                    }
                                });
                            });

                            // Initially hide the conversion rate input box and disable the submit button
                            $("#conversion-rate-group").hide();
                            $("#add-currency-form button[type=\"submit\"]").prop("disabled", true);

                            // Display the new currency in the input prompt dynamically
                            $("#currency-name").on("input", function() {
                                var search_term = $(this).val().trim().toUpperCase();
                                
                                if (search_term.length > 0) {
                                    // Show the conversion rate field with pre-filled value of 1 for custom currency
                                    $("#currency-display").text(search_term); // Dynamically populate the currency display
                                    $("#conversion-rate").val(1); // Pre-fill with 1 for custom currency
                                    $("#conversion-rate-group").fadeIn();  // Show conversion rate input field
                                    $("#add-currency-form button[type=\"submit\"]").prop("disabled", false);  // Enable submit button

                                    $.ajax({
                                        url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                        method: "POST",
                                        data: {
                                            action: "gem_search_currency",
                                            search_term: search_term
                                        },
                                        success: function(response) {
                                            $("#currency-suggestions").empty().show();
                                            if (response.success && response.data.length > 0) {
                                                $.each(response.data, function(index, currency) {
                                                    $("#currency-suggestions").append("<button type=\"button\" class=\"list-group-item list-group-item-action\" data-currency=\"" + currency.currency + "\">" + currency.full_name + " (" + currency.currency + ")</button>");
                                                });
                                            } else {
                                                $("#currency-suggestions").hide();
                                            }
                                        },
                                        error: function() {
                                            $("#currency-suggestions").hide();
                                        }
                                    });
                                } else {
                                    $("#currency-suggestions").hide();
                                    $("#conversion-rate-group").fadeOut();  // Hide conversion rate if no input
                                    $("#add-currency-form button[type=\"submit\"]").prop("disabled", true);  // Disable submit button
                                }
                                $("#conversion-rate").val("");  // Clear rate if the user changes currency input
                            });

                            // Handle currency selection from suggestions
                            $(document).on("click", ".list-group-item", function() {
                                var selectedCurrency = $(this).data("currency");
                                var localCurrency = "' . esc_js($group_details["local_currency"]) . '";  // Assuming local currency is displayed in #currency-display
                                
                                // Debugging log
                                console.log("Selected Currency: " + selectedCurrency + ", Local Currency: " + localCurrency);

                                $("#currency-name").val(selectedCurrency);
                                $("#currency-display").text(" " + selectedCurrency); // Added non-breaking space before currency
                                $("#currency-suggestions").hide();

                                // Show "Fetching forex rate..." in the conversion rate input field for better UX
                                var $conversionRateInput = $("#conversion-rate");
                                $conversionRateInput.prop("disabled", true);  // Disable conversion rate input during loading
                                $conversionRateInput.val("Fetching forex rate...");  // Show fetching text in the input field
                                $(".modal-footer .spinner-border").addClass("d-none");  // Hide footer spinner

                                // Fetch exchange rate and prefill
                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    method: "POST",
                                    data: {
                                        action: "gem_get_exchange_rate",
                                        currency: selectedCurrency,
                                        to_currency: localCurrency  // Pass the local currency dynamically
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            // Fix the conversion logic, show 1 selected currency = x local currency
                                            var rate = parseFloat(response.data);
                                            
                                            // Debugging log
                                            console.log("Exchange rate fetched: " + rate);

                                            $conversionRateInput.val(rate.toFixed(6));  // Pre-fill conversion rate
                                            $conversionRateInput.prop("disabled", false);  // Re-enable input
                                            $("#conversion-rate-group").show();  // Show the conversion rate input box
                                            $("#add-currency-form button[type=\"submit\"]").prop("disabled", false);  // Enable submit button
                                        } else {
                                            $conversionRateInput.val("");  // Clear input field
                                            $conversionRateInput.prop("disabled", false);  // Re-enable input
                                            $("#add-currency-form button[type=\"submit\"]").prop("disabled", true);  // Disable submit button
                                        }
                                    },
                                    error: function() {
                                        $conversionRateInput.val("");  // Clear input field
                                        $conversionRateInput.prop("disabled", false);  // Re-enable input
                                        $("#add-currency-form button[type=\"submit\"]").prop("disabled", true);  // Disable submit button
                                    }
                                });
                            });

                            // Disable submit if the conversion rate is not valid
                            $("#conversion-rate").on("input", function() {
                                var rate = parseFloat($(this).val());
                                // Ensure no unintended rounding issue occurs with fixed decimals
                                if (isNaN(rate) || rate <= 0) {
                                    $("#add-currency-form button[type=\"submit\"]").prop("disabled", true);
                                } else {
                                    // Allow a valid rate to be submitted
                                    $("#add-currency-form button[type=\"submit\"]").prop("disabled", false);
                                }
                            });

                            $("#add-user-form").submit(function(e) {
                                e.preventDefault();

                                var newMemberEmail = $("#new-member-email").val().trim().toLowerCase();
                                var currentMembers = ' . json_encode($group_details['members']) . ';

                                // Email validation regex pattern
                                var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;

                                // Validate if email is in correct format
                                if (!emailPattern.test(newMemberEmail)) {
                                    alert("Please enter a valid email address.");
                                    return;
                                }

                                // Validate that the email is not already a member of the group
                                if (currentMembers.includes(newMemberEmail)) {
                                    alert("This user is already a member of the group.");
                                    return;
                                }

                                // Show spinner
                                $(".modal-footer .spinner-border").removeClass("d-none");

                                // AJAX call to add the user
                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_user",
                                        group_name: "' . esc_js($group_name) . '",
                                        email: "' . esc_js($email) . '",
                                        new_member_email: newMemberEmail
                                    },
                                    success: function(response) {
                                        // Hide spinner
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#add-user-modal").modal("hide");
                                        $("#response-message").html(response.data);
                                        setTimeout(function() {
                                            location.reload(true);
                                        }, 2000);
                                    },
                                    error: function(error) {
                                        // Hide spinner on error
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#response-message").html("An error occurred: " + error);
                                    }
                                });
                            });

                            // Search for users when typing in the new member email input
                            $("#new-member-email").on("input", function () {
                                var search_term = $(this).val().trim().toLowerCase();
                                var $input = $(this);

                                if (search_term.length >= 3) {
                                    // Perform AJAX search to find users
                                    $.ajax({
                                        url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                        method: "POST",
                                        data: {
                                            action: "gem_search_members",  // Ensure this action is handled in your backend
                                            search_term: search_term
                                        },
                                        success: function (response) {
                                            // Clear previous suggestions
                                            $("#email-suggestions").empty();

                                            // If search is successful and users are found
                                            if (response.success && response.data.length > 0) {
                                                var suggestions = "";
                                                $.each(response.data, function (index, user) {
                                                    if (user.email.toLowerCase() !== "' . esc_js($email) . '") {
                                                        suggestions += `<button type="button" class="btn btn-sm btn-info suggestion-item" 
                                                                        data-email="${user.email}">${user.name} - ${user.email}</button><br>`;
                                                    }
                                                });
                                                $("#email-suggestions").html(suggestions).show();
                                            } else {
                                                // If no users are found
                                                $("#email-suggestions").html("<p>No results found</p>").show();
                                            }
                                        },
                                        error: function () {
                                            // In case of AJAX error
                                            $("#email-suggestions").html("<p>Error searching for users</p>").show();
                                        }
                                    });
                                } else {
                                    // Hide suggestions if input length is less than 3 characters
                                    $("#email-suggestions").hide();
                                }
                            });

                            // Handle suggestion click
                            $(document).on("click", ".suggestion-item", function () {
                                var email = $(this).data("email");
                                $("#new-member-email").val(email);  // Fill the input with the selected email
                                $("#email-suggestions").hide();  // Hide the suggestions box after selecting
                            });

                            // Form submission for removing expense
                            $("#remove-expense-form").submit(function(e) {
                                e.preventDefault();
                                var expenseDatetime = $("#expense-select").val();

                                $(".modal-footer .spinner-border").removeClass("d-none");

                                $.ajax({
                                    url: "' . esc_url(admin_url('admin-ajax.php')) . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_remove_expense",
                                        group_name: "' . esc_js($group_name) . '",
                                        email: "' . esc_js($email) . '",
                                        expense_datetime: expenseDatetime
                                    },
                                    success: function(response) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#remove-expense-modal").modal("hide");
                                        $("#response-message").html(response.data);
                                        setTimeout(function() {
                                            location.reload(true);
                                        }, 2000);
                                    },
                                    error: function(error) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#response-message").html("An error occurred: " + error);
                                    }
                                });
                            });

                            // Input change for amount
                            $("#entry-amount").on("input", function() {
                                var amount = parseFloat($(this).val());
                                if (isNaN(amount) || amount < 1) {
                                    $(this).val(1);
                                }
                            });

                            // Checkbox handling for shares
                            $(".user-checkbox").change(function() {
                                var checkedBoxes = $(".user-checkbox:checked");
                                var totalAmount = parseFloat($("#entry-amount").val());
                                var shareAmount = (totalAmount / checkedBoxes.length).toFixed(2);

                                $(".share-input").val("0.00").prop("disabled", true).parent().find(".user-name").css("opacity", "0.5");

                                checkedBoxes.each(function() {
                                    var input = $(this).parent().find(".share-input");
                                    input.val(shareAmount).prop("disabled", false);
                                    input.parent().find(".user-name").css("opacity", "1");
                                });
                            });

                            // Reset the form on pop-up close
                            $("#add-entry-modal, #settle-modal, #add-currency-modal, #add-user-modal, #remove-expense-modal").on("hidden.bs.modal", function() {
                                $(this).find("form")[0].reset();
                                $(".user-checkbox").prop("checked", false);
                                $(".share-input").val("0.00").prop("disabled", true).parent().find(".user-name").css("opacity", "0.5");
                            });
                            // Reset the form and suggestions on pop-up close
                            $("#add-user-modal").on("hidden.bs.modal", function() {
                                $(this).find("form")[0].reset(); // Reset form fields
                                $("#email-suggestions").empty();  // Clear out the suggestions
                            });

                            $("img.lazy").lazyload({
                                effect : "fadeIn"
                            });
                        });
                    </script>';

        return $output;
    }

?>
