<?php
// includes/edit-profile-functions.php
// Edit profile page functions and data fetching

/**
 * Initialize edit profile page
 * @param mysqli $conn Database connection
 * @return array Array containing user data and notifications
 */
function initEditProfilePage($conn) {
    // Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    
    // Fetch user data
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    $user = mysqli_fetch_assoc($user_query);

    // Fetch notifications for the user
$notification_stmt = $conn->prepare("SELECT id, title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notification_stmt->bind_param("i", $user_id);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();

$notifications = []; // Default to empty array
if ($notification_result) {
    $notifications = $notification_result->fetch_all(MYSQLI_ASSOC);
}
$notification_stmt->close();

    // Initialize messages
    $success_message = "";
    $error_message = "";

    return [
        'user_id' => $user_id,
        'user' => $user,
        'notifications' => $notifications,
        'success_message' => $success_message,
        'error_message' => $error_message
    ];
}

/**
 * Handle profile update form submission
 * @param mysqli $conn Database connection
 * @param int $user_id User ID from session
 * @param array $post_data POST data array
 * @return array Result array with updated data and messages
 */
function handleProfileUpdate($conn, $user_id, $post_data) {
    $success_message = "";
    $error_message = "";
    $updated_user = null;

    // Sanitize input data
    $first_name = mysqli_real_escape_string($conn, $post_data['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn, $post_data['last_name'] ?? '');
    $phone = mysqli_real_escape_string($conn, $post_data['phone'] ?? '');
    $email = mysqli_real_escape_string($conn, $post_data['email'] ?? '');
    $address = mysqli_real_escape_string($conn, $post_data['address'] ?? '');

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First name, last name, and email are required fields.";
        return compact('success_message', 'error_message', 'updated_user');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
        return compact('success_message', 'error_message', 'updated_user');
    }

    // Check if email already exists for another user
    $email_check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'");
    if (mysqli_num_rows($email_check) > 0) {
        $error_message = "This email is already registered with another account.";
        return compact('success_message', 'error_message', 'updated_user');
    }

    // Update user details - NO updated_at column in your table
    $update_query = "
        UPDATE users SET 
        first_name = '$first_name',
        last_name = '$last_name',
        phone = '$phone',
        email = '$email',
        address = '$address'
        WHERE id = '$user_id'
    ";

    if (mysqli_query($conn, $update_query)) {
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
        $updated_user = mysqli_fetch_assoc($user_query);
        
        // Log the update activity (optional)
        logProfileUpdate($conn, $user_id, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email
        ]);
    } else {
        $error_message = "Error updating profile. Please try again.";
        error_log("Profile update failed for user $user_id: " . mysqli_error($conn));
    }

    return compact('success_message', 'error_message', 'updated_user');
}

/**
 * Log profile update activity (optional function)
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param array $update_data Updated data
 */
function logProfileUpdate($conn, $user_id, $update_data) {
    $activity_message = "User updated profile information: ";
    $changes = [];
    
    if (isset($update_data['first_name'])) {
        $changes[] = "First name: " . $update_data['first_name'];
    }
    if (isset($update_data['last_name'])) {
        $changes[] = "Last name: " . $update_data['last_name'];
    }
    if (isset($update_data['email'])) {
        $changes[] = "Email: " . $update_data['email'];
    }
    
    $activity_message .= implode(", ", $changes);
    
    // Insert into activity log if you have such a table
    // $log_query = "INSERT INTO user_activities (user_id, activity_type, description) 
    //               VALUES ('$user_id', 'profile_update', '$activity_message')";
    // mysqli_query($conn, $log_query);
}

/**
 * Handle profile picture upload separately
 * @param mysqli $conn Database connection
 * @param int $user_id User ID from session
 * @param array $file_data $_FILES array
 * @return array Result with success/error message
 */
function handleProfilePictureUpload($conn, $user_id, $file_data) {
    if (!isset($file_data['profile_picture']) || $file_data['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error.'];
    }

    $file = $file_data['profile_picture'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, and GIF files are allowed.'];
    }
    
    // Validate file size
    if ($file_size > $max_size) {
        return ['success' => false, 'message' => 'File size must be less than 2MB.'];
    }
    
    // Generate unique filename
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
    
    // Use absolute path from project root
    $upload_dir = realpath(dirname(__FILE__) . '/../uploads/profile_pictures');
    if (!$upload_dir || !is_dir($upload_dir)) {
        $upload_dir = dirname(__FILE__) . '/../uploads/profile_pictures';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $upload_dir = realpath($upload_dir);
    }
    
    $upload_path = $upload_dir . '/' . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Update database with new filename
        $update_query = "UPDATE users SET profile_picture = '$new_filename' WHERE id = '$user_id'";
        if (mysqli_query($conn, $update_query)) {
            return [
                'success' => true, 
                'message' => 'Profile picture updated successfully!',
                'filename' => $new_filename
            ];
        } else {
            // Delete uploaded file if database update fails
            unlink($upload_path);
            return ['success' => false, 'message' => 'Error updating profile in database.'];
        }
    } else {
        return ['success' => false, 'message' => 'Error uploading file. Please try again.'];
    }
}

