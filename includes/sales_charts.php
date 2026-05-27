<?php
/**
 * Sales Charts Data Functions
 */

/**
 * Fetches weekly sales data
 * 
 * @param mysqli $conn Database connection object
 * @return array Weekly sales data
 */
function getWeeklySalesData($conn, $month = null, $year = null) {
    $where_clause = "WHERE status IN ('approved', 'completed')";
    if ($month && $year) {
        $where_clause .= " AND MONTH(COALESCE(approved_at, payment_date)) = " . (int)$month . " AND YEAR(COALESCE(approved_at, payment_date)) = " . (int)$year;
    } else {
        $where_clause .= " AND DATE(COALESCE(approved_at, payment_date)) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
    }

    $weekly_sales_query = mysqli_query($conn, "
        SELECT DATE(COALESCE(approved_at, payment_date)) AS sale_date, SUM(amount) AS total
        FROM gcash_requests
        $where_clause
        GROUP BY DATE(COALESCE(approved_at, payment_date))
    ");

    $sales_by_date = [];
    if ($weekly_sales_query) {
        while ($row = mysqli_fetch_assoc($weekly_sales_query)) {
            if (isset($sales_by_date[$row['sale_date']])) {
                $sales_by_date[$row['sale_date']] += (float)$row['total'];
            } else {
                $sales_by_date[$row['sale_date']] = (float)$row['total'];
            }
        }
    }

    $weekly_sales = [];
    if ($month && $year) {
        // For monthly filter, we might want to show by date, but let's keep it consistent or show days of the month
        // Actually, the monthlyChart already shows days of the month.
        // Let's make weekly chart show the last 7 days of that month if filtered?
        // Or just the same as before if not current month?
        // Let's just use the sale_date as labels if filtered.
        if ($weekly_sales_query) {
            mysqli_data_seek($weekly_sales_query, 0);
            while ($row = mysqli_fetch_assoc($weekly_sales_query)) {
                $label = date('M d', strtotime($row['sale_date']));
                $weekly_sales[$label] = (float)$row['total'];
            }
        }
    } else {
        for ($i = 6; $i >= 0; $i--) {
            $date_key = date('Y-m-d', strtotime("-{$i} day"));
            $label = date('l', strtotime($date_key));
            $weekly_sales[$label] = $sales_by_date[$date_key] ?? 0;
        }
    }
    
    return $weekly_sales;
}

/**
 * Fetches monthly sales data
 * 
 * @param mysqli $conn Database connection object
 * @return array Monthly sales data
 */
function getMonthlySalesData($conn, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = (int)$month;
    $year = (int)$year;

    $monthly_sales_query = mysqli_query($conn, "
        SELECT DATE_FORMAT(COALESCE(approved_at, payment_date), '%b %d') AS date, SUM(amount) AS total
        FROM gcash_requests
        WHERE MONTH(COALESCE(approved_at, payment_date)) = $month AND YEAR(COALESCE(approved_at, payment_date)) = $year
        AND status IN ('approved', 'completed')
        GROUP BY DATE(COALESCE(approved_at, payment_date)), DATE_FORMAT(COALESCE(approved_at, payment_date), '%b %d')
    ");
    
    $monthly_sales = [];
    if ($monthly_sales_query) {
        while ($row = mysqli_fetch_assoc($monthly_sales_query)) {
            if (isset($monthly_sales[$row['date']])) {
                $monthly_sales[$row['date']] += (float)$row['total'];
            } else {
                $monthly_sales[$row['date']] = (float)$row['total'];
            }
        }
    }
    
    return $monthly_sales;
}

/**
 * Fetches service type sales data (instead of holiday sales)
 * 
 * @param mysqli $conn Database connection object
 * @return array Service type sales data
 */
function getServiceTypeSalesData($conn, $month = null, $year = null) {
    $where_clause = "WHERE gr.status IN ('approved', 'completed') AND gr.amount > 0";
    if ($month && $year) {
        $where_clause .= " AND MONTH(COALESCE(gr.approved_at, gr.payment_date)) = " . (int)$month . " AND YEAR(COALESCE(gr.approved_at, gr.payment_date)) = " . (int)$year;
    }

    $service_sales_query = mysqli_query($conn, "
        SELECT b.service_type, SUM(gr.amount) AS total
        FROM gcash_requests gr
        JOIN bookings b ON gr.booking_id = b.id
        $where_clause
        GROUP BY b.service_type
    ");
    
    $service_sales = [
        'Full Service' => 0,
        'Self Service' => 0,
        'Fold Service' => 0,
        'Other Services' => 0
    ];
    
    if ($service_sales_query) {
        while ($row = mysqli_fetch_assoc($service_sales_query)) {
            $raw_service_types = strtolower((string)$row['service_type']);
            $amount = (float)$row['total'];

            $tokens = array_filter(array_map('trim', explode(',', $raw_service_types)));
            if (empty($tokens)) {
                $tokens = [$raw_service_types];
            }

            $categories = [];
            foreach ($tokens as $token) {
                if (strpos($token, 'self') !== false) {
                    $categories['Self Service'] = true;
                } elseif (strpos($token, 'fold') !== false) {
                    $categories['Fold Service'] = true;
                } elseif (strpos($token, 'full') !== false) {
                    $categories['Full Service'] = true;
                }
            }

            if (empty($categories)) {
                $categories['Other Services'] = true;
            }

            $category_count = count($categories);
            $amount_per_category = $category_count > 0 ? ($amount / $category_count) : $amount;

            foreach (array_keys($categories) as $category) {
                $service_sales[$category] += $amount_per_category;
            }
        }
    }
    
    // Round to 2 decimal places
    foreach ($service_sales as &$value) {
        $value = round($value, 2);
    }
    
    // Remove categories with 0 value
    $service_sales = array_filter($service_sales, function($value) {
        return $value > 0;
    });
    
    // Sort by amount descending
    arsort($service_sales);
    
    return $service_sales;
}

/**
 * Gets today's sales growth percentage
 * 
 * @param mysqli $conn Database connection object
 * @return array Today's sales and growth
 */
function getTodaySalesGrowth($conn, $month = null, $year = null) {
    if ($month && $year) {
        $today_day = date('d');
        $target_month = (int)$month;
        $target_year = (int)$year;
        
        // Ensure the day exists in the target month (e.g., Feb 30 -> Feb 28)
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $target_month, $target_year);
        if ($today_day > $days_in_month) {
            $today_day = $days_in_month;
        }
        
        $target_date = sprintf('%04d-%02d-%02d', $target_year, $target_month, $today_day);
        $prev_date = date('Y-m-d', strtotime($target_date . ' -1 day'));
    } else {
        $target_date = date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime('yesterday'));
    }

    // Today's (or target day's) sales from gcash_requests
    $today_gcash_query = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM gcash_requests
        WHERE DATE(COALESCE(approved_at, payment_date)) = '$target_date' AND status IN ('approved', 'completed')
    ");
    
    $today_sales = 0;
    if ($today_gcash_query) {
        $today_sales = (float)mysqli_fetch_assoc($today_gcash_query)['total'];
    }
    
    // Yesterday's (or prev day's) sales from gcash_requests
    $yesterday_gcash_query = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM gcash_requests
        WHERE DATE(COALESCE(approved_at, payment_date)) = '$prev_date' AND status IN ('approved', 'completed')
    ");
    
    $yesterday_sales = 0;
    if ($yesterday_gcash_query) {
        $yesterday_sales = (float)mysqli_fetch_assoc($yesterday_gcash_query)['total'];
    }
    
    // Calculate growth
    $growth = 0;
    if ($yesterday_sales > 0) {
        $growth = (($today_sales - $yesterday_sales) / $yesterday_sales) * 100;
    } else if ($today_sales > 0) {
        $growth = 100;
    }
    
    return [
        'today_sales' => $today_sales,
        'growth' => round($growth, 1),
        'target_date' => $target_date
    ];
}

/**
 * Gets total sales this month
 * 
 * @param mysqli $conn Database connection object
 * @return float Total monthly sales
 */
function getMonthlyTotalSales($conn, $month = null, $year = null) {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $month = (int)$month;
    $year = (int)$year;

    // From gcash_requests
    $gcash_total_query = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM gcash_requests
        WHERE MONTH(COALESCE(approved_at, payment_date)) = $month AND YEAR(COALESCE(approved_at, payment_date)) = $year
        AND status IN ('approved', 'completed')
    ");
    
    $monthly_total = 0;
    if ($gcash_total_query) {
        $monthly_total = (float)mysqli_fetch_assoc($gcash_total_query)['total'];
    }
    
    return $monthly_total;
}

/**
 * Fetches holiday vs regular sales data
 * Includes ALL payments regardless of payment method
 * 
 * @param mysqli $conn Database connection object
 * @return array Holiday and Regular sales data
 */
function getHolidaySalesData($conn, $month = null, $year = null) {
    $where_month = "";
    if ($month && $year) {
        $where_month = " AND MONTH(COALESCE(approved_at, payment_date)) = " . (int)$month . " AND YEAR(COALESCE(approved_at, payment_date)) = " . (int)$year;
    }

    // Holiday sales from gcash_requests
    $holiday_gcash_query = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM gcash_requests
        WHERE is_holiday = 1 AND status IN ('approved', 'completed') AND amount > 0 $where_month
    ");
    $total_holiday = $holiday_gcash_query ? (float)mysqli_fetch_assoc($holiday_gcash_query)['total'] : 0;
    
    // Regular sales from gcash_requests
    $regular_gcash_query = mysqli_query($conn, "
        SELECT IFNULL(SUM(amount), 0) AS total
        FROM gcash_requests
        WHERE is_holiday = 0 AND status IN ('approved', 'completed') AND amount > 0 $where_month
    ");
    $total_regular = $regular_gcash_query ? (float)mysqli_fetch_assoc($regular_gcash_query)['total'] : 0;

    return [
        'Holiday Sales' => $total_holiday,
        'Regular Sales' => $total_regular
    ];
}

/**
 * Fetches weekend vs weekday sales data
 * Includes ALL payments regardless of payment method
 * 
 * @param mysqli $conn Database connection object
 * @return array Weekend and Weekday sales data
 */
function getWeekendSalesData($conn, $month = null, $year = null) {
    $where_month = "WHERE status IN ('approved', 'completed') AND amount > 0";
    if ($month && $year) {
        $where_month .= " AND MONTH(COALESCE(approved_at, payment_date)) = " . (int)$month . " AND YEAR(COALESCE(approved_at, payment_date)) = " . (int)$year;
    }

    $weekend_sales_query = mysqli_query($conn, "
        SELECT
            CASE WHEN DAYOFWEEK(COALESCE(approved_at, payment_date)) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END AS day_type,
            SUM(amount) AS total
        FROM gcash_requests
        $where_month
        GROUP BY CASE WHEN DAYOFWEEK(COALESCE(approved_at, payment_date)) IN (1, 7) THEN 'Weekend' ELSE 'Weekday' END
    ");
    
    $weekend_data = [
        'Weekend Sales' => 0,
        'Weekday Sales' => 0
    ];
    
    if ($weekend_sales_query) {
        while ($row = mysqli_fetch_assoc($weekend_sales_query)) {
            $day_type = $row['day_type'];
            if (isset($weekend_data[$day_type . ' Sales'])) {
                $weekend_data[$day_type . ' Sales'] += (float)$row['total'];
            } else {
                $weekend_data[$day_type . ' Sales'] = (float)$row['total'];
            }
        }
    }
    
    return $weekend_data;
}
?>