<?php
// admin/fix_claimed_rewards_points.php
// This script verifies and corrects claimed rewards point deductions

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';

// Point mapping for each reward type
$reward_points = [
    'Free Wash Load' => 6,
    'Free Dry Load' => 12,
    'Free Wash & Dry Load' => 18
];

// Collect results for display
$results = [];
$total_fixed = 0;

// Get all users with claimed rewards
$users_query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.user_points
                FROM users u
                JOIN claimed_rewards cr ON u.id = cr.user_id
                ORDER BY u.id";

$users_result = $conn->query($users_query);

if ($users_result && $users_result->num_rows > 0) {
    while ($user = $users_result->fetch_assoc()) {
        $user_id = $user['id'];
        $current_points = (int)$user['user_points'];
        
        // Get all claimed rewards for this user with status 'Claimed' or 'Pending'
        $rewards_query = $conn->prepare(
            "SELECT id, reward_name, status, claimed_at 
             FROM claimed_rewards 
             WHERE user_id = ? AND status IN ('Claimed', 'Pending')
             ORDER BY claimed_at ASC"
        );
        $rewards_query->bind_param('i', $user_id);
        $rewards_query->execute();
        $rewards_result = $rewards_query->get_result();
        
        $total_to_deduct = 0;
        $claimed_rewards_list = [];
        
        while ($reward = $rewards_result->fetch_assoc()) {
            $reward_name = $reward['reward_name'];
            $points_for_reward = $reward_points[$reward_name] ?? 0;
            $total_to_deduct += $points_for_reward;
            $claimed_rewards_list[] = [
                'id' => $reward['id'],
                'name' => $reward_name,
                'points' => $points_for_reward,
                'status' => $reward['status'],
                'claimed_at' => $reward['claimed_at']
            ];
        }
        $rewards_query->close();
        
        if ($total_to_deduct > 0) {
            // Calculate new points (deduct all claimed reward points, minimum 0)
            $new_points = max(0, $current_points - $total_to_deduct);
            
            // Update user points
            $update_query = $conn->prepare(
                "UPDATE users SET user_points = ? WHERE id = ?"
            );
            $update_query->bind_param('ii', $new_points, $user_id);
            $update_query->execute();
            $rows_affected = $conn->affected_rows;
            $update_query->close();
            
            $results[] = [
                'user_id' => $user_id,
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'previous_points' => $current_points,
                'new_points' => $new_points,
                'total_deducted' => $total_to_deduct,
                'claimed_rewards' => $claimed_rewards_list,
                'status' => 'FIXED',
                'rows_affected' => $rows_affected
            ];
            
            $total_fixed++;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Claimed Rewards Points - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #6f42c1 0%, #6c757d 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        .summary-box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #6f42c1; }
        .user-result { background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745; }
        .points-changed { font-weight: bold; color: #dc3545; }
        .reward-item { padding: 10px; background: #f8f9fa; margin: 5px 0; border-radius: 4px; }
        .status-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 0.85em; font-weight: 500; }
        .status-fixed { background: #28a745; color: white; }
        .btn-back { margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="admin_home.php" class="btn btn-secondary btn-back"><i class="fas fa-arrow-left"></i> Back to Admin</a>
    
    <div class="header">
        <h1><i class="fas fa-coins"></i> Claimed Rewards Points Verification & Fix</h1>
        <p class="mb-0">Scan and correct claimed rewards point deductions</p>
    </div>
    
    <div class="summary-box">
        <h5><i class="fas fa-check-circle"></i> Fix Summary</h5>
        <p class="mb-0"><strong>Total Users Fixed:</strong> <span style="font-size: 1.3em; color: #6f42c1;"><?php echo $total_fixed; ?></span></p>
    </div>
    
    <?php if (count($results) > 0): ?>
        <h4 class="mb-3">Detailed Results</h4>
        
        <?php foreach ($results as $result): ?>
            <div class="user-result">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <div>
                        <h6 style="margin: 0;">User: <strong><?php echo htmlspecialchars($result['user_name']); ?></strong> (ID: <?php echo $result['user_id']; ?>)</h6>
                    </div>
                    <span class="status-badge status-fixed"><?php echo $result['status']; ?></span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <p class="mb-1"><small class="text-muted">Previous Points</small></p>
                        <p style="font-size: 1.3em; color: #dc3545;"><strong><?php echo $result['previous_points']; ?> pts</strong></p>
                    </div>
                    <div>
                        <p class="mb-1"><small class="text-muted">Total Deducted</small></p>
                        <p style="font-size: 1.3em; color: #ff6b6b;"><strong>-<?php echo $result['total_deducted']; ?> pts</strong></p>
                    </div>
                    <div>
                        <p class="mb-1"><small class="text-muted">New Points</small></p>
                        <p style="font-size: 1.3em; color: #28a745;"><strong><?php echo $result['new_points']; ?> pts</strong></p>
                    </div>
                </div>
                
                <div>
                    <h6 style="margin-bottom: 10px;"><i class="fas fa-gifts"></i> Claimed Rewards</h6>
                    <?php foreach ($result['claimed_rewards'] as $reward): ?>
                        <div class="reward-item">
                            <div style="display: flex; justify-content: space-between;">
                                <div>
                                    <strong><?php echo htmlspecialchars($reward['name']); ?></strong><br>
                                    <small class="text-muted">Claimed: <?php echo date('M d, Y', strtotime($reward['claimed_at'])); ?></small>
                                </div>
                                <div style="text-align: right;">
                                    <span style="background: #e7d4f5; padding: 5px 10px; border-radius: 4px; font-weight: bold;">-<?php echo $reward['points']; ?> pts</span><br>
                                    <small class="text-muted"><?php echo ucfirst($reward['status']); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info" role="alert">
            <i class="fas fa-info-circle"></i> No users with uncorrected claimed rewards found.
        </div>
    <?php endif; ?>
    
    <div class="alert alert-primary" role="alert">
        <i class="fas fa-lightbulb"></i> <strong>Note:</strong> This script automatically deducts points for all users who have claimed rewards (status: Claimed or Pending). The points are now properly synchronized with the claimed_rewards table.
    </div>
</div>

<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
</body>
</html>
