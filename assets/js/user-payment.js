// user-payment.js - Fixed version with correct pricing and service request

// Global variables
let currentBookingData = {};
let totalAmount = 0;
let totalPoints = 1; // Fixed 1 point per transaction

// Initialize payment system
function initPaymentSystem() {
    initPaymentButtons();
    
    const paymentModal = document.getElementById('paymentModal');
    if (paymentModal) {
        paymentModal.addEventListener('show.bs.modal', function() {});
        paymentModal.addEventListener('hidden.bs.modal', function() {
            resetPaymentForm();
        });
    }
    
    const addWeightBtn = document.getElementById('addWeightBtn');
    if (addWeightBtn) {
        addWeightBtn.addEventListener('click', calculatePayment);
    }
    
    const cashAmount = document.getElementById('cashAmount');
    if (cashAmount) {
        cashAmount.addEventListener('input', updateChange);
    }
    
    const paymentMethod = document.getElementById('paymentMethod');
    if (paymentMethod) {
        paymentMethod.addEventListener('change', function() {
            const cashAmountField = document.getElementById('cashAmountField');
            const changeSection = document.getElementById('changeSection');
            if (this.value === 'Cash') {
                if (cashAmountField) cashAmountField.style.display = 'block';
                if (changeSection) changeSection.style.display = 'block';
            } else {
                if (cashAmountField) cashAmountField.style.display = 'none';
                if (changeSection) changeSection.style.display = 'none';
            }
        });
    }
    
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', handlePaymentSubmit);
    }
}

// Initialize payment buttons
function initPaymentButtons() {
    document.querySelectorAll('.payBtn').forEach(button => {
        button.addEventListener('click', function() {
            // Get booking data - including the service request
            const serviceRequest = this.getAttribute('data-request') || '';
            console.log('Service request from button (raw):', serviceRequest);
            
            currentBookingData = {
                id: this.getAttribute('data-id'),
                name: this.getAttribute('data-name') || 'Current User',
                service: this.getAttribute('data-service'),
                detergent: this.getAttribute('data-detergent') || '',
                request: serviceRequest,
                machineCount: this.getAttribute('data-machine-count') || '1',
                weight: this.getAttribute('data-weight') || '',
                estimatedAmount: parseFloat(this.getAttribute('data-estimated-amount')) || 0
            };
            
            console.log('Booking data loaded:', currentBookingData);
            console.log('Service request value:', currentBookingData.request);
            console.log('Estimated amount from server: ₱' + currentBookingData.estimatedAmount);
            
            // Set modal values
            const modalBookingId = document.getElementById('modalBookingId');
            const customerName = document.getElementById('customerName');
            const machineCount = document.getElementById('machineCount');
            const serviceRequestInput = document.getElementById('serviceRequestInput');
            
            if (modalBookingId) modalBookingId.value = currentBookingData.id;
            if (customerName) customerName.value = currentBookingData.name;
            if (machineCount) machineCount.value = currentBookingData.machineCount;
            if (serviceRequestInput) serviceRequestInput.value = currentBookingData.request;
            
            // Clear any previous data first
            resetPaymentFormForNewBooking();
            
            // Store the total amount from server (includes services + addons)
            currentBookingData.totalAmountFromServer = currentBookingData.estimatedAmount;
            
            // Parse and display selected services
            parseAndDisplayServices(currentBookingData.service);
            
            // Display detergent/addons (display only with correct pricing)
            if (currentBookingData.detergent && currentBookingData.detergent !== 'N/A') {
                displayDetergentAddons(currentBookingData.detergent);
            }
            
            // Set weight if available
            const laundryWeight = document.getElementById('laundryWeight');
            if (laundryWeight && currentBookingData.weight) {
                laundryWeight.value = currentBookingData.weight;
            }
            
            // Display service request
            displayServiceRequest(currentBookingData.request);
        });
    });
}

/**
 * Parse service data and display with correct price
 */
