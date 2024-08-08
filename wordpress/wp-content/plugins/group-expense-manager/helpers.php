<?php

function gem_get_entry_display($entry) {
    $logged_in_user_email = wp_get_current_user()->user_email;
    $paid_by_display = ($entry['paid_by'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['paid_by']);
    $paid_to_display = isset($entry['paid_to']) ? (($entry['paid_to'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['paid_to'])) : '';
    $added_by_display = ($entry['added_by'] == $logged_in_user_email) ? 'You' : get_user_display_name($entry['added_by']);
    return [
        'paid_by' => $paid_by_display,
        'paid_to' => $paid_to_display,
        'added_by' => $added_by_display,
    ];
}

function get_user_display_name($email) {
    if ($user = get_user_by('email', $email)) {
        return $user->display_name;
    }
    return $email;
}

function gem_display_expenses($entries) {
    usort($entries, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $logged_in_user_email = wp_get_current_user()->user_email;
    $output = '<table class="table">';
    $output .= '<thead><tr><th>Description</th><th>Amount</th><th>Currency</th><th>Paid By</th><th>Shares</th><th>Date</th><th>Added By</th></tr></thead><tbody>';
    foreach ($entries as $entry) {
        if ($entry['type'] !== 'settlement') {
            $display = gem_get_entry_display($entry);
            $class = $entry['cancelled'] ? ' class="cancelled"' : '';
            $output .= '<tr' . $class . '>';
            $output .= '<td>' . $entry['description'] . '</td>';
            $output .= '<td>' . $entry['amount'] . '</td>';
            $output .= '<td>' . $entry['currency'] . '</td>';
            $output .= '<td>' . $display['paid_by'] . '</td>';
            $output .= '<td>';
            if (isset($entry['shares'])) {
                $output .= implode(', ', array_map(
                    function($k, $v) use ($logged_in_user_email) {
                        $display_name = ($k == $logged_in_user_email) ? 'You' : get_user_display_name($k);
                        return $display_name . ': ' . $v;
                    },
                    array_keys($entry['shares']),
                    $entry['shares']
                ));
            } else {
                $output .= 'N/A';
            }
            $output .= '</td>';
            $output .= '<td>' . $entry['date'] . '</td>'; 
            $output .= '<td>' . $display['added_by'] . '</td>';
            $output .= '</tr>';
        }
    }
    $output .= '</tbody></table>';
    return $output;
}

function gem_display_payments($entries) {
    usort($entries, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    $logged_in_user_email = wp_get_current_user()->user_email;
    $output = '<table class="table">';
    $output .= '<thead><tr><th>Description</th><th>Amount</th><th>Currency</th><th>Date</th><th>Added By</th></tr></thead><tbody>';
    foreach ($entries as $entry) {
        if ($entry['type'] === 'settlement') {
            $display = gem_get_entry_display($entry);
            $class = $entry['cancelled'] ? ' class="cancelled"' : '';
            $output .= '<tr' . $class . '>';
            $output .= '<td>' . $display['paid_by'] . ' paid ' . $display['paid_to'] . '</td>';
            $output .= '<td>' . $entry['amount'] . '</td>';
            $output .= '<td>' . $entry['currency'] . '</td>';
            $output .= '<td>' . $entry['date'] . '</td>'; 
            $output .= '<td>' . $display['added_by'] . '</td>';
            $output .= '</tr>';
        }
    }
    $output .= '</tbody></table>';
    return $output;
}

?>
