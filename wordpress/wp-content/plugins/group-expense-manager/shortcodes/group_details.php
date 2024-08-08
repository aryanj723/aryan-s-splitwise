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
        $output .= '<thead><tr><th>Currency Information</th><th>Creator & Members</th><th>Balances</th></tr></thead>';
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

        // Creator and Members
        $output .= '<td>';
        $output .= '<div class="creator">Creator: ' . htmlspecialchars($group_details['creator_email']) . '</div>';
        $output .= '<div class="members">Members:</div><ul>';
        foreach ($group_details['members'] as $member) {
            if ($member == $email) {
                $output .= '<li>You</li>';
            } else {
                $user_info = get_user_by('email', $member);
                $output .= '<li>' . ($user_info ? $user_info->display_name : $member) . '</li>';
            }
        }
        $output .= '</ul>';
        $output .= '</td>'; // Close Creator & Members column

        // Balances
        $output .= '<td>';
        if (!empty($group_details['balances'])) {
            $output .= '<ul>';
            foreach ($group_details['balances'] as $balance) {
                $debtor = ($balance[0] == $email) ? 'You' : get_user_display_name($balance[0]);
                $creditor = ($balance[1] == $email) ? 'You' : get_user_display_name($balance[1]);
                $output .= '<li>' . $debtor . ' should pay ' . htmlspecialchars($balance[2]) . ' ' . htmlspecialchars($balance[3]) . ' to ' . $creditor . '</li>';
            }
            $output .= '</ul>';
        }
        $output .= '</td>'; // Close Balances column

        $output .= '</tr></tbody></table>';

        $output .= '<div class="button-container">';
        $output .= '<button id="add-entry-btn" class="btn btn-primary" style="margin-right: 10px;">Add Expense</button>';
        $output .= '<button id="settle-btn" class="btn btn-primary">Record Payment</button>';
        $output .= '</div>';

        $output .= '<div id="group-entries">';
        $output .= '<h4>Expenses</h4>' . gem_display_expenses($group_details['entries']);
        $output .= '<h4>Payments</h4>' . gem_display_payments($group_details['entries']);
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
                                        <input type="number" id="entry-amount" class="form-control" required>
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
            $output .= '<div>' . $display_name . ':</div>';
            $output .= '<input type="number" class="form-control share-input" name="share-' . htmlspecialchars($member) . '" placeholder="Amount for ' . $display_name . '">';
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

                            $("#add-entry-form").submit(function(e) {
                                e.preventDefault();
                                var shares = {};
                                var totalShares = 0;
                                $(".share-input").each(function() {
                                    var share = parseFloat($(this).val()) || 0;
                                    shares[$(this).attr("name").split("-")[1]] = share;
                                    totalShares += share;
                                });

                                var amount = parseFloat($("#entry-amount").val());
                                if (totalShares !== amount) {
                                    alert("Shares do not add up to the total amount.");
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

                            $("img.lazy").lazyload({
                                effect : "fadeIn"
                            });
                        });
                    </script>';

        return $output;
    }
}

?>
