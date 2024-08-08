<?php

// Dashboard Shortcode
function gem_dashboard_shortcode() {
    if (is_user_logged_in()) {
        return '<button id="add-expense-btn" class="btn btn-primary" style="margin-right: 10px;">Add Expense</button>
                <button id="record-payment-btn" class="btn btn-primary" style="margin-right: 10px;">Record Payment</button>
                <button id="add-user-btn" class="btn btn-primary" style="margin-right: 10px;">Add User</button>
                <button id="add-currency-btn" class="btn btn-primary">Add Currency</button>
                <div id="gem-content"></div>
                <script>
                    jQuery(document).ready(function($) {
                        $("#add-expense-btn").click(function() {
                            $("#gem-content").load("' . site_url('/add-expense') . '");
                        });
                        $("#record-payment-btn").click(function() {
                            $("#gem-content").load("' . site_url('/record-payment') . '");
                        });
                        $("#add-user-btn").click(function() {
                            $("#gem-content").load("' . site_url('/add-user') . '");
                        });
                        $("#add-currency-btn").click(function() {
                            $("#gem-content").load("' . site_url('/add-currency') . '");
                        });
                    });
                </script>';
    } else {
        return 'You must be logged in to view this page.';
    }
}

?>