function parseAndDisplayServices(serviceData) {
    const selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
    if (!selectedServicesDisplay) {
        console.error('Missing services display element');
        return;
    }
    
    console.log('Parsing service data:', serviceData);
    console.log('Total amount from server: ₱' + currentBookingData.totalAmountFromServer);
    
    let servicesList = [];
    
    // Parse the service data
    if (serviceData && serviceData !== 'N/A') {
        const servicesArray = serviceData.split(',');
        
        servicesArray.forEach(serviceItem => {
            let serviceName = serviceItem.trim();
            let serviceId = null;
            
            // Check if service is stored as "ID:Name" format
            if (serviceName.includes(':')) {
                const parts = serviceName.split(':');
                serviceId = parts[0];
                serviceName = parts[1];
            }
            
            servicesList.push({
                id: serviceId,
                name: serviceName,
                original: serviceItem.trim()
            });
        });
    }
    
    // Build HTML for services display
    let servicesHtml = '<div class="fw-bold mb-2">Selected Services:</div>';
    
    servicesList.forEach(service => {
        const serviceLower = service.name.toLowerCase();
        let icon = 'bi-question-circle';
        let badgeColor = 'bg-secondary';
        
        if (serviceLower.includes('wash') && serviceLower.includes('dry') && serviceLower.includes('fold')) {
            icon = 'bi-recycle';
            badgeColor = 'bg-primary';
        } else if (serviceLower.includes('wash') && serviceLower.includes('dry')) {
            icon = 'bi-arrow-repeat';
            badgeColor = 'bg-success';
        } else if (serviceLower.includes('wash')) {
            icon = 'bi-droplet';
            badgeColor = 'bg-info';
        } else if (serviceLower.includes('dry')) {
            icon = 'bi-wind';
            badgeColor = 'bg-warning text-dark';
        } else if (serviceLower.includes('fold')) {
            icon = 'bi-box';
            badgeColor = 'bg-secondary';
        }
        
        servicesHtml += `
            <div class="d-flex align-items-center mb-2">
                <i class="bi ${icon} me-2"></i>
                <span class="badge ${badgeColor} me-2">${escapeHtml(service.name)}</span>
            </div>
        `;
    });
    
    // Add total amount from server
    servicesHtml += `
        <div class="mt-3 pt-2 border-top">
            <div class="d-flex justify-content-between">
                <strong>Total Amount:</strong>
                <span class="text-success fw-bold">₱${currentBookingData.totalAmountFromServer.toFixed(2)}</span>
            </div>
        </div>
    `;
    
    selectedServicesDisplay.innerHTML = servicesHtml;
    
    // Store the total price for calculation
    currentBookingData.servicePrice = currentBookingData.totalAmountFromServer;
    currentBookingData.servicesList = servicesList;
    
    console.log('Services displayed, total price: ₱' + currentBookingData.servicePrice);
}

/**
 * Display detergent and addons with correct pricing
 * The server already includes these in the total amount, but we show the price for reference
 */
function displayDetergentAddons(detergentData) {
    const selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
    if (!selectedServicesDisplay || !detergentData || detergentData === 'N/A') {
        return;
    }
    
    // Check if there are detergent items
    const detergents = detergentData.split(',').map(d => d.trim()).filter(d => d && d !== 'Bring my own detergent' && d !== 'Bring my own');
    
    if (detergents.length > 0) {
        let addonsHtml = '<div class="mt-3 pt-2 border-top"><div class="fw-bold mb-2">Selected Addons:</div>';
        
        detergents.forEach(detergent => {
            const detergentLower = detergent.toLowerCase();
            let icon = 'bi-droplet';
            let badgeColor = 'bg-warning text-dark';
            let price = 16.00; // Default for detergent
            
            // Determine if it's fabric conditioner (₱11.00) or detergent (₱16.00)
            if (detergentLower.includes('downy') || 
                detergentLower.includes('fabric') || 
                detergentLower.includes('conditioner') ||
                detergentLower.includes('softener') ||
                detergentLower.includes('fabcon')) {
                price = 11.00;
                icon = 'bi-flower';
                badgeColor = 'bg-purple text-white';
            }
            
            addonsHtml += `
                <div class="d-flex align-items-center mb-2">
                    <i class="bi ${icon} me-2"></i>
                    <span class="badge ${badgeColor} me-2">${escapeHtml(detergent)}</span>
                    <small class="text-muted">(+₱${price.toFixed(2)})</small>
                </div>
            `;
        });
        
        addonsHtml += '</div>';
        selectedServicesDisplay.insertAdjacentHTML('beforeend', addonsHtml);
        
        console.log('Addons displayed with correct pricing');
    }
}

