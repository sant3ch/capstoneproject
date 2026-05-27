<?php
//Author Bryce
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Notifications</title>
    <link rel="stylesheet" href="../assets/lib/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <h3 class="mb-4">📬 Notifications</h3>

    <?php if ($result->num_rows > 0): ?>
        <ul class="list-group">
            <?php while ($row = $result->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start <?php echo $row['is_read'] ? '' : 'list-group-item-warning'; ?>">
                    <div>
                        <div class="fw-bold">🧺 <?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></div>
                       <?php 
    $messageData = json_decode($row['message'], true); 
    $queueNumber = $messageData['queue_number'] ?? 'N/A';
    $pickupTime = $messageData['pickup_time'] ?? 'N/A';
    $name = $messageData['name'] ?? 'Customer';
?>

<div>
    Your queue number is <strong><?= $queueNumber ?></strong>.
    Please pick up your laundry on <strong><?= $pickupTime ?></strong>.
    Thank you for using Jorish Laundry!

    <!-- 🔘 Screenshot Queue Number Button -->
    <br>
    <button class="btn btn-sm btn-outline-primary mt-2" 
        onclick='showQueueModal("<?= $queueNumber ?>", "<?= $name ?>", "<?= $pickupTime ?>")'>
        Screenshot Queue Number
    </button>
</div>

                    </div>
                    <?php if (!$row['is_read']): ?>
                        <span class="badge bg-primary rounded-pill">New</span>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <div class="alert alert-info">No notifications yet.</div>
    <?php endif; ?>

</div>
<!-- 📸 Modal for Screenshot -->
<div class="modal fade" id="queueModal" tabindex="-1" aria-labelledby="queueModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Queue Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <p><strong>Queue Number:</strong> <span id="modalQueueNumber"></span></p>
        <p><strong>Name:</strong> <span id="modalName"></span></p>
        <p><strong>Pick-up Time:</strong> <span id="modalTime"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/lib/js/bootstrap.bundle.min.js"></script>
<script>
function showQueueModal(queue, name, time) {
    document.getElementById('modalQueueNumber').innerText = queue;
    document.getElementById('modalName').innerText = name;
    document.getElementById('modalTime').innerText = time;

    const myModal = new bootstrap.Modal(document.getElementById('queueModal'));
    myModal.show();
}
</script>


</body>
</html>

<?php
$stmt->close();
$conn->close();
?>



