<?php
/**
 * Utility functions for Philippine Holidays
 */

function getPhilippineHoliday($date_str) {
    $date = date('Y-m-d', strtotime($date_str));
    $year = date('Y', strtotime($date_str));
    $md = date('m-d', strtotime($date_str));

    // Fixed Holidays
    $fixed_holidays = [
        '01-01' => "New Year's Day",
        '02-25' => "EDSA People Power Anniversary",
        '04-09' => "Araw ng Kagitingan",
        '05-01' => "Labor Day",
        '06-12' => "Independence Day",
        '08-21' => "Ninoy Aquino Day",
        '11-01' => "All Saints' Day",
        '11-02' => "All Souls' Day",
        '11-30' => "Bonifacio Day",
        '12-08' => "Feast of the Immaculate Conception",
        '12-24' => "Christmas Eve",
        '12-25' => "Christmas Day",
        '12-30' => "Rizal Day",
        '12-31' => "Last Day of the Year"
    ];

    if (isset($fixed_holidays[$md])) {
        return $fixed_holidays[$md];
    }

    // Moveable Holidays for 2026
    if ($year == 2026) {
        $moveable_2026 = [
            '2026-04-02' => "Maundy Thursday",
            '2026-04-03' => "Good Friday",
            '2026-04-04' => "Black Saturday",
            '2026-04-05' => "Easter Sunday",
            '2026-08-31' => "National Heroes Day", // Last Monday of August
            '2026-03-20' => "Eid'l Fitr", // Tentative
            '2026-05-27' => "Eid'l Adha" // Tentative
        ];

        if (isset($moveable_2026[$date])) {
            return $moveable_2026[$date];
        }
    }
    
    // Fallback for National Heroes Day (Last Monday of August)
    if ($md >= '08-25' && $md <= '08-31') {
        $day_of_week = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
        $is_last_monday = ($day_of_week == 1 && date('m', strtotime($date . ' + 7 days')) == '09');
        if ($is_last_monday) {
            return "National Heroes Day";
        }
    }

    return null;
}

function isPhilippineHoliday($date_str) {
    return getPhilippineHoliday($date_str) !== null;
}

/**
 * Returns an array of holidays for a given month and year
 */
function getHolidaysForMonth($month, $year) {
    $holidays = [];
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $holiday = getPhilippineHoliday($date);
        if ($holiday) {
            $holidays[$date] = $holiday;
        }
    }
    
    return $holidays;
}
?>
