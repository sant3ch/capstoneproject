// modal-tabs-init.js - Initialize Bootstrap tabs and modals without flickering

document.addEventListener("DOMContentLoaded", function () {
    // Initialize Bootstrap tabs (global tabs, not just in payment modal)
    const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabTriggers.forEach(function (triggerEl) {
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            const targetId = this.getAttribute('href');
            const targetPane = document.querySelector(targetId);
            
            if (targetPane) {
                // Remove active class from all tabs
                document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Hide all tab panes
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Activate current tab and pane
                this.classList.add('active');
                targetPane.classList.add('show', 'active');
                
                // If there's a nested payment modal, ensure it's properly initialized
                if (targetId === '#schedule') {
                    // Re-initialize payment buttons after tab switch
                    setTimeout(() => {
                        if (typeof initPaymentSystem === 'function') {
                            initPaymentSystem();
                        }
                    }, 100);
                }
            }
        });
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover focus'
        });
    });
    
    // Fix modal backdrop issues
    document.addEventListener('show.bs.modal', function(event) {
        // Remove duplicate backdrops
        const existingBackdrops = document.querySelectorAll('.modal-backdrop');
        if (existingBackdrops.length > 1) {
            for (let i = 1; i < existingBackdrops.length; i++) {
                existingBackdrops[i].remove();
            }
        }
    });
    
    // Handle modal hidden event
    document.addEventListener('hidden.bs.modal', function(event) {
        const modal = event.target;
        
        // Reset form inside modal if exists
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        
        // Remove backdrop if no other modals are open
        setTimeout(() => {
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
            }
        }, 100);
    });
    
    // Initialize tab content on page load
    const activeTab = document.querySelector('[data-bs-toggle="tab"].active');
    if (activeTab) {
        const targetId = activeTab.getAttribute('href');
        const targetPane = document.querySelector(targetId);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }
    }
});