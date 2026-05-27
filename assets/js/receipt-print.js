// receipt-print.js - Enhanced receipt printing without page reload

function confirmPrint(modalId) {
    Swal.fire({
        title: 'Print Receipt?',
        html: `
            <div class="text-center">
                <i class="fas fa-print text-primary fs-1 mb-3 d-block"></i>
                <p>Do you want to print this transaction receipt?</p>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> The receipt will open in a new print-friendly window.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-print"></i> Yes, Print',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            printReceipt(modalId);
        }
    });
}

function printReceipt(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        Swal.fire('Error', 'Receipt not found!', 'error');
        return;
    }
    
    // Get modal content
    const modalContent = modal.querySelector('.modal-body').cloneNode(true);
    
    // Create print window
    const printWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes');
    
    // Create print-friendly content
    const printContent = createPrintContent(modalContent, modalId);
    
    // Write to print window
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Focus on print window and auto-print
    printWindow.focus();
    
    // Give it a moment to load before printing
    setTimeout(() => {
        try {
            printWindow.print();
        } catch (e) {
            // If auto-print fails, show print button
            Swal.fire({
                title: 'Print Ready',
                html: `
                    <div class="text-center">
                        <i class="fas fa-print text-success fs-1 mb-3 d-block"></i>
                        <p>The receipt is ready to print.</p>
                        <p class="text-muted small">If print didn't start automatically, click "Print" in the new window.</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'OK'
            });
        }
    }, 500);
}

function createPrintContent(contentElement, modalId) {
    // Remove any existing print buttons
    const printButtons = contentElement.querySelectorAll('button');
    printButtons.forEach(button => button.remove());
    
    // Create header with company info
    const header = `
        <div class="receipt-header text-center mb-4">
            <h2 class="company-name" style="color: #6f42c1; font-weight: bold;">Jorish Express Laundry</h2>
            <h4 class="receipt-title">TRANSACTION RECEIPT</h4>
            <div class="receipt-date">Printed: ${new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}</div>
            <hr style="border-top: 2px solid #000;">
        </div>
    `;
    
    // Create footer
    const footer = `
        <div class="receipt-footer text-center mt-4 pt-4 border-top">
            <p style="margin-bottom: 5px;">Thank you for choosing Jorish Express Laundry!</p>
            <p style="margin-bottom: 5px; color: #666;">This is a computer-generated receipt.</p>
            <p style="color: #666; font-size: 12px;">For inquiries: (123) 456-7890 | support@jorishlaundry.com</p>
        </div>
    `;
    
    // Create print controls
    const controls = `
        <div class="print-controls text-center mt-4" style="padding: 20px;">
            <button onclick="window.print()" class="btn btn-primary btn-sm" style="padding: 8px 20px;">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm" style="padding: 8px 20px; margin-left: 10px;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    `;
    
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - Jorish Express Laundry</title>
            <link href=".\.../assets/lib/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href=".\.../assets/lib/css/all.min.css">
            <style>
                @media print {
                    body { 
                        margin: 0; 
                        padding: 20px; 
                        font-size: 12px;
                    }
                    .print-controls { 
                        display: none !important; 
                    }
                    .receipt-header {
                        margin-bottom: 15px;
                    }
                }
                @media screen {
                    body { 
                        padding: 20px; 
                        font-size: 14px;
                    }
                }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    max-width: 800px;
                    margin: 0 auto;
                }
                .company-name {
                    font-size: 24px;
                    margin-bottom: 5px;
                }
                .receipt-title {
                    font-size: 18px;
                    margin: 10px 0;
                }
                .receipt-date {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 15px;
                }
                .detail-item {
                    margin-bottom: 8px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #eee;
                }
                .detail-item strong {
                    color: #333;
                    min-width: 150px;
                    display: inline-block;
                }
                .total-amount {
                    font-size: 20px;
                    font-weight: bold;
                    color: #28a745;
                }
                .badge {
                    font-size: 11px;
                    padding: 3px 6px;
                    margin: 2px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                ${header}
                <div class="receipt-content">
                    ${contentElement.innerHTML}
                </div>
                ${footer}
                ${controls}
            </div>
            <script>
                // Auto-print on load
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        try {
                            window.print();
                        } catch (e) {
                            console.log('Print initiated manually');
                        }
                    }, 1000);
                });
            </script>
        </body>
        </html>
    `;
}

// Initialize print buttons
document.addEventListener('DOMContentLoaded', function() {
    // Handle existing print buttons
    const printButtons = document.querySelectorAll('[onclick^="printReceipt"]');
    printButtons.forEach(button => {
        const oldOnClick = button.getAttribute('onclick');
        if (oldOnClick) {
            const modalId = oldOnClick.match(/printReceipt\('([^']+)'\)/)?.[1];
            if (modalId) {
                button.removeAttribute('onclick');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    confirmPrint(modalId);
                });
            }
        }
    });
    
    // Also handle any buttons with onclick calling confirmPrint
    const confirmPrintButtons = document.querySelectorAll('[onclick^="confirmPrint"]');
    confirmPrintButtons.forEach(button => {
        const oldOnClick = button.getAttribute('onclick');
        if (oldOnClick) {
            const modalId = oldOnClick.match(/confirmPrint\('([^']+)'\)/)?.[1];
            if (modalId) {
                button.removeAttribute('onclick');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    confirmPrint(modalId);
                });
            }
        }
    });
});



