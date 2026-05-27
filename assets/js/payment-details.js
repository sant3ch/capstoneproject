// payment-details.js - Dynamic payment details modal (Updated for SIMPLE pricing)
document.addEventListener('DOMContentLoaded', function() {
    // Handle payment details modal
    const viewDetailsButtons = document.querySelectorAll('.view-payment-details');
    const paymentDetailsModal = document.getElementById('paymentDetailsModal');
    const paymentDetailsContent = document.getElementById('paymentDetailsContent');
    const paymentDetailsModalLabel = document.getElementById('paymentDetailsModalLabel');
    const printDetailsBtn = document.getElementById('printDetailsBtn');
    
    let currentTransactionId = null;
    let currentTransactionData = null;
    
    // Helper function to create service badges for SIMPLE pricing
    function createServiceBadges(servicesString) {
        if (!servicesString || servicesString === 'N/A') {
            return '<span class="badge bg-light text-muted">No services listed</span>';
        }
        
        const services = servicesString.split(',').map(s => s.trim()).filter(s => s);
        let badgesHtml = '';
        
        // Define service colors for SIMPLE pricing
        const serviceColors = {
            'wash': 'bg-primary',
            'dry': 'bg-success',
            'fold': 'bg-info',
            'detergent': 'bg-warning text-dark',
            'downy': 'bg-purple text-white',
            'fabric conditioner': 'bg-purple text-white'
        };
        
        services.forEach(service => {
            const serviceLower = service.toLowerCase();
            let badgeClass = 'bg-secondary';
            
            // Determine badge color based on service type
            if (serviceLower.includes('wash')) {
                badgeClass = serviceColors.wash;
            } else if (serviceLower.includes('dry')) {
                badgeClass = serviceColors.dry;
            } else if (serviceLower.includes('fold')) {
                badgeClass = serviceColors.fold;
            } else if (serviceLower.includes('detergent')) {
                badgeClass = serviceColors.detergent;
            } else if (serviceLower.includes('downy') || serviceLower.includes('fabric conditioner')) {
                badgeClass = serviceColors.downy;
            }
            
            badgesHtml += `<span class="badge ${badgeClass} me-1 mb-1">${service}</span>`;
        });
        
        return badgesHtml || '<span class="text-muted">No services listed</span>';
    }
    
    // Format date for display
    function formatDate(dateString) {
        if (!dateString || dateString === 'N/A') return 'N/A';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    function formatDateTime(dateString) {
        if (!dateString || dateString === 'N/A') return 'N/A';
        
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }
    
    // Calculate service breakdown for SIMPLE pricing
    function calculateServiceBreakdown(servicesString, detergentString, totalAmount) {
        if (!servicesString || servicesString === 'N/A') {
            return '<div class="text-muted">No service details available</div>';
        }
        
        const services = servicesString.split(',').map(s => s.trim()).filter(s => s);
        const detergents = detergentString ? detergentString.split(',').map(d => d.trim()).filter(d => d) : [];
        
        let breakdownHtml = '<div class="service-breakdown">';
        
        // Service prices for SIMPLE pricing
        const servicePrices = {
            'wash': 65.00,
            'dry': 80.00,
            'fold': 30.00
        };
        
        // Addon prices for SIMPLE pricing
        const addonPrices = {
            'detergent': 16.00,
            'downy': 11.00,
            'fabric conditioner': 11.00
        };
        
        let calculatedTotal = 0;
        
        // Add services
        services.forEach(service => {
            const serviceLower = service.toLowerCase();
            let price = 0;
            let serviceName = '';
            
            if (serviceLower.includes('wash')) {
                price = servicePrices.wash;
                serviceName = 'Wash';
            } else if (serviceLower.includes('dry')) {
                price = servicePrices.dry;
                serviceName = 'Dry';
            } else if (serviceLower.includes('fold')) {
                price = servicePrices.fold;
                serviceName = 'Fold';
            } else {
                price = servicePrices.wash;
                serviceName = service;
            }
            
            calculatedTotal += price;
            breakdownHtml += `
                <div class="d-flex justify-content-between mb-1">
                    <span>${serviceName}:</span>
                    <span>₱${price.toFixed(2)}</span>
                </div>
            `;
        });
        
        // Add detergents/addons
        detergents.forEach(detergent => {
            const detergentLower = detergent.toLowerCase();
            let price = 0;
            let addonName = '';
            
            if (detergentLower.includes('bring my own detergent') || detergentLower === 'n/a') {
                return; // Skip if user brought their own
            }
            
            if (detergentLower.includes('downy') || detergentLower.includes('fabric conditioner')) {
                price = addonPrices.downy;
                addonName = 'Downy/Fabric Conditioner';
            } else {
                price = addonPrices.detergent;
                addonName = 'Detergent';
            }
            
            calculatedTotal += price;
            breakdownHtml += `
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">${addonName}:</span>
                    <span class="text-muted">₱${price.toFixed(2)}</span>
                </div>
            `;
        });
        
        breakdownHtml += `
            <hr class="my-2">
            <div class="d-flex justify-content-between fw-bold">
                <span>Calculated Total:</span>
                <span>₱${calculatedTotal.toFixed(2)}</span>
            </div>
        `;
        
        breakdownHtml += '</div>';
        return breakdownHtml;
    }
    
    // Handle view details button clicks
    if (viewDetailsButtons.length > 0) {
        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentTransactionId = this.dataset.transactionId;
                currentTransactionData = {
                    transactionId: this.dataset.transactionId,
                    bookingId: this.dataset.bookingId,
                    customerName: this.dataset.customerName,
                    services: this.dataset.services,
                    detergent: this.dataset.detergent || '',
                    laundryWeight: this.dataset.laundryWeight,
                    paymentMethod: this.dataset.paymentMethod,
                    totalAmount: this.dataset.totalAmount,
                    pointsEarned: this.dataset.pointsEarned,
                    transactionDate: this.dataset.transactionDate,
                    bookingDate: this.dataset.bookingDate,
                    machineCount: this.dataset.machineCount || '1',
                    timeSlot: this.dataset.timeSlot || 'N/A'
                };
                
                // Update modal title
                paymentDetailsModalLabel.textContent = `Transaction #${currentTransactionId}`;
                
                // Create modal content with SIMPLE pricing details
                const content = `
                    <div class="transaction-details">
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Transaction ID:</strong>
                                    <div class="text-primary fw-bold">#${this.dataset.transactionId}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Booking ID:</strong>
                                    <div>${this.dataset.bookingId}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Customer:</strong>
                                    <div>${this.dataset.customerName}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Booking Date:</strong>
                                    <div>${formatDate(this.dataset.bookingDate)}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Time Slot:</strong>
                                    <div>${this.dataset.timeSlot || 'N/A'}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Machines:</strong>
                                    <div>${this.dataset.machineCount || '1'}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-item mb-3">
                            <strong>Services Availed:</strong>
                            <div class="mt-2">
                                ${createServiceBadges(this.dataset.services)}
                            </div>
                        </div>
                        
                        <div class="detail-item mb-3">
                            <strong>Detergent/Addons:</strong>
                            <div class="mt-2">
                                ${this.dataset.detergent && this.dataset.detergent !== 'N/A' 
                                    ? createServiceBadges(this.dataset.detergent)
                                    : '<span class="text-muted">No addons selected</span>'}
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Laundry Weight:</strong>
                                    <div class="text-muted">N/A (SIMPLE Pricing)</div>
                                    ${this.dataset.laundryWeight && this.dataset.laundryWeight !== 'N/A' 
                                        ? `<small class="text-muted">${this.dataset.laundryWeight}</small>`
                                        : ''}
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Payment Method:</strong>
                                    <div>
                                        <span class="badge ${this.dataset.paymentMethod === 'GCASH' ? 'bg-success' : 'bg-info text-dark'}">
                                            ${this.dataset.paymentMethod}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-item mb-4">
                            <strong>Amount Breakdown:</strong>
                            <div class="mt-2 p-3 bg-light rounded">
                                ${calculateServiceBreakdown(
                                    this.dataset.services, 
                                    this.dataset.detergent, 
                                    this.dataset.totalAmount
                                )}
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Total Amount:</strong>
                                    <div class="fw-bold text-success fs-5">₱${this.dataset.totalAmount}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <strong>Points Earned:</strong>
                                    <div class="fw-bold text-warning">${this.dataset.pointsEarned} points</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Transaction Date:</strong>
                            <div class="text-muted">${formatDateTime(this.dataset.transactionDate)}</div>
                        </div>
                    </div>
                `;
                
                // Update modal content
                paymentDetailsContent.innerHTML = content;
                
                // Show modal
                const modal = new bootstrap.Modal(paymentDetailsModal);
                modal.show();
            });
        });
    }
    
    // Handle print button
    if (printDetailsBtn) {
        printDetailsBtn.addEventListener('click', function() {
            if (!currentTransactionId || !currentTransactionData) return;
            
            // Open print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Create print-friendly content
            const printContent = createPrintContent(currentTransactionData);
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Auto-print after content loads
            setTimeout(() => {
                printWindow.print();
            }, 500);
        });
    }
    
    // Function to create print content (Updated for SIMPLE pricing)
    function createPrintContent(data) {
        const serviceBadgesHtml = createServiceBadges(data.services);
        const detergentBadgesHtml = data.detergent && data.detergent !== 'N/A' 
            ? createServiceBadges(data.detergent)
            : '<span class="text-muted">No addons selected</span>';
        
        return `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Receipt - Transaction #${data.transactionId}</title>
                <link href=".\.../assets/lib/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; background-color: #fff; }
                    @media print {
                        body { margin: 0; padding: 20px; }
                        .no-print { display: none !important; }
                    }
                    .receipt-container { max-width: 600px; margin: 0 auto; }
                    .receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
                    .receipt-title { font-size: 24px; font-weight: bold; color: #6f42c1; margin-bottom: 5px; }
                    .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
                    .transaction-id { font-size: 16px; margin-bottom: 10px; }
                    .receipt-date { font-size: 14px; color: #666; }
                    .receipt-body { margin-bottom: 30px; }
                    .detail-row { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
                    .detail-label { font-weight: bold; color: #555; margin-bottom: 5px; }
                    .detail-value { font-size: 16px; }
                    .service-badge { display: inline-block; padding: 4px 8px; margin: 2px; border-radius: 4px; font-size: 12px; }
                    .breakdown-box { background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
                    .breakdown-item { display: flex; justify-content: space-between; padding: 3px 0; }
                    .breakdown-subitem { display: flex; justify-content: space-between; padding-left: 15px; font-size: 0.9rem; color: #666; }
                    .total-section { background-color: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 30px; }
                    .total-amount { font-size: 24px; font-weight: bold; color: #28a745; }
                    .receipt-footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
                    .note-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 10px 0; }
                </style>
            </head>
            <body>
                <div class="receipt-container">
                    <div class="receipt-header">
                        <div class="company-name">Jorish Express Laundry</div>
                        <div class="receipt-title">TRANSACTION RECEIPT</div>
                        <div class="transaction-id">Transaction #${data.transactionId}</div>
                        <div class="receipt-date">Printed: ${new Date().toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}</div>
                    </div>
                    
                    <div class="receipt-body">
                        <div class="row detail-row">
                            <div class="col-6">
                                <div class="detail-label">Transaction ID</div>
                                <div class="detail-value">#${data.transactionId}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Booking ID</div>
                                <div class="detail-value">${data.bookingId}</div>
                            </div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-6">
                                <div class="detail-label">Customer Name</div>
                                <div class="detail-value">${data.customerName}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Booking Date</div>
                                <div class="detail-value">${formatDate(data.bookingDate)}</div>
                            </div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-6">
                                <div class="detail-label">Time Slot</div>
                                <div class="detail-value">${data.timeSlot || 'N/A'}</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Machines</div>
                                <div class="detail-value">${data.machineCount || '1'}</div>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Services Availed</div>
                            <div class="detail-value mt-2">
                                ${serviceBadgesHtml}
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Detergent/Addons</div>
                            <div class="detail-value mt-2">
                                ${detergentBadgesHtml}
                            </div>
                        </div>
                        
                        <div class="row detail-row">
                            <div class="col-6">
                                <div class="detail-label">Laundry Weight</div>
                                <div class="detail-value">N/A (SIMPLE Pricing)</div>
                            </div>
                            <div class="col-6">
                                <div class="detail-label">Payment Method</div>
                                <div class="detail-value">
                                    <span class="service-badge" style="background-color: #17a2b8; color: white;">${data.paymentMethod}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="breakdown-box">
                            <div class="detail-label mb-2">Amount Breakdown (SIMPLE Pricing)</div>
                            ${calculateServiceBreakdown(data.services, data.detergent, data.totalAmount)}
                        </div>
                        
                        <div class="note-box">
                            <strong>Note:</strong> SIMPLE pricing applies fixed rates per service. No laundry weight calculation.
                        </div>
                        
                        <div class="total-section">
                            <div class="row">
                                <div class="col-6">
                                    <div class="detail-label">Total Amount</div>
                                    <div class="total-amount">₱${data.totalAmount}</div>
                                </div>
                                <div class="col-6">
                                    <div class="detail-label">Points Earned</div>
                                    <div class="detail-value fw-bold" style="color: #ffc107; font-size: 20px;">
                                        ${data.pointsEarned} points
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-row mt-4">
                            <div class="detail-label">Transaction Date</div>
                            <div class="detail-value">${formatDateTime(data.transactionDate)}</div>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <div class="mb-2">Thank you for choosing Jorish Express Laundry!</div>
                        <div>For inquiries, please contact: (123) 456-7890</div>
                        <div>Email: support@jorishlaundry.com</div>
                        <div class="mt-3">This is a computer-generated receipt. No signature required.</div>
                    </div>
                    
                    <div class="no-print text-center mt-4">
                        <button onclick="window.print()" class="btn btn-primary me-2">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                        <button onclick="window.close()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Close Window
                        </button>
                    </div>
                </div>
            </body>
            </html>
        `;
    }
});



