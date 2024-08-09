<?php

// Group Details Shortcode
function gem_group_details_shortcode() {
    if (!is_user_logged_in()) {
        return 'You must be logged in to view group details.';
    }

    if (!isset($_GET['group_name'])) {
        return 'No group specified.';
    }

    $group_name = sanitize_text_field($_GET['group_name']);
    $user = wp_get_current_user();
    $email = $user->user_email;

    $response = wp_remote_post('https://pelagic-rig-428909-d0.lm.r.appspot.com/groups/get_group_details', array(
        'method'    => 'POST',
        'body'      => json_encode(array('name' => $group_name, 'email' => $email)),
        'headers'   => array(
            'Content-Type' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    } else {
        $group_details = json_decode(wp_remote_retrieve_body($response), true);
        $output = '<h2>' . htmlspecialchars($group_details['name']) . '</h2>';

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

        $output .= '<div class="button-container">';
        $output .= '<button id="add-entry-btn" class="btn btn-primary" style="margin-right: 10px;">Add Expense</button>';
        $output .= '<button id="settle-btn" class="btn btn-primary" style="margin-right: 10px;">Record Payment</button>';
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

        // Modals for Add Expense and Record Payment
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
                                        <input type="text" id="entry-description" class="form-control" required>
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
                    <select id="entry-currency" class="form-control">';
        $output .= '<option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';
        foreach ($group_details['currency_conversion_rates'] as $currency => $rate) {
            $output .= '<option value="' . htmlspecialchars($currency) . '">' . htmlspecialchars($currency) . '</option>';
        }
        $output .= '</select>
                    <label for="entry-shares">Shares:</label>';
        foreach ($group_details['members'] as $member) {
            $display_name = ($member == $email) ? 'You' : (get_user_by('email', $member) ? get_user_display_name($member) : $member);
            $output .= '<div class="user-share" style="display: flex; align-items: center; margin-bottom: 10px;">';
            $output .= '<input type="checkbox" class="user-checkbox" data-user-id="' . htmlspecialchars($member) . '" style="margin-right: 10px;">';
            $output .= '<span class="user-name" style="width: 60%;">' . htmlspecialchars($display_name) . '</span>';
            $output .= '<input type="number" class="form-control share-input" name="share-' . htmlspecialchars($member) . '" style="width: 25%; margin-left: auto;" placeholder="Share" min="0" step="0.01" disabled>';
            $output .= '</div>';
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
                                        <label for="payment-description">Description:</label>
                                        <input type="text" id="payment-description" class="form-control" required>
                                        <label for="payment-amount">Amount:</label>
                                        <input type="number" id="payment-amount" class="form-control" required>
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
                                        <select id="payment-currency" class="form-control">';
        $output .= '<option value="' . htmlspecialchars($group_details['local_currency']) . '">' . htmlspecialchars($group_details['local_currency']) . '</option>';
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

        $output .= '<div id="response-message"></div>';

        $output .= '<script>
                        jQuery(document).ready(function($) {
                            $("#add-entry-btn").click(function() {
                                $("#add-entry-modal").modal("show");
                            });

                            $("#settle-btn").click(function() {
                                $("#settle-modal").modal("show");
                            });

                            $("#delete-group-btn").click(function() {
                                var confirmDelete = confirm("Are you sure you want to leave the group?");
                                if (confirmDelete) {
                                    $.ajax({
                                        url: "' . admin_url('admin-ajax.php') . '",
                                        type: "POST",
                                        data: {
                                            action: "gem_delete_group",
                                            group_name: "' . rawurlencode($group_name) . '",
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

                            $("#entry-amount").on("input", function() {
                                var amount = parseFloat($(this).val());
                                if (isNaN(amount) || amount < 1) {
                                    $(this).val(1);
                                }
                            });

                            $("#add-entry-form").submit(function(e) {
                                e.preventDefault();
                                var shares = {};
                                var totalShares = 0;
                                $(".share-input").each(function() {
                                    var share = parseFloat($(this).val()) || 0;
                                    if (share > 0) {
                                        shares[$(this).attr("name").split("-")[1]] = share.toFixed(2);
                                    }
                                    totalShares += share;
                                });

                                var amount = parseFloat($("#entry-amount").val());
                                if (totalShares > amount + 1) {
                                    alert("The total shares exceed the acceptable range around the total amount. Please correct this.");
                                    return;
                                }

                                $(".modal-footer .spinner-border").removeClass("d-none");

                                $.ajax({
                                    url: "' . admin_url('admin-ajax.php') . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_expense",
                                        group_name: "' . rawurlencode($group_name) . '",
                                        email: "' . $email . '",
                                        description: $("#entry-description").val(),
                                        amount: $("#entry-amount").val(),
                                        paid_by: $("#entry-paid-by").val(),
                                        currency: $("#entry-currency").val(),
                                        shares: shares
                                    },
                                    success: function(response) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#add-entry-modal").modal("hide");
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

                            $("#settle-form").submit(function(e) {
                                e.preventDefault();

                                var paid_by = $("#payment-paid-by").val();
                                var paid_to = $("#payment-paid-to").val();
                                if (paid_by === paid_to) {
                                    alert("You cannot pay yourself.");
                                    return;
                                }

                                $(".modal-footer .spinner-border").removeClass("d-none");

                                $.ajax({
                                    url: "' . admin_url('admin-ajax.php') . '",
                                    type: "POST",
                                    data: {
                                        action: "gem_add_payment",
                                        group_name: "' . rawurlencode($group_name) . '",
                                        email: "' . $email . '",
                                        description: $("#payment-description").val(),
                                        amount: $("#payment-amount").val(),
                                        paid_by: paid_by,
                                        paid_to: paid_to,
                                        currency: $("#payment-currency").val()
                                    },
                                    success: function(response) {
                                        $(".modal-footer .spinner-border").addClass("d-none");
                                        $("#settle-modal").modal("hide");
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
                            $("#add-entry-modal").on("hidden.bs.modal", function() {
                                $("#add-entry-form")[0].reset();
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