/**
 * Display service request (pickup/delivery)
 */
function displayServiceRequest(requestData) {
    const selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
    if (!selectedServicesDisplay) {
        console.error('Missing services display element');
        return;
    }
    
    console.log('Displaying service request - raw data:', requestData);
    
    // Handle empty or null request
    let requestDisplay = 'None';
    let icon = 'bi-question-circle';
    
    if (requestData && requestData !== 'N/A' && requestData !== 'None' && requestData !== '') {
        // Parse comma-separated request services (e.g., "Pickup, Delivery")
        const requests = requestData.split(',').map(r => r.trim().toLowerCase());
        
        const hasPickup = requests.some(r => r.includes('pickup'));
        const hasDelivery = requests.some(r => r.includes('delivery'));
        
        if (hasPickup && hasDelivery) {
            requestDisplay = 'Pickup & Delivery';
            icon = 'bi-arrow-left-right';
        } else if (hasPickup) {
            requestDisplay = 'Pickup Only';
            icon = 'bi-box-arrow-in-down';
        } else if (hasDelivery) {
            requestDisplay = 'Delivery Only';
            icon = 'bi-truck';
        } else {
            // If it doesn't contain pickup or delivery, show the original
            requestDisplay = requestData;
            icon = 'bi-truck';
        }
    } else {
        requestDisplay = 'No service request';
        icon = 'bi-question-circle';
    }
    
    const requestHtml = `
        <div class="mt-3 pt-2 border-top">
            <div class="d-flex align-items-center">
                <i class="bi ${icon} me-2 text-primary"></i>
                <div>
                    <div class="fw-bold mb-1">Service Request:</div>
                    <span class="badge bg-info text-dark">${escapeHtml(requestDisplay)}</span>
                </div>
            </div>
        </div>
    `;
    
    selectedServicesDisplay.insertAdjacentHTML('beforeend', requestHtml);
    console.log('Service request displayed:', requestDisplay);
}

// Reset payment form for new booking
function resetPaymentFormForNewBooking() {
    const paymentItemsTable = document.getElementById('paymentItemsTable');
    if (paymentItemsTable) {
        const tbody = paymentItemsTable.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = '<tr id="noItemsRow"><td colspan="7" class="text-center text-muted py-3">Click "Add" button to calculate payment</td></tr>';
        }
    }
    
    const totalAmountDisplay = document.getElementById('totalAmountDisplay');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const totalPointsDisplay = document.getElementById('totalPointsDisplay');
    const totalPointsInput = document.getElementById('totalPointsInput');
    const changeAmount = document.getElementById('changeAmount');
    const cashAmount = document.getElementById('cashAmount');
    const laundryWeight = document.getElementById('laundryWeight');
    
    if (totalAmountDisplay) totalAmountDisplay.textContent = '₱0.00';
    if (totalAmountInput) totalAmountInput.value = '0';
    if (totalPointsDisplay) totalPointsDisplay.textContent = '1';
    if (totalPointsInput) totalPointsInput.value = '1';
    if (changeAmount) changeAmount.textContent = '₱0.00';
    if (cashAmount) cashAmount.value = '';
    if (laundryWeight) laundryWeight.value = '';
    
    totalAmount = 0;
    totalPoints = 1;
}

