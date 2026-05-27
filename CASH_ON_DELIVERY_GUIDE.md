# Cash on Delivery Payment Feature Guide

## Overview
The Cash on Delivery (COD) payment feature allows customers to pay for their laundry services directly to delivery staff upon delivery of their clean laundry. This feature complements the existing GCASH payment option, providing customers with flexible payment options.

## System Architecture

### Files Involved

#### Backend
- **`user/submit_cash_request.php`** - Main endpoint for submitting cash on delivery payment requests
  - Validates user and booking information
  - Checks for existing pending cash requests
  - Calculates the booking amount using database pricing
  - Creates a payment request record in `gcash_requests` table
  - Sends notifications to both admin and user
  - Returns JSON response with request details

#### Frontend
- **`assets/js/cash-payment.js`** - Client-side JavaScript handler
  - Manages cash payment modal interactions
  - Collects booking and amount information
  - Submits requests to the backend
  - Handles success/error responses
  - Updates UI with confirmation messages

#### HTML/Views
- **`user/booking_confirmation.php`** - Primary entry point for cash payment requests
  - Contains `cashModal` - form for submitting cash on delivery request
  - Contains `cashSuccessModal` - confirmation message after successful submission
  - Loads the `cash-payment.js` script

## Database Schema

### Table: `gcash_requests`
The cash on delivery requests use the existing `gcash_requests` table with a payment method identifier:

```sql
INSERT INTO gcash_requests (
    booking_id,           -- Foreign key to bookings table
    user_id,              -- Foreign key to users table
    customer_name,        -- Customer's full name
    payment_method,       -- 'Cash on Delivery' for this feature
    amount,               -- Calculated amount to be paid
    reference_number,     -- Unique reference (COD-YYYYMMDD-BOOKINGID)
    status,               -- 'pending', 'approved', 'completed', 'rejected'
    requested_at          -- Timestamp of request submission
) VALUES (...)
```

## Payment Request Flow

### 1. User Submission (Booking Confirmation Page)
- User books laundry service through `book-now.php`
- Redirected to `booking_confirmation.php` with booking details
- User clicks "Pay with Cash" button
- `cashModal` displays with:
  - Booking reference number
  - Customer name
  - Services requested
  - Estimated amount to pay
  - Usage instructions

### 2. Request Processing
- User confirms cash on delivery by clicking "Confirm Cash on Delivery"
- `cash-payment.js` validates the information
- Sends POST request to `submit_cash_request.php` with:
  ```javascript
  {
    booking_id: <id>,
    payment_method: "Cash on Delivery",
    amount: <calculated_amount>
  }
  ```

### 3. Backend Validation
The `submit_cash_request.php` performs:
- User authentication check
- Booking validity verification
- Duplicate request prevention
- Amount calculation using current database pricing
- Transaction-based database update

### 4. Request Creation
Upon validation success:
- New record in `gcash_requests` table created with status='pending'
- Admin notification generated ("New Cash on Delivery Request")
- User notification generated with request details
- Booking status updated to 'Pending'
- Transaction committed

### 5. User Confirmation
- Success modal (`cashSuccessModal`) displays with:
  - Booking ID
  - Submission timestamp
  - Amount to pay (COD)
  - Status badge showing "Pending Approval"
- 10-second automatic redirect to user profile

## Admin Workflow

### 1. Request Approval
When admin receives a cash on delivery request:
1. Admin reviews request in admin dashboard
2. Confirms customer details and amount
3. Sets delivery location/date
4. Updates request status to 'approved'

### 2. Delivery Process
- Delivery staff receives package with COD amount information
- Staff collects exact amount from customer
- Payment is recorded in system

### 3. Booking Completion
- Once payment is received, request status updates to 'completed'
- Booking status updates to 'Completed'
- Customer receives notification of booking completion

## Key Features

### Validation
- **User Authentication**: Ensures only logged-in users can submit requests
- **Booking Verification**: Validates booking exists and belongs to user
- **Duplicate Prevention**: Prevents multiple pending/approved requests for same booking
- **Amount Calculation**: Uses current database service prices for accurate calculation

### Error Handling
```javascript
// Possible error responses from backend:
{
  status: 'error',
  message: 'Unauthorized access. Please login.'
}

{
  status: 'error',
  message: 'Invalid booking ID'
}

{
  status: 'error',
  message: 'Booking not found or does not belong to you'
}

{
  status: 'error',
  message: 'A cash on delivery request is already pending for this booking'
}

{
  status: 'error',
  message: 'Unable to calculate amount. Please contact support.'
}
```

