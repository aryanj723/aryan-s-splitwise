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

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/get_group_details', array(
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

        // Parse the group name and creation date
        list($group_name, $creation_timestamp) = explode('$', $group_name_raw);
        $creation_date = explode('T', $creation_timestamp)[0]; // Get the date and ignore the time

        // Display the formatted group name and creation date
        $output = '<h2>Group: ' . htmlspecialchars($group_name) . ' ; Created: ' . htmlspecialchars($creation_date) . '</h2>';
        $group_name = sanitize_text_field($_GET['group_name']);

        $output .= '<table class="table">';
        $output .= '<thead><tr><th>Currency Information</th><th>Members & Spends</th><th>Balances</th></tr></thead>';
        $output .= '<tbody><tr>';

        // Currency Information
        $output .= '<td>';
        $output .= '<p>Local Currency: ' . htmlspecialchars($group_details['local_currency']) . '</p>';
        $output .= '<ul>';
        foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
            $output .= '<li>1 ' . htmlspecialchars($currency) . ' = ' . htmlspecialchars($rate) . ' ' . htmlspecialchars($group_details['local_currency']) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</td>'; // Close Currency Information column

        // Members & Spends
        $output .= '<td>';
        $output .= '<div class="members">Members & Spends:</div><ul>';
        
        foreach ($group_details['members'] as $member) {
            $spend = 0;
            foreach ($group_details['spends'] as $spend_entry) {
                if ($spend_entry[0] == $member) {
                    $spend = $spend_entry[1];
                    break;
                }
            }
            $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
            $output .= '<li>' . htmlspecialchars($display_name) . ': ' . htmlspecialchars(number_format($spend, 2)) . ' ' . htmlspecialchars($group_details['local_currency']) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</td>'; // Close Members & Spends column

        // Balances
        $output .= '<td>';
        if (!empty($group_details['balances'])) {
            $output .= '<ul>';
            foreach ($group_details['balances'] as $balance) {
                $debtor = ($balance[0] == $email) ? 'You' : get_user_display_name($balance[0]);
                $creditor = ($balance[1] == $email) ? 'You' : get_user_display_name($balance[1]);
                
                // Check if the currency key exists before accessing
                $currency = isset($balance[3]) ? htmlspecialchars($balance[3]) : '';

                // Ensure debtor and creditor are not null
                $output .= '<li>' . htmlspecialchars($debtor ?? '') . ' should pay ' . htmlspecialchars($balance[2] ?? '') . ' ' . $currency . ' to ' . htmlspecialchars($creditor ?? '') . '</li>';
            }
            $output .= '</ul>';
        }
        $output .= '</td>'; // Close Balances column

        $output .= '</tr></tbody></table>';

        // Button Container with added buttons
        $output .= '<div class="button-container">';
        $output .= '<button id="add-currency-btn" class="btn btn-primary" style="margin-right: 10px;">Add Currency</button>';
        $output .= '<button id="add-entry-btn" class="btn btn-primary" style="margin-right: 10px;">Add Expense</button>';
        $output .= '<button id="settle-btn" class="btn btn-primary" style="margin-right: 10px;">Record Payment</button>';
        $output .= '<button id="add-user-btn" class="btn btn-primary" style="margin-right: 10px;">Add User</button>';
        $output .= '<button id="remove-expense-btn" class="btn btn-danger" style="margin-right: 10px;">Remove Expense</button>';
        $output .= '<button id="delete-group-btn" class="btn btn-danger">Leave Group</button>';
        $output .= '</div>';

        $output .= '<div id="group-entries">';
        $output .= '<h4>Expenses</h4>' . gem_display_expenses($group_details['entries']);
        $output .= '<h4>Payments</h4>' . gem_display_payments($group_details['entries']);
        $output .= '</div>';

        // Logs Section
        $output .= '<div id="group-logs">';
        $output .= '<h4>Logs</h4>';
        $output .= '<table class="table">';
        $output .= '<tbody>';
        foreach ($group_details['logs'] as $log) {
            $output .= '<tr><td>' . htmlspecialchars($log) . '</td></tr>';
        }
        $output .= '</tbody></table>';
        $output .= '</div>';

        // Modals for Add Expense, Record Payment, Add Currency, Add User, and Remove Expense
        $output .= '<div class="modal fade" id="add-entry-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-entry-form">
                    <label for="entry-description">Description:</label>
                    <input type="text" id="entry-description" class="form-control" required maxlength="20">
                    
                    <label for="entry-amount">Amount:</label>
                    <input type="number" id="entry-amount" class="form-control" required min="1" step="0.01">
                    
                    <label for="entry-paid-by">Paid By:</label>
                    <select id="entry-paid-by" class="form-control">';

foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
    $output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}

$output .= '</select>
                    
                    <label for="entry-currency">Currency:</label>
                    <select id="entry-currency" class="form-control">
                        <option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';

foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
    $output .= '<option value="' . htmlspecialchars($currency) . '">' . htmlspecialchars($currency) . '</option>';
}

$output .= '</select>
                    
                    <label for="entry-shares">Shares:</label>';

foreach ($group_details['members'] as $member) {
    $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
    $output .= '<div class="user-share" style="display: flex; align-items: center; margin-bottom: 10px;">
                    <input type="checkbox" class="user-checkbox" data-user-id="' . htmlspecialchars($member) . '" style="margin-right: 10px;">
                    <span class="user-name" style="width: 60%;">' . htmlspecialchars($display_name) . '</span>
                    <input type="number" class="form-control share-input" name="share-' . htmlspecialchars($member) . '" style="width: 25%; margin-left: auto;" placeholder="Share" min="0" step="0.01" disabled>
                </div>';
}