// Full reset when modal closes
function resetPaymentForm() {
    resetPaymentFormForNewBooking();
    
    const customerName = document.getElementById('customerName');
    const selectedServicesDisplay = document.getElementById('selectedServicesDisplay');
    const serviceRequestInput = document.getElementById('serviceRequestInput');
    const paymentMethod = document.getElementById('paymentMethod');
    
    if (customerName) customerName.value = '';
    if (selectedServicesDisplay) selectedServicesDisplay.innerHTML = '<div class="text-muted">No service selected</div>';
    if (serviceRequestInput) serviceRequestInput.value = '';
    if (paymentMethod) paymentMethod.value = 'Cash';
    
    currentBookingData = {};
}

// Calculate payment
function calculatePayment() {
    const laundryWeight = document.getElementById('laundryWeight');
    const weight = laundryWeight ? parseFloat(laundryWeight.value) : 0;
    
    if (!currentBookingData.service) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'No service selected', 'error');
        } else {
            alert('No service selected');
        }
        return;
    }
    
    if (!weight || weight <= 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Warning', 'Please enter valid laundry weight', 'warning');
        } else {
            alert('Please enter valid laundry weight');
        }
        return;
    }
    
    // Total amount is from server (services + addons)
    totalAmount = currentBookingData.servicePrice || 0;
    
    // Format request display for the payment table - parse comma-separated values
    let requestDisplay = 'None';
    if (currentBookingData.request && currentBookingData.request !== 'N/A' && currentBookingData.request !== '') {
        const requests = currentBookingData.request.split(',').map(r => r.trim().toLowerCase());
        const hasPickup = requests.some(r => r.includes('pickup'));
        const hasDelivery = requests.some(r => r.includes('delivery'));
        
        if (hasPickup && hasDelivery) {
            requestDisplay = 'Pickup & Delivery';
        } else if (hasPickup) {
            requestDisplay = 'Pickup Only';
        } else if (hasDelivery) {
            requestDisplay = 'Delivery Only';
        } else {
            requestDisplay = currentBookingData.request;
        }
    }
    
    // Get service names for display
    let serviceNames = [];
    if (currentBookingData.servicesList) {
        serviceNames = currentBookingData.servicesList.map(s => s.name);
    } else if (currentBookingData.service) {
        const services = currentBookingData.service.split(',');
        services.forEach(s => {
            if (s.includes(':')) {
                serviceNames.push(s.split(':')[1]);
            } else {
                serviceNames.push(s.trim());
            }
        });
    }
    
    // Update payment table
    const paymentItemsTable = document.getElementById('paymentItemsTable');
    if (paymentItemsTable) {
        const tbody = paymentItemsTable.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td>
                        <div class="d-flex flex-column">
                            ${serviceNames.map(name => `<span class="badge bg-primary mb-1">${escapeHtml(name)}</span>`).join('')}
                        </div>
                    </td>
                    <td class="fw-bold">${weight} kg</td>
                    <td class="fw-bold">₱${totalAmount.toFixed(2)}</td>
                    <td><span class="badge bg-info">${escapeHtml(requestDisplay)}</span></td>
                    <td class="fw-bold text-success">₱${totalAmount.toFixed(2)}</td>
                    <td class="fw-bold text-warning">${totalPoints}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="clearPaymentItems()">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }
    }
    
    // Update totals
    const totalAmountDisplay = document.getElementById('totalAmountDisplay');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const totalPointsDisplay = document.getElementById('totalPointsDisplay');
    const totalPointsInput = document.getElementById('totalPointsInput');
    
    if (totalAmountDisplay) totalAmountDisplay.textContent = `₱${totalAmount.toFixed(2)}`;
    if (totalAmountInput) totalAmountInput.value = totalAmount.toFixed(2);
    if (totalPointsDisplay) totalPointsDisplay.textContent = totalPoints;
    if (totalPointsInput) totalPointsInput.value = totalPoints;
    
    // Update change if cash amount is entered
    updateChange();
}

