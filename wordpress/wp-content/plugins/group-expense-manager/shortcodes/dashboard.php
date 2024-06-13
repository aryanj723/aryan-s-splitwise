<?php

// Dashboard Shortcode
function gem_dashboard_shortcode() {
    if (is_user_logged_in()) {
        return '<button id="create-group-btn" class="btn btn-primary" style="margin-right: 10px;">Add Expense</button>
                <button id="record-payment-btn" class="btn btn-primary">Record Payment</button>
                <div id="gem-content"></div>
                <script>
                    jQuery(document).ready(function($) {
                        $("#create-group-btn").click(function() {
                            $("#gem-content").load("' . site_url('/add-expense') . '");
                        });
                        $("#record-payment-btn").click(function() {
                            $("#gem-content").load("' . site_url('/record-payment') . '");
                        });
                    });
                </script>';
    } else {
        return 'You must be logged in to view this page.';
    }
}

?>