### Success Response
```javascript
{
  status: 'success',
  message: 'Cash on delivery request submitted successfully',
  request_id: <id>,
  booking_id: <id>,
  amount: <amount>,
  formatted_amount: '₱X.XX',
  reference_number: 'COD-YYYYMMDD-BOOKINGID'
}
```

## Notifications

### Admin Notification
- **Type**: `cash_request`
- **Title**: "New Cash on Delivery Request"
- **Message**: "New cash on delivery request #{request_id} from {customer_name} for ₱{amount}"
- **Created**: Upon successful request submission

### User Notification
- **Type**: Booking-related notification
- **Title**: "Cash on Delivery Request Submitted"
- **Message**: "Your cash on delivery request for ₱{amount} (Booking #{booking_id}) has been submitted. Reference: {reference_number}"
- **Link**: Points to `user-profile.php?view_payment_request={request_id}`

## Configuration

### Modals in booking_confirmation.php

#### Cash Modal ID: `#cashModal`
- Opens payment request form
- Collects user confirmation
- Shows booking details

#### Success Modal ID: `#cashSuccessModal`
- Displays after successful submission
- Shows confirmation message
- Auto-redirects after 10 seconds

### JavaScript Configuration

#### File: `assets/js/cash-payment.js`
- **Initialization**: Runs on DOMContentLoaded
- **Endpoints**: Posts to `user/submit_cash_request.php`
- **Timeout**: 15 seconds for API requests
- **Redirect**: User-profile.php on success

## Troubleshooting

### Issue: "A cash on delivery request is already pending for this booking"
**Solution**: 
- User attempted to submit multiple requests for same booking
- Admin must approve or reject the pending request first
- User can then submit a new request if needed

### Issue: Amount shows as 0.00
**Solution**: 
- Services may not be properly saved in booking
- Check `bookings.service_type` field in database
- Ensure services are linked to correct pricing in `services` table

### Issue: Modal doesn't open
**Solution**: 
- Verify `cash-payment.js` is loaded in the page
- Check browser console for JavaScript errors
- Ensure Bootstrap 5 is loaded (required for modals)

### Issue: Form submission fails silently
**Solution**: 
- Check browser network tab in developer tools
- Verify `submit_cash_request.php` is accessible
- Check server error logs (`php_errors.log`)
- Ensure user is logged in and session is valid

## Integration with Existing Features

### Bookings Table
- Uses `bookings.id` for booking_id
- Updates `bookings.status` to 'Pending' upon request submission
- Works with existing booking management

### Services Table
- Uses `services.price` for amount calculation
- Supports multiple services per booking
- Maintains current pricing consistency

### Users Table
- Uses `users.id` for user identification
- Retrieves `first_name` and `last_name` for display
- Links user to payment requests

### Notifications System
- Uses existing `admin_notifications` table for admin alerts
- Uses existing `notifications` table for user notifications
- Integrates with notification display system

## Future Enhancements

1. **Payment Confirmation**: Allow customers to mark payment as completed after delivery
2. **COD Analytics**: Track COD payment success rates and amounts
3. **Delivery Time Slots**: Select preferred delivery time for COD orders
4. **Multiple Payment Options**: Split payment between COD and GCASH
5. **Partial Payments**: Support partial COD payments with change management

## Security Considerations

1. **Session Validation**: All requests require valid user session
2. **Booking Ownership**: Verifies user owns the booking
3. **SQL Injection Prevention**: Uses prepared statements
4. **Price Verification**: Recalculates amount server-side (no client-side trust)
5. **Transaction Safety**: Uses database transactions for atomicity

## Testing Checklist

- [ ] Submit cash request from booking confirmation page
- [ ] Verify success modal appears and redirects to profile
- [ ] Check admin notification in admin dashboard
- [ ] Verify booking status changes to 'Pending'
- [ ] Attempt duplicate request (should fail with appropriate message)
- [ ] Test with various service combinations
- [ ] Verify amount calculation accuracy
- [ ] Test error handling with invalid booking ID
- [ ] Verify session timeout handling
- [ ] Check that Cash on Delivery requests appear in payment history

## Reference Information

### Payment Method Constants
- GCASH Requests: `payment_method = 'GCASH'`
- Cash on Delivery: `payment_method = 'Cash on Delivery'`

### Reference Number Format
- Format: `COD-YYYYMMDD-BOOKINGID`
- Example: `COD-20240115-000123`
- Used for customer reference and tracking

### Status Values
- `pending` - Submitted by user, awaiting admin approval
- `approved` - Approved by admin, waiting for payment
- `completed` - Payment received, booking complete
- `rejected` - Declined by admin

## Support Contact
For issues or questions regarding the cash on delivery feature, please contact the development team or check the system logs for detailed error messages.
