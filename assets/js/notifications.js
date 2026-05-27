// assets/js/notifications.js
console.log('Notifications.js loading...');

// Determine the correct path for notification PHP files
function getNotificationPath(filename) {
    // Check if we're in a subdirectory like /user/, /admin/, /staff/
    if (window.location.pathname.includes('/user/') || 
        window.location.pathname.includes('/admin/') || 
        window.location.pathname.includes('/staff/')) {
        return filename; // Same directory
    } else {
        return 'user/' + filename; // Root level - files are in /user/
    }
}

// Define global functions
window.markAllNotificationsRead = function() {
    console.log('markAllNotificationsRead() called');
    
    // Show loading state
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        const originalText = markAllBtn.textContent;
        markAllBtn.textContent = 'Marking...';
        markAllBtn.disabled = true;
    }
    
    fetch(getNotificationPath('mark_notification_read.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({}) // Empty object for "mark all"
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Server response:', data);
        
        if (markAllBtn) {
            markAllBtn.textContent = 'Mark All as Read';
            markAllBtn.disabled = false;
        }
        
        if (data.status === 'success') {
            // Update UI for all notifications
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.add('read-notification');
                item.style.opacity = '0.7';
            });
            
            // Remove badge
            const badge = document.querySelector('.badge.bg-danger');
            if (badge) badge.remove();
            
            // Update badge in notification button
            updateNotificationBadge();
            
            // Show success message
            showNotification('All notifications marked as read', 'success');
            
            // Close modal after 1.5 seconds
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('notificationModal'));
                if (modal) modal.hide();
            }, 1500);
        } else {
            showNotification(data.message || 'Error marking notifications as read', 'error');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        if (markAllBtn) {
            markAllBtn.textContent = 'Mark All as Read';
            markAllBtn.disabled = false;
        }
        showNotification('Network error. Please try again.', 'error');
    });
}

window.markSingleNotificationRead = function(notificationId) {
    console.log('markSingleNotificationRead() called for ID:', notificationId);
    
    if (!notificationId) return;
    
    fetch(getNotificationPath('mark_notification_read.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Single notification response:', data);
        
        if (data.status === 'success') {
            const notificationElement = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.add('read-notification');
                notificationElement.style.opacity = '0.7';
                updateNotificationBadge();
            }
        }
    })
    .catch(error => {
        console.error('Error marking single notification:', error);
    });
}

function updateNotificationBadge() {
    const unreadCount = document.querySelectorAll('.notification-item:not(.read-notification)').length;
    console.log('Unread notifications count:', unreadCount);
    
    const notificationButton = document.querySelector('button[data-bs-target="#notificationModal"]');
    let badge = document.querySelector('.notification-badge');
    
    if (unreadCount > 0) {
        if (!badge && notificationButton) {
            badge = document.createElement('span');
            badge.className = 'notification-badge badge bg-danger rounded-pill';
            badge.style.cssText = 'position: absolute; top: -5px; right: -5px; font-size: 0.7rem; padding: 0.25em 0.4em;';
            badge.textContent = unreadCount;
            notificationButton.style.position = 'relative';
            notificationButton.appendChild(badge);
        } else if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = 'inline-block';
        }
    } else if (badge) {
        badge.style.display = 'none';
    }
}

function showNotification(message, type = 'info') {
    // Create toast notification
    const toastContainer = document.getElementById('toast-container') || (() => {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
        return container;
    })();
    
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Function to render notification item with HTML content
function renderNotificationItem(notification) {
    const message = notification.message || '';
    
    const link = notification.link || '';
    let messageHtml = '';

    if (message.includes('Click here to view payment details')) {
        let actionButton = '';
        if (link && link.includes('view_gcash_request=')) {
            const requestIdMatch = link.match(/view_gcash_request=(\d+)/);
            const requestId = requestIdMatch ? requestIdMatch[1] : null;
            if (requestId) {
                actionButton = `<button type="button" class="btn btn-sm btn-primary mt-1" onclick="showGCASHPaymentModalFromData(${requestId});">View Payment Details</button>`;
            }
        }

        if (!actionButton && link) {
            actionButton = `<a href="${link}" class="btn btn-sm btn-primary mt-1">View Payment Details</a>`;
        }

        if (!actionButton) {
            actionButton = `<span class="badge bg-secondary">(Action unavailable)</span>`;
        }

        messageHtml = escapeHtml(message).replace('Click here to view payment details', actionButton).replace(/\n/g, '<br>');
    } else {
        // Escape HTML for safety but preserve line breaks
        messageHtml = escapeHtml(message).replace(/\n/g, '<br>');
    }
    
    return `
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <strong>${escapeHtml(notification.title || '')}</strong><br>
                ${messageHtml}
                <small class="text-muted d-block mt-1">${formatDate(notification.created_at)}</small>
            </div>
            ${!notification.is_read ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : ''}
        </div>
    `;
}

// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Function to load and display notifications in the modal
function loadNotifications() {
    fetch(getNotificationPath('get_notifications.php'))
        .then(response => response.json())
        .then(data => {
            const notificationList = document.querySelector('.notification-list');
            if (!notificationList) return;
            
            if (data.notifications && data.notifications.length > 0) {
                notificationList.innerHTML = '';
                data.notifications.forEach(notification => {
                    const li = document.createElement('li');
                    li.className = `list-group-item notification-item ${!notification.is_read ? 'unread' : 'read-notification'}`;
                    li.setAttribute('data-id', notification.id);
                    li.innerHTML = renderNotificationItem(notification);
                    notificationList.appendChild(li);
                });
            } else {
                notificationList.innerHTML = '<li class="list-group-item text-center text-muted">No new notifications.</li>';
            }
            
            updateNotificationBadge();
        })
        .catch(error => console.error('Error loading notifications:', error));
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing notifications...');
    
    // Check if notification modal exists on this page
    const notificationModal = document.getElementById('notificationModal');
    if (!notificationModal) {
        console.log('Notification modal not found on this page - skipping notification initialization');
        return;
    }
    
    // Initialize badge on page load
    updateNotificationBadge();
    
    // Load notifications into modal
    loadNotifications();
    
    // Event listeners
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        console.log('Found Mark All button');
        markAllBtn.addEventListener('click', window.markAllNotificationsRead);
    } else {
        console.warn('Mark All button not found!');
    }
    
    const closeBtn = document.getElementById('closeNotificationModal');
    if (closeBtn) {
        closeBtn.addEventListener('click', window.markAllNotificationsRead);
    }
    
    // Click on individual notifications - handle the link clicks separately
    document.addEventListener('click', function(e) {
        // Check if clicked on a link inside notification
        const link = e.target.closest('.notification-item a');
        if (link) {
            // Allow the link to work normally (navigate)
            return;
        }
        
        // Otherwise, mark as read
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !notificationItem.classList.contains('read-notification')) {
            const notificationId = notificationItem.getAttribute('data-id');
            if (notificationId) {
                window.markSingleNotificationRead(notificationId);
            }
        }
    });
    
    // Reload notifications when modal is shown
    notificationModal.addEventListener('shown.bs.modal', function() {
        console.log('Notification modal opened, reloading notifications...');
        loadNotifications();
        updateNotificationBadge();
    });
    
    console.log('Notifications initialized successfully');
});

// Also make sure functions are available immediately
console.log('Notifications.js loaded, window.markAllNotificationsRead defined:', typeof window.markAllNotificationsRead !== 'undefined');