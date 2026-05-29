<?php
/**
 * Helpers for admin-managed marketing content.
 * Public pages and admin pages include this to read CMS data.
 * All read helpers tolerate missing tables/empty data by returning
 * empty arrays / defaults so pages never crash before the migration runs.
 */

if (!function_exists('cms_all_settings')) {

    /** Load every site_settings row once per request (cached). */
    function cms_all_settings($conn) {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $res = @mysqli_query($conn, "SELECT setting_key, setting_value FROM site_settings");
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
        return $cache;
    }

    /** Get one setting value, or $default if not set. */
    function getSetting($conn, $key, $default = '') {
        $all = cms_all_settings($conn);
        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    /** Insert or update a setting. Returns true on success. */
    function upsertSetting($conn, $key, $value, $group = 'general') {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO site_settings (setting_key, setting_value, setting_group)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, "sss", $key, $value, $group);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    /** Active testimonials, ordered. */
    function getActiveTestimonials($conn) {
        $rows = [];
        $res = @mysqli_query($conn,
            "SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }

    /** Active core values, ordered. */
    function getCoreValues($conn) {
        $rows = [];
        $res = @mysqli_query($conn,
            "SELECT * FROM core_values WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }

    /** Active why-choose-us bubbles, ordered. */
    function getWhyItems($conn) {
        $rows = [];
        $res = @mysqli_query($conn,
            "SELECT * FROM why_choose_us_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }

    /**
     * Published blog posts (newest first), optional limit.
     * Ordered by sort_order then most recent.
     */
    function getPublishedPosts($conn, $limit = null) {
        $rows = [];
        $sql = "SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC, id DESC";
        if ($limit !== null) {
            $limit = (int)$limit;
            $sql .= " LIMIT $limit";
        }
        $res = @mysqli_query($conn, $sql);
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }

    /** Single published post by id, or null. */
    function getPostById($conn, $id) {
        $id = (int)$id;
        $stmt = mysqli_prepare($conn,
            "SELECT * FROM blog_posts WHERE id = ? AND is_published = 1 LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    /** Services keyed by service_name for slot mapping on public pages. */
    function getServicesByName($conn) {
        $map = [];
        $res = @mysqli_query($conn, "SELECT * FROM services");
        if ($res) while ($r = mysqli_fetch_assoc($res)) $map[$r['service_name']] = $r;
        return $map;
    }

    /** Price of a named service formatted with no decimals, fallback to $default. */
    function svcPrice($svcMap, $name, $default = 0) {
        return isset($svcMap[$name]['price']) ? (float)$svcMap[$name]['price'] : (float)$default;
    }
}
?>
