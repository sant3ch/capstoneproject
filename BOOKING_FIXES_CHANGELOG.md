# Booking System Fixes - Inventory & Machine Status

## Issue Summary
The laundry booking system had issues where:
1. ❌ Machines remained "Available" after bookings were completed, allowing double-booking
2. ❌ Inventory items weren't properly managed through the booking lifecycle
3. ❌ Cancelled bookings didn't restore inventory or machine availability
4. ❌ Rescheduled bookings didn't properly adjust machine/inventory status

## Solution Overview
Implemented proper state management for machines and inventory throughout the booking lifecycle.

---

## Files Modified

### 1. `user/booking_process.php`
**What Changed:** Added machine status update when booking is created
- When a booking is successfully created, all selected machines are marked as "Unavailable"
- Prevents double-booking and ensures accurate machine availability
- Location: After inventory deduction, before notification creation
- Logs: Tracks machine status updates for debugging

**Code Added:**
```php
// Mark machines as unavailable when booking created
$machine_names_arr = !empty($machine_names) ? array_map('trim', explode(', ', $machine_names)) : [];
if (!empty($machine_names_arr)) {
    foreach ($machine_names_arr as $machine_name) {
        if (!empty($machine_name)) {
            $machine_update = $conn->prepare("UPDATE machines SET status = 'Unavailable' WHERE machine_name = ?");
            // ... execute and log
        }
    }
}
```

---

### 2. `user/process_payment_user.php`
**What Changed:** Added machine status restoration when payment is processed
- When payment is successfully processed and booking status changes to "Completed"
- All machines used in the booking are marked back to "Available"
- Location: After booking status update, before transaction commit
- Ensures machines are freed immediately upon successful payment

**Code Added:**
```php
// Update machine status back to Available after payment
if (!empty($booking['machine_names'])) {
    $machine_list = explode(', ', $booking['machine_names']);
    foreach ($machine_list as $machine_name) {
        $machine_name = trim($machine_name);
        if (!empty($machine_name)) {
            $machine_update = $conn->prepare("UPDATE machines SET status = 'Available' WHERE machine_name = ?");
            // ... execute and log
        }
    }
}
```

---

### 3. `admin/process_payment.php`
**What Changed:** Added same machine status restoration as user payment flow
- Ensures consistency between admin and user payment processing
- Staff/Admin payments now properly free machines
- Location: After booking status update, before transaction commit

---

### 4. `user/cancel_booking.php`
**What Changed:** Added inventory restoration when booking is cancelled
- When a booking is cancelled, all inventory items are restored to stock
- Parses both old (plain name) and new ("Qty x ItemName") detergent formats
- Location: After machine status updates, before notification creation
- Updated success message to reflect both machine and inventory restoration

**Code Added:**
```php
// Restore inventory when booking is cancelled
if (!empty($booking['detergent'])) {
    $detergent_list = explode(', ', $booking['detergent']);
    foreach ($detergent_list as $detergent_entry) {
        $detergent_entry = trim($detergent_entry);
        if (empty($detergent_entry) || $detergent_entry === 'Bring my own detergent') {
            continue;
        }
        
        // Parse "Qty x ItemName" format or use default qty=1
        $qty = 1;
        $item_name = $detergent_entry;
        if (preg_match('/^(\d+)\s*x\s+(.+)$/i', $detergent_entry, $matches)) {
            $qty = intval($matches[1]);
            $item_name = trim($matches[2]);
        }
        
        // Restore inventory
        $inventory_restore = $conn->prepare("UPDATE inventory SET stock_quantity = stock_quantity + ? WHERE item_name = ?");
        // ... execute and log
    }
}
```

---

### 5. `user/reschedule_process.php` (BONUS)
**What Changed:** Added comprehensive machine and inventory status handling during rescheduling
- Frees machines that are no longer needed
- Marks new machines as unavailable
- Restores old inventory items
- Deducts new inventory items
- Ensures rescheduled bookings have proper status management

**Key Features:**
- Compares old vs new machines to handle additions/removals
- Properly handles inventory quantity parsing and adjustments
- All operations logged for debugging
- Maintains data consistency

---

## Booking Lifecycle Now Handles

### Create Booking
1. ✅ Inventory deducted
2. ✅ Machines marked as "Unavailable"

### Cancel Booking
1. ✅ Booking status set to "Cancelled"
2. ✅ Machines marked back to "Available"
3. ✅ Inventory restored

### Reschedule Booking
1. ✅ Old machines freed (if not reused)
2. ✅ New machines marked unavailable (if new)
3. ✅ Old inventory restored
4. ✅ New inventory deducted

### Process Payment (Complete Booking)
1. ✅ Payment recorded
2. ✅ Points awarded
3. ✅ User updated
4. ✅ **Machines marked "Available"** ← NEW
5. ✅ Booking status set to "Completed"

---

## Testing Checklist

### Test 1: Basic Booking
- [ ] Create a booking with specific machines and detergents
- [ ] Check: Machines show as "Unavailable" in database
- [ ] Check: Inventory items are reduced
- [ ] Process payment
- [ ] Check: Machines show as "Available" again
- [ ] Check: Booking status is "Completed"

### Test 2: Cancel Booking
- [ ] Create a booking (note initial inventory)
- [ ] Cancel the booking
- [ ] Check: Machines are "Available"
- [ ] Check: Inventory is restored to original quantity
- [ ] Check: Booking status is "Cancelled"

### Test 3: Reschedule Booking
- [ ] Create booking A with Machines: [W1, W2], Detergent: [Det A, Det B]
- [ ] Reschedule to different machines B [W3, W4] and different detergent [Det C]
- [ ] Check: W1, W2 are "Available" | W3, W4 are "Unavailable"
- [ ] Check: Det A and B inventory restored | Det C inventory deducted

### Test 4: Multiple Bookings
- [ ] Create 2 simultaneous bookings with same machines
- [ ] Verify: Machines are "Unavailable" after first booking
- [ ] Verify: Second booking cannot use same unavailable machines (or shows conflict)
- [ ] Process payment for first booking
- [ ] Verify: Machines become "Available" only after payment

### Test 5: Staff Processing
- [ ] Have staff member process a booking payment through admin interface
- [ ] Verify: Machines are freed properly
- [ ] Verify: Same logic as user payment processing

---

## Database Queries for Manual Verification

### Check booking status with machine info
```sql
SELECT id, status, machine_names, booking_date FROM bookings WHERE id = 123;
```

### Check machine availability
```sql
SELECT machine_name, status FROM machines WHERE machine_name IN ('Washer 1', 'Washer 2', 'Dryer 1');
```

### Check inventory levels
```sql
SELECT item_name, stock_quantity FROM inventory WHERE item_type = 'detergent' ORDER BY item_name;
```

### View booking lifecycle
```sql
SELECT 
    b.id, b.user_id, b.status, 
    b.machine_names, b.detergent, 
    b.booking_date, b.created_at,
    (SELECT COUNT(*) FROM transactions WHERE booking_id = b.id) as payment_count
FROM bookings b 
WHERE id = 123;
```

---

## Notes
- All changes include error logging for troubleshooting
- Database operations check `affected_rows` to ensure success
- Both old and new detergent formats ("ItemName" and "Qty x ItemName") are supported
- Machine names are properly trimmed to handle formatting variations
- All changes are transaction-safe where applicable

## Version
- Date: April 7, 2026
- Status: Ready for Testing
- Syntax: ✅ Verified
