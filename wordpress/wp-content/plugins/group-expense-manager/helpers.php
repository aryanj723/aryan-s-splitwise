<?php

function gem_get_entry_display($entry, $members) {
    $logged_in_user_email = wp_get_current_user()->user_email;
    $paid_by_display = ($entry['paid_by'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['paid_by'], $members);
    $paid_to_display = isset($entry['paid_to']) ? (($entry['paid_to'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['paid_to'], $members)) : '';
    $added_by_display = ($entry['added_by'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['added_by'], $members);
    return [
        'paid_by' => $paid_by_display,
        'paid_to' => $paid_to_display,
        'added_by' => $added_by_display,
    ];
}

function get_user_display_name($email, $members) {
    if ($user = get_user_by('email', $email)) {
        $display_name = $user->display_name;
        
        // Count how many members have the same display name
        $display_name_count = 0;
        foreach ($members as $member) {
            $member_user = get_user_by('email', $member);
            if ($member_user && $member_user->display_name === $display_name) {
                $display_name_count++;
            }
        }

        // If display name is unique, return it, otherwise return the email
        return $display_name_count > 1 ? $email : $display_name;
    }
    
    // Return email as fallback if user is not found
    return $email;
}

function gem_display_expenses($entries, $members) {
    usort($entries, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $logged_in_user_email = wp_get_current_user()->user_email;
    $output = '<table class="table table-striped table-responsive" class="table">';
    $output .= '<thead><tr><th>Description</th><th>Amount</th><th>Currency</th><th>Paid By</th><th>Shares</th><th>Date</th><th>Added By</th></tr></thead><tbody>';
    
    foreach ($entries as $entry) {
        if ($entry['type'] !== 'settlement') {
            $display = gem_get_entry_display($entry, $members);
            $class = $entry['cancelled'] ? ' style="text-decoration: line-through; color: grey;"' : '';
            $output .= '<tr' . $class . '>';
            $output .= '<td>' . $entry['description'] . '</td>';
            $output .= '<td>' . $entry['amount'] . '</td>';
            $output .= '<td>' . $entry['currency'] . '</td>';
            $output .= '<td>' . $display['paid_by'] . '</td>';
            $output .= '<td>';

            if (isset($entry['shares'])) {
                // Fix: Pass $members into the scope of the array_map callback
                $output .= implode(', ', array_map(
                    function($k, $v) use ($logged_in_user_email, $members) {
                        $display_name = ($k == $logged_in_user_email) ? 'You' : get_user_display_name($k, $members);
                        return $display_name . ': ' . $v;
                    },
                    array_keys($entry['shares']),
                    $entry['shares']
                ));
            } else {
                $output .= 'N/A';
            }
            $output .= '</td>';

            // Parsing and formatting the date
            $raw_date = $entry['date'];
            $formatted_date = htmlspecialchars($raw_date); // Default to the raw date

            // Attempt to format the date
            $date_parts = explode(':', $raw_date);
            if (count($date_parts) >= 4) {
                $datetime_str = $date_parts[0] . ':' . $date_parts[1] . ':' . $date_parts[2];
                $milliseconds = $date_parts[3];
                try {
                    $date = new DateTime($datetime_str);
                    $formatted_date = $date->format('M j, Y \a\t g:i:s') . ':' . $milliseconds . ' ' . $date->format('A') . ' UTC';
                } catch (Exception $e) {
                    // Use raw date if formatting fails
                }
            }

            $output .= '<td>' . $formatted_date . '</td>';
            $output .= '<td>' . $display['added_by'] . '</td>';
            $output .= '</tr>';
        }
    }
    $output .= '</tbody></table>';
    return $output;
}


function gem_display_payments($entries, $members) {
    usort($entries, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $logged_in_user_email = wp_get_current_user()->user_email;
    $output = '<table class="table table-striped table-responsive" class="table">';
    $output .= '<thead><tr><th>Description</th><th>Amount</th><th>Currency</th><th>Date</th><th>Added By</th></tr></thead><tbody>';
    
    foreach ($entries as $entry) {
        if ($entry['type'] === 'settlement') {
            $display = gem_get_entry_display($entry, $members);
            $class = $entry['cancelled'] ? ' class="cancelled"' : '';
            $output .= '<tr' . $class . '>';
            $output .= '<td>' . $display['paid_by'] . ' paid ' . $display['paid_to'] . '</td>';
            $output .= '<td>' . $entry['amount'] . '</td>';
            $output .= '<td>' . $entry['currency'] . '</td>';

            // Parsing and formatting the date
            $raw_date = $entry['date'];
            $formatted_date = htmlspecialchars($raw_date); // Default to the raw date

            // Attempt to format the date
            $date_parts = explode(':', $raw_date);
            if (count($date_parts) >= 4) {
                $datetime_str = $date_parts[0] . ':' . $date_parts[1] . ':' . $date_parts[2];
                $milliseconds = $date_parts[3];
                try {
                    $date = new DateTime($datetime_str);
                    $formatted_date = $date->format('M j, Y \a\t g:i:s') . ':' . $milliseconds . ' ' . $date->format('A') . ' UTC';
                } catch (Exception $e) {
                    // Use raw date if formatting fails
                }
            }

            $output .= '<td>' . $formatted_date . '</td>';
            $output .= '<td>' . $display['added_by'] . '</td>';
            $output .= '</tr>';
        }
    }
    $output .= '</tbody></table>';
    return $output;
}


?>