// Clear payment items
function clearPaymentItems() {
    const paymentItemsTable = document.getElementById('paymentItemsTable');
    if (paymentItemsTable) {
        const tbody = paymentItemsTable.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = '<tr id="noItemsRow"><td colspan="7" class="text-center text-muted py-3">Click "Add" button to calculate payment</td></tr>';
        }
    }
    
    // Reset totals
    const totalAmountDisplay = document.getElementById('totalAmountDisplay');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const totalPointsDisplay = document.getElementById('totalPointsDisplay');
    const totalPointsInput = document.getElementById('totalPointsInput');
    const changeAmount = document.getElementById('changeAmount');
    
    if (totalAmountDisplay) totalAmountDisplay.textContent = '₱0.00';
    if (totalAmountInput) totalAmountInput.value = '0';
    if (totalPointsDisplay) totalPointsDisplay.textContent = '1';
    if (totalPointsInput) totalPointsInput.value = '1';
    if (changeAmount) changeAmount.textContent = '₱0.00';
    
    totalAmount = 0;
    totalPoints = 1;
}

// Update change calculation
function updateChange() {
    const cashAmount = document.getElementById('cashAmount');
    const changeAmount = document.getElementById('changeAmount');
    
    if (cashAmount && changeAmount) {
        const cash = parseFloat(cashAmount.value) || 0;
        const change = cash - totalAmount;
        changeAmount.textContent = `₱${change.toFixed(2)}`;
    }
}

// Handle payment form submission
function handlePaymentSubmit(e) {
    e.preventDefault();
    
    if (totalAmount === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', 'Please calculate the payment first', 'error');
        } else {
            alert('Please calculate the payment first');
        }
        return;
    }
    
    const paymentMethod = document.getElementById('paymentMethod');
    const cashAmount = document.getElementById('cashAmount');
    
    if (paymentMethod && paymentMethod.value === 'Cash') {
        if (!cashAmount.value || parseFloat(cashAmount.value) < totalAmount) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'Cash amount must be at least equal to total amount', 'error');
            } else {
                alert('Cash amount must be at least equal to total amount');
            }
            return;
        }
    }
    
    const submitBtn = document.getElementById('submitPaymentBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
    
    const formData = new FormData(this);
    
    fetch('process_payment_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            if (paymentModal) {
                paymentModal.hide();
            }
            
            const successModalElement = document.getElementById('paymentSuccessModal');
            if (successModalElement) {
                const successModal = new bootstrap.Modal(successModalElement);
                
                const successTransactionId = document.getElementById('successTransactionId');
                const successCustomerName = document.getElementById('successCustomerName');
                const successAmount = document.getElementById('successAmount');
                const successPoints = document.getElementById('successPoints');
                const successNewBalance = document.getElementById('successNewBalance');
                
                if (successTransactionId) successTransactionId.textContent = data.transaction_id || 'N/A';
                if (successCustomerName) successCustomerName.textContent = currentBookingData.name || 'Customer';
                if (successAmount) successAmount.textContent = '₱' + totalAmount.toFixed(2);
                if (successPoints) successPoints.textContent = data.points_earned || 1;
                if (successNewBalance) successNewBalance.textContent = data.new_balance || 'N/A';
                
                successModal.show();
                
                const successOkBtn = document.getElementById('paymentSuccessOkBtn');
                if (successOkBtn) {
                    successOkBtn.onclick = function() {
                        successModal.hide();
                        window.location.href = 'user-profile.php?payment_success=1';
                    };
                }
                
                setTimeout(() => {
                    if (successModalElement.classList.contains('show')) {
                        successModal.hide();
                    }
                    window.location.href = 'user-profile.php?payment_success=1';
                }, 5000);
            }
        } else {
            throw new Error(data.message || 'Payment processing failed');
        }
    })
    .catch(error => {
        console.error('Payment error:', error);
        if (typeof Swal !== 'undefined') {
            Swal.fire('Error', error.message, 'error');
        } else {
            alert('Error: ' + error.message);
        }
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPaymentSystem);
} else {
    initPaymentSystem();
}