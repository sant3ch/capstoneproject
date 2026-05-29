<?php
/**
 * Migration: admin-managed marketing content.
 * Run once via browser: http://localhost/capstoneproject/migrate_marketing_content.php
 * Safe to re-run (CREATE TABLE IF NOT EXISTS, ON DUPLICATE KEY, empty-table seed guards).
 * Leaves the existing `services` table untouched.
 */
include 'config.php';
header('Content-Type: text/plain');

function run($conn, $label, $sql) {
    echo "-- $label\n";
    if (mysqli_query($conn, $sql)) {
        echo "Success\n\n";
    } else {
        echo "Error: " . mysqli_error($conn) . "\n\n";
    }
}

// ---- Tables -------------------------------------------------------------
run($conn, "create site_settings", "
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_group` VARCHAR(50) DEFAULT 'general',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

run($conn, "create blog_posts", "
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(80) DEFAULT NULL,
  `excerpt` VARCHAR(500) DEFAULT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `read_minutes` INT(11) DEFAULT 5,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

run($conn, "create testimonials", "
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `author_name` VARCHAR(120) NOT NULL,
  `initials` VARCHAR(4) DEFAULT NULL,
  `rating` TINYINT(1) NOT NULL DEFAULT 5,
  `quote` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

run($conn, "create core_values", "
CREATE TABLE IF NOT EXISTS `core_values` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `icon_class` VARCHAR(80) DEFAULT 'fas fa-star',
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

run($conn, "create why_choose_us_items", "
CREATE TABLE IF NOT EXISTS `why_choose_us_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `icon_class` VARCHAR(80) DEFAULT 'fas fa-star',
  `label` VARCHAR(120) NOT NULL,
  `accent` ENUM('default','orange') NOT NULL DEFAULT 'default',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ---- site_settings seed (idempotent via ON DUPLICATE KEY) ---------------
run($conn, "seed site_settings", "
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('about_title', 'ABOUT US', 'about'),
('about_tagline', 'Your Trusted Partner in Laundry Care', 'about'),
('about_description', 'We are professionals and are committed to providing quality laundry and dry cleaning services. With years of experience and dedication, we ensure that every garment receives the care and attention it deserves.', 'about'),
('about_image', 'assets/images/aboutus.png', 'about'),
('home_why_intro', 'At Jorish Express Laundry, we understand that you have many options when it comes to laundry services.', 'home'),
('svc_self_desc', 'Enjoy our modern, efficient self-service washers and dryers. Ideal for quick and budget-friendly laundry.', 'services'),
('svc_full_desc', 'Our professional staff handles everything — washing, drying, and folding — so you don''t have to.', 'services'),
('svc_capacity_note', '8kg per load capacity', 'services'),
('svc_wash_dry_fold_label', 'Wash, dry & fold', 'services'),
('svc_wash_dry_fold_price', '175', 'services')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`");

// ---- List-table seeds (only when empty, so re-runs do not duplicate) ----
function seedIfEmpty($conn, $table, $label, $sql) {
    $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$table`");
    $row = $res ? mysqli_fetch_assoc($res) : ['c' => 1];
    if ((int)$row['c'] === 0) {
        run($conn, $label, $sql);
    } else {
        echo "-- $label\nSkipped (table not empty)\n\n";
    }
}

seedIfEmpty($conn, 'testimonials', "seed testimonials", "
INSERT INTO `testimonials` (`author_name`, `initials`, `rating`, `quote`, `sort_order`) VALUES
('Maria Reyes', 'MR', 5, 'I can''t thank Jorish Express enough for their impeccable service. My clothes have never looked better, and the convenience of booking online is absolutely a lifesaver!', 1),
('Juan Cruz', 'JC', 5, 'I''ve been using Jorish Express for months now, and I''m consistently impressed by their attention to detail and commitment to customer satisfaction. The loyalty rewards are a great bonus!', 2)");

seedIfEmpty($conn, 'core_values', "seed core_values", "
INSERT INTO `core_values` (`icon_class`, `title`, `description`, `sort_order`) VALUES
('fas fa-headset', 'Personalized Experience', 'You can always reach us for your laundry concerns. Call or message us - we are happy to help!', 1),
('fas fa-award', 'Quality', 'We take utmost care of your clothes, segregating whites and colored clothes. We use gentle yet effective detergents to ensure they won''t damage your fabrics.', 2),
('fas fa-mobile-alt', 'Convenience', 'We simplify the booking request. Simply book through our in-app store via Facebook Messenger and we''ll handle your laundry seamlessly.', 3)");

seedIfEmpty($conn, 'why_choose_us_items', "seed why_choose_us_items", "
INSERT INTO `why_choose_us_items` (`icon_class`, `label`, `accent`, `sort_order`) VALUES
('fas fa-award', 'Expertise and Experience', 'default', 1),
('fas fa-clock', 'Timely Service', 'default', 2),
('fas fa-smile', 'Customer Satisfaction', 'default', 3),
('fas fa-star', 'Exceptional Quality', 'orange', 4),
('fas fa-concierge-bell', 'Convenience', 'orange', 5),
('fas fa-heart', 'Personalized Care', 'default', 6),
('fas fa-tag', 'Transparent Pricing', 'orange', 7)");

seedIfEmpty($conn, 'blog_posts', "seed blog_posts", "
INSERT INTO `blog_posts` (`title`, `category`, `excerpt`, `content`, `read_minutes`, `is_published`, `sort_order`) VALUES
('Essential Laundry Hacks Every Homeowner Should Know', 'Tips', 'Discover simple tricks to extend the life of your clothes and keep them looking fresh.', 'Discover simple tricks to extend the life of your clothes and keep them looking fresh.\n\nSorting by color and fabric, washing in cold water, and air-drying delicate items are just a few habits that make a big difference over time.', 5, 1, 1),
('The Ultimate Guide to Removing Common Stains', 'Guide', 'Learn professional techniques to tackle the most stubborn stains on any fabric type.', 'Learn professional techniques to tackle the most stubborn stains on any fabric type.\n\nFrom coffee to grease, acting quickly and using the right pre-treatment is the key to keeping your garments spotless.', 7, 1, 2),
('Efficient Laundry Sorting Techniques for Busy Families', 'Family', 'Smart sorting strategies that save time and protect your family''s clothing from damage.', 'Smart sorting strategies that save time and protect your family''s clothing from damage.\n\nLabeled hampers and a simple weekly schedule keep laundry day stress-free for the whole household.', 4, 1, 3)");

echo "Migration complete.\n";
?>
