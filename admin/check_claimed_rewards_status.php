<?php
// admin/check_claimed_rewards_status.php
// Diagnostic script to verify claimed rewards and user points

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';
require_once '../includes/auth-check-admin.php';

// Point mapping
$reward_points = [
    'Free Wash Load' => 6,
    'Free Dry Load' => 12,
    'Free Wash & Dry Load' => 18
];

// Get all users with claimed rewards
$sql = "SELECT u.id, u.first_name, u.last_name, u.user_points,
               GROUP_CONCAT(cr.reward_name SEPARATOR ', ') as rewards,
               COUNT(cr.id) as reward_count
        FROM users u
        LEFT JOIN claimed_rewards cr ON u.id = cr.user_id AND cr.status IN ('Claimed', 'Pending')
        WHERE u.role = 'user'
        GROUP BY u.id
        ORDER BY u.id";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claimed Rewards Status Check - Jorish Express Laundry</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/lib/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #6f42c1 0%, #6c757d 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .needs-deduction { background-color: #fff3cd; }
        .badge { margin: 3px; }
        .btn-group { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <a href="admin_home.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Admin</a>
    
    <div class="header">
        <h1><i class="fas fa-check-square"></i> Claimed Rewards Status Check</h1>
        <p class="mb-0">View current user points and claimed rewards status</p>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead style="background-color: #6f42c1; color: white;">
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Current Points</th>
                    <th>Claimed Rewards</th>
                    <th>Total Points to Deduct</th>
                    <th>Points After Deduction</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $current_points = (int)$row['user_points'];
                        $reward_count = (int)$row['reward_count'];
                        $total_to_deduct = 0;
                        
                        // Calculate total points to deduct
                        if ($row['rewards']) {
                            $reward_names = array_map('trim', explode(',', $row['rewards']));
                            foreach ($reward_names as $reward_name) {
                                $total_to_deduct += $reward_points[$reward_name] ?? 0;
                            }
                        }
                        
                        $expected_points = max(0, $current_points - $total_to_deduct);
                        $is_correct = ($current_points == $expected_points) || ($reward_count == 0);
                        $row_class = $is_correct ? '' : 'needs-deduction';
                        
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><strong><?php echo $current_points; ?> pts</strong></td>
                            <td>
                                <?php if ($reward_count > 0): ?>
                                    <?php 
                                    $rewards = array_map('trim', explode(',', $row['rewards']));
                                    foreach ($rewards as $reward):
                                        $pts = $reward_points[$reward] ?? 0;
                                    ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($reward); ?> (-<?php echo $pts; ?> pts)</span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $total_to_deduct; ?> pts</td>
                            <td>
                                <strong style="color: <?php echo ($expected_points == $current_points) ? '#28a745' : '#dc3545'; ?>">
                                    <?php echo $expected_points; ?> pts
                                </strong>
                            </td>
                            <td>
                                <?php if ($is_correct && $reward_count > 0): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Correct</span>
                                <?php elseif ($is_correct && $reward_count == 0): ?>
                                    <span class="badge bg-secondary">No Rewards</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> Needs Fix</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="7" class="text-center text-muted">No users found</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <div class="btn-group mt-4">
        <a href="fix_claimed_rewards_points.php" class="btn btn-primary btn-lg">
            <i class="fas fa-tools"></i> Fix All Claimed Rewards Points
        </a>
    </div>
    
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle"></i> <strong>Legend:</strong><br>
        • <span class="badge bg-warning">Needs Fix</span> = User points haven't been deducted for claimed rewards<br>
        • <span class="badge bg-success">Correct</span> = User points have been properly deducted<br>
        • <span class="badge bg-secondary">No Rewards</span> = User has no claimed rewards
    </div>
</div>

<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
</body>
</html>