$output .= '<button type="submit" class="btn btn-primary mt-3">Submit</button>
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
                <label for="payment-amount">Amount:</label>
                <input type="number" id="payment-amount" class="form-control" required min="1" step="0.01">
                
                <label for="payment-paid-by">Paid By:</label>
                <select id="payment-paid-by" class="form-control">';
foreach ($group_details['members'] as $member) {
$display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
$output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}

$output .= '</select>
                
                <label for="payment-paid-to">Paid To:</label>
                <select id="payment-paid-to" class="form-control">';
foreach ($group_details['members'] as $member) {
$display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
$output .= '<option value="' . htmlspecialchars($member) . '">' . htmlspecialchars($display_name) . '</option>';
}

$output .= '</select>
                
                <label for="payment-currency">Currency:</label>
                <select id="payment-currency" class="form-control">
                    <option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';
foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
$output .= '<option value="' . htmlspecialchars($currency) . '">' . htmlspecialchars($currency) . '</option>';
}

$output .= '</select>
                
                <button type="submit" class="btn btn-primary mt-3">Submit</button>
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


        // Add Currency Modal
        $output .= '<div class="modal fade" id="add-currency-modal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Currency</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="add-currency-form">
                                <label for="currency-name">Currency Name:</label>
                                <input type="text" id="currency-name" class="form-control" required placeholder="Enter new currency (e.g., EUR)">
                                
                                <label for="conversion-rate-display">Conversion Rate:</label>
                                <div class="input-group">
                                    <span class="input-group-text">1 <span id="currency-display">[Currency]</span> = </span>
                                    <input type="number" id="conversion-rate" class="form-control" required placeholder="Conversion rate" step="0.0001" min="0.0001" max="99999.9999">
                                    <span class="input-group-text">' . htmlspecialchars($group_details['local_currency']) . '</span>
                                </div>
                                
                                <button type="submit" class="btn btn-primary mt-3">Submit</button>
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
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add User</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="add-user-form">
                                        <label for="new-member-email">New Member Email:</label>
                                        <input type="email" id="new-member-email" class="form-control" required>
                                        <button type="submit" class="btn btn-primary mt-3">Submit</button>
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

        foreach ($group_details['entries'] as $entry) {
            if ($entry['type'] === 'expense' && !$entry['cancelled']) {
                $output .= '<option value="' . htmlspecialchars($entry['date']) . '">' . htmlspecialchars($entry['description'] . ' - ' . number_format($entry['amount'], 2) . ' ' . $entry['currency']) . '</option>';
            }
        }

        $output .= '           </select>
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
                                    var shareAmount = parseFloat($(this).closest(".user-share").find(".share-input").val());

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
                                            location.reload();
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
                                            location.reload();
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

                            $("#add-currency-form").on("submit", function(event) {
                                event.preventDefault();
                                
                                var currencyName = $("#currency-name").val().trim();
                                var conversionRate = parseFloat($("#conversion-rate").val()).toFixed(4);

                                // Validate that the currency name does not exceed 20 characters
                                if (currencyName.length > 20) {
                                    alert("Currency name must not exceed 20 characters.");
                                    return;
                                }

                                // Validate that the conversion rate is greater than 0
                                if (isNaN(conversionRate) || conversionRate <= 0) {
                                    alert("Conversion rate must be greater than 0 and valid up to 4 decimal places.");
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
                                            location.reload();
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

                            // Display the new currency in the input prompt dynamically
                            $("#currency-name").on("input", function() {
                                var currency = $(this).val().toUpperCase().trim();
                                if (currency !== "") {
                                    $("#currency-display").text(currency);
                                } else {
                                    $("#currency-display").text("[Currency]");
                                }
                            });

                            $("#add-user-form").submit(function(e) {
                                e.preventDefault();
                                var newMemberEmail = $("#new-member-email").val();

                                $(".modal-footer .spinner-border").removeClass("d-none");

                                $.ajax({
                                    url: "' . admin_url('admin-ajax.php') . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_user",
                                        group_name: "' . $group_name . '",
                                        email: "' . $email . '",
                                        new_member_email: newMemberEmail
                                    },
                                    success: function(response) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#add-user-modal").modal("hide");
                                        $("#response-message").html(response.data);
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    },
                                    error: function(error) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#response-message").html("An error occurred: " + error);
                                    }
                                });
                            });

                            $("#remove-expense-form").submit(function(e) {
                                e.preventDefault();
                                var expenseDatetime = $("#expense-select").val();

                                $(".modal-footer .spinner-border").removeClass("d-none");

                                $.ajax({
                                    url: "' . admin_url('admin-ajax.php') . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_remove_expense",
                                        group_name: "' . $group_name . '",
                                        email: "' . $email . '",
                                        expense_datetime: expenseDatetime
                                    },
                                    success: function(response) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#remove-expense-modal").modal("hide");
                                        $("#response-message").html(response.data);
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    },
                                    error: function(error) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#response-message").html("An error occurred: " + error);
                                    }
                                });
                            });

                            $("#entry-amount").on("input", function() {
                                var amount = parseFloat($(this).val());
                                if (isNaN(amount) || amount < 1) {
                                    $(this).val(1);
                                }
                            });

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

                            $("img.lazy").lazyload({
                                effect : "fadeIn"
                            });
                        });
                    </script>';

        return $output;
    }
}

?>
