# Queue Management System - Fix Summary

## Issue Identified
When the "Assign Machine" button was clicked on a booking in the Booked Schedule section, the booking would disappear instead of moving to the Queue section with a 10-second progress bar.

## Root Cause
The system was correctly updating the booking status to `'Queuing (Assigning Machines)'`, BUT after the status change, the page would redirect back to the same filter (e.g., `?filter=pending` which only shows `'Pending / Booked'` bookings). Since the booking was no longer in the 'Pending / Booked' stage, it appeared to disappear.

The booking didn't actually disappear—it was being moved to the "Queuing" section, but the user wasn't being shown that section.

## Solution Implemented

### Change Made to `queue_management.php`
When a machine assignment is successful, the page now redirects to the "Queuing" filter:

```php
// Before: Redirected to the current filter (booking disappeared from view)
header('Location: queue_management.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));

// After: Redirects to the queueing section where the 10-second progress starts
header('Location: queue_management.php?filter=queueing');
```

## Complete Workflow (Now Fixed)

### Step 1: Assign Machine
- User finds a booking in the "Pending / Booked" section
- Clicks the "Assign Machine" button
- System validates that selected machines are available
- Updates booking status to `'Queuing (Assigning Machines)'`
- Marks machines as "Unavailable"

### Step 2: Redirect to Queue Section
- **Page automatically redirects to the Queue section** (`?filter=queueing`)
- User can now see the booking in the queue section
- The booking displays a **10-second progress bar** with animation

### Step 3: Progress Bar Animation (10 Seconds)
- JavaScript `handleQueueingProgress()` function monitors the progress bar
- Progress bar fills from 0% to 100% over 10 seconds
- Blue color with animation: `linear-gradient(90deg, #2196F3, #1976D2)`
- Spinning icon with "Queueing machines..." message
- Percentage counter updates in real-time

### Step 4: Auto-Move to In Process
- After exactly 10 seconds, JavaScript makes AJAX request
- Request calls `complete_queueing` handler
- Backend verifies booking is still in Queuing state
- Updates booking status to `'In Process'`
- Sets `estimated_start_time` and `estimated_completion_time`
- Sends notification to customer
- Page auto-refreshes to show booking in "In Process" section

### Step 5: In Process Progress
- Booking now shows in the "In Process" section
- Progress bar reflects actual time elapsed vs. estimated service time
- Shows time remaining and completion time
- Continues until service is complete

## Technical Details

### Database States
1. `'Pending / Booked'` - Booking confirmed, waiting for machine assignment
2. `'Queuing (Assigning Machines)'` - **NEW: Machines being assigned (10-second queue phase)**
3. `'In Process'` - Laundry is being washed/dried
4. `'Ready for Pickup'` - Service complete, waiting for customer pickup
5. `'Completed / Picked Up'` - Customer has picked up
6. `'Missed Pickup'` - Overdue pickup

### JavaScript Functions Involved
- `handleQueueingProgress()` - Monitors 10-second queue timer
- `fetch()` - Sends AJAX request after 10 seconds
- `refreshProgressBars()` - Refreshes in-process progress bars

### AJAX Endpoint
- **URL**: `queue_management.php` (POST)
- **Parameters**: `complete_queueing=1`, `booking_id`, `start_time`, `end_time`
- **Response**: JSON with `ok` and `message` fields

## Benefits of This Fix

1. **Better UX**: Users can see bookings transition through the queue
2. **Automation**: No manual intervention needed for queue to in-process transition
3. **Transparency**: 10-second timer shows machines are being loaded
4. **Seamless workflow**: Bookings flow automatically through stages
5. **Visual feedback**: Progress bar keeps staff informed

## Testing Checklist

- [ ] Click "Assign Machine" on a Pending/Booked booking
- [ ] Verify page redirects to Queue section (filter=queueing)
- [ ] Verify booking appears with 10-second progress bar
- [ ] Verify progress bar fills from 0% to 100% over 10 seconds
- [ ] Verify booking auto-moves to "In Process" after 10 seconds
- [ ] Verify page auto-refreshes when moving to In Process
- [ ] Verify booking is no longer visible in Queue section after 10 seconds
- [ ] Verify customer receives notification of status change
- [ ] Verify machines are marked as "Unavailable" during queueing
- [ ] Verify error handling if machines become unavailable

## Notes

- The 10-second duration represents the time laundry is being loaded into machines
- If machines are released (become unavailable) during this period, appropriate errors are handled
- The system uses database transactions to ensure data consistency
- Progress bar updates every 100ms for smooth visual feedback
- Auto-refresh occurs after queueing is complete to show updated page

## Troubleshooting

**Issue**: Booking still disappears after clicking "Assign Machine"
- **Check**: Verify the redirect is working (should go to ?filter=queueing)
- **Solution**: Clear browser cache, refresh page

**Issue**: Progress bar doesn't move
- **Check**: Verify JavaScript is enabled in browser
- **Solution**: Open browser console (F12) to check for errors

**Issue**: Booking returns to Pending after 10 seconds (doesn't move to In Process)
- **Check**: Verify database connectivity
- **Check**: Check nginx/Apache error logs
- **Solution**: Manually update the booking status using the dropdown

**Issue**: Multiple bookings in queue but progress bars not syncing
- **Expected**: Each booking has its own 10-second timer based on when it entered the queue
- **Note**: This is intentional - they queue independently

## Files Modified
- `admin/queue_management.php` - Updated redirect logic for Assign Machine button

## Deployment Notes
- No database changes required
- No new files needed
- Backward compatible with existing bookings
- Can be deployed immediately