/**
 * Get form field value for display
 * @param array $user User data array
 * @param array $updated_user Updated user data (if any)
 * @param string $field Field name
 * @return string Field value
 */
function getFormValue($user, $updated_user, $field) {
    // Use updated user data if available, otherwise use original
    $data_source = $updated_user ?: $user;
    return htmlspecialchars($data_source[$field] ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Handle password change form submission
 * @param mysqli $conn Database connection
 * @param int $user_id User ID from session
 * @param array $post_data POST data array
 * @return array Result array with messages
 */
function handlePasswordChange($conn, $user_id, $post_data) {
    $success_message = "";
    $error_message = "";

    // Get submitted data
    $current_password = $post_data['current_password'] ?? '';
    $new_password = $post_data['new_password'] ?? '';
    $confirm_password = $post_data['confirm_password'] ?? '';

    // Validate required fields
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
        return compact('success_message', 'error_message');
    }

    // Validate new password length
    if (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
        return compact('success_message', 'error_message');
    }

    // Check if passwords match
    if ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
        return compact('success_message', 'error_message');
    }

    // Check if new password is same as current
    if ($new_password === $current_password) {
        $error_message = "New password cannot be the same as your current password.";
        return compact('success_message', 'error_message');
    }

    // Fetch current password from database
    $user_query = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
    if (!$user_query || mysqli_num_rows($user_query) === 0) {
        $error_message = "User not found.";
        return compact('success_message', 'error_message');
    }

    $user = mysqli_fetch_assoc($user_query);
    $stored_password = $user['password'];

    // Verify current password
    if (!password_verify($current_password, $stored_password)) {
        $error_message = "Current password is incorrect.";
        return compact('success_message', 'error_message');
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update password in database
    $hashed_password_escaped = mysqli_real_escape_string($conn, $hashed_password);
    $update_query = "UPDATE users SET password = '$hashed_password_escaped' WHERE id = '$user_id'";

    if (mysqli_query($conn, $update_query)) {
        $success_message = "Password changed successfully!";
        
        // Add notification for password change
        $notif_title = "Security Alert";
        $notif_message = "Your password was successfully updated. If you didn't do this, please contact support immediately.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        if ($notif_stmt) {
            $notif_stmt->bind_param("iss", $user_id, $notif_title, $notif_message);
            $notif_stmt->execute();
            $notif_stmt->close();
        }
        
        // You can optionally log out the user after password change
        // $_SESSION = array();
        // session_destroy();
    } else {
        $error_message = "Error updating password. Please try again.";
        error_log("Password change failed for user $user_id: " . mysqli_error($conn));
    }

    return compact('success_message', 'error_message');
}
?>