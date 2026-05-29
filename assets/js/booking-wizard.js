// assets/js/booking-wizard.js
// Multi-step booking wizard functionality

// Global state
let currentStep = 1;
const totalSteps = 3;
let bookingData = {
    step1: { date: '', timeSlot: '' },
    step2: { services: [], machines: [], machineCount: 0, weight: 0, laundryItems: [], isSelfService: false },
    step3: { firstName: '', lastName: '', mobile: '', email: '', pickupAddress: '', deliveryAddress: '', pickupCoords: '', deliveryCoords: '' }
};

// Initialize wizard on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Booking wizard initialized');
    // ✅ Clear localStorage on page load to prevent auto-filling previous selections
    localStorage.removeItem('bookingWizardData');
    // restoreFromLocalStorage(); // Disabled to prevent auto-filling
    updateWizardDisplay();
    initializeSelectTwoForStep2();
    setupAddressValidation();
    setupStep1DisplayUpdates();
});

// ===== STEP 1 DISPLAY UPDATES =====

function setupStep1DisplayUpdates() {
    const bookingDateField = document.getElementById('booking_date');
    const timeSlotField = document.getElementById('selected_time_slot');

    // Update display when date changes
    if (bookingDateField) {
        bookingDateField.addEventListener('change', function() {
            updateStep1Display();
        });
    }

    // Update display when time slot changes
    if (timeSlotField) {
        timeSlotField.addEventListener('change', function() {
            updateStep1Display();
        });
    }

    // Initial update
    updateStep1Display();
}

function updateStep1Display() {
    const bookingDate = document.getElementById('booking_date').value;
    const timeSlot = document.getElementById('selected_time_slot').value;
    
    // Update date display
    const dateDisplay = document.getElementById('selected-date-display');
    const dateText = document.getElementById('selected-date-text');
    if (bookingDate && dateDisplay && dateText) {
        const date = new Date(bookingDate + 'T00:00:00');
        dateText.textContent = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        dateDisplay.style.display = 'block';
    } else if (dateDisplay) {
        dateDisplay.style.display = 'none';
    }

    // Update time display
    const timeDisplay = document.getElementById('selected-time-display');
    const timeText = document.getElementById('selected-time-text');
    if (timeSlot && timeDisplay && timeText) {
        timeText.textContent = timeSlot;
        timeDisplay.style.display = 'block';
    } else if (timeDisplay) {
        timeDisplay.style.display = 'none';
    }
}

// ===== STEP NAVIGATION =====

// Returns capacity-overflow info when the entered weight needs more than the
// 2-machine (16 kg) limit, else null.
function weightExceedsCapacity() {
    const el = document.getElementById('laundry-weight');
    const w = el ? parseFloat(el.value) : 0;
    if (!w || w <= 0) return null;
    const PER_LOAD = 8, MAX = 2;
    const needed = Math.ceil(w / PER_LOAD);
    if (needed <= MAX) return null;
    return { weight: w, needed: needed, maxKg: PER_LOAD * MAX };
}

// Ask the user to confirm proceeding with laundry that exceeds capacity.
// Resolves true (proceed) when there's no excess or the user confirms.
async function confirmExcessWeight() {
    const info = weightExceedsCapacity();
    if (!info) return true;
    const res = await Swal.fire({
        icon: 'warning',
        title: 'Laundry Exceeds Capacity',
        html: `Your laundry is about <b>${info.weight} kg</b>, which needs <b>${info.needed} loads</b> — `
            + `more than the <b>${info.maxKg} kg</b> (2-machine) limit for one booking.<br><br>`
            + `Do you want to proceed with the excess? Our staff may split the extra load or it may require another visit.`,
        showCancelButton: true,
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'No, adjust weight',
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#94a3b8'
    });
    return res.isConfirmed;
}

async function goToNextStep() {
    if (validateCurrentStep()) {
        saveCurrentStepData();

        // Confirm before leaving Step 2 if the laundry exceeds machine capacity
        if (currentStep === 2 && !(await confirmExcessWeight())) {
            return;
        }

        // Check if on Step 2 with self-service: show confirmation modal instead of going to Step 3
        if (currentStep === 2 && bookingData.step2.isSelfService) {
            document.getElementById('selfServiceConfirmationModal').classList.add('show');
            document.getElementById('selfServiceConfirmationModal').style.display = 'block';
            document.body.classList.add('modal-open');
            return;
        }
        
        currentStep++;
        if (currentStep > totalSteps) currentStep = totalSteps;
        updateWizardDisplay();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function goToPreviousStep() {
    saveCurrentStepData();
    currentStep--;
    if (currentStep < 1) currentStep = 1;
    clearAllValidationErrors();
    updateWizardDisplay();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep(stepNumber) {
    if (stepNumber >= 1 && stepNumber <= totalSteps && validateCurrentStep()) {
        saveCurrentStepData();
        currentStep = stepNumber;
        updateWizardDisplay();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ===== STEP VALIDATION =====

// Check if self-service is selected in services
function isSelfServiceSelected(services) {
    if (!Array.isArray(services)) {
        return false;
    }
    return services.some(service => {
        const serviceLower = service.toLowerCase();
        return serviceLower.includes('self-service') || serviceLower.includes('self service');
    });
}

function validateCurrentStep() {
    clearAllValidationErrors();
    
    switch(currentStep) {
        case 1:
            return validateStep1();
        case 2:
            return validateStep2();
        case 3:
            return validateStep3();
        default:
            return false;
    }
}

function validateStep1() {
    let isValid = true;
    const bookingDate = document.getElementById('booking_date').value;
    const selectedTimeSlot = document.getElementById('selected_time_slot').value;

    if (!bookingDate) {
        showError('Please select a date on the calendar', 'step-1-error');
        isValid = false;
    }

    if (!selectedTimeSlot) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Please select a time slot.',
            confirmButtonText: 'OK'
        });
        isValid = false;
    }

    if (isValid) {
        showSuccess('Date and time selected successfully!', 'step-1-success');
    }

    return isValid;
}

function validateStep2() {
    let isValid = true;
    const serviceType = document.getElementById('service-type');
    const machineType = document.getElementById('machine-type');
    const machineCount = document.getElementById('machine-count').value;
    const laundryWeightEl = document.getElementById('laundry-weight');
    const laundryWeight = laundryWeightEl ? parseFloat(laundryWeightEl.value) : 0;

    if (!serviceType.value || serviceType.value.length === 0) {
        showError('Please select at least one service', 'step-2-error');
        isValid = false;
    } else {
        // Validation: Fold cannot be a standalone service
        const selectedServices = $(serviceType).val() || [];
        const hasFold = selectedServices.some(s => s === 'Fold');
        const hasPrimaryService = selectedServices.some(s => 
            s === 'Full-Service - Wash & Dry' || 
            s === 'Self-Service - Washer' || 
            s === 'Self-Service - Dryer'
        );

        if (hasFold && !hasPrimaryService) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Service Selection',
                text: 'The "Fold" service cannot be selected alone. It must be paired with a washing or drying service.',
                confirmButtonText: 'OK'
            });
            isValid = false;
        }
    }

    // Laundry weight must be set before machines can be chosen
    if (isValid && (!laundryWeight || laundryWeight <= 0)) {
        showError('Please enter the laundry weight (kg) before selecting a machine', 'step-2-error');
        isValid = false;
    }

    if (isValid && (!machineType.value || machineType.value.length === 0)) {
        showError('Please select at least one machine', 'step-2-error');
        isValid = false;
    }

    if (!machineCount || machineCount < 1) {
        showError('Please enter a valid number of machines', 'step-2-error');
        isValid = false;
    }

    if (isValid) {
        showSuccess('Services and machines selected successfully!', 'step-2-success');
    }

    return isValid;
}

function validateStep3() {
    let isValid = true;
    const errors = {};

    // Helper to get value from step3 field or fallback to user profile hidden field
    const getVal = (step3Id, fallbackId) => {
        const el = document.getElementById(step3Id);
        if (el) return el.value.trim();
        const fallbackEl = document.getElementById(fallbackId);
        return fallbackEl ? fallbackEl.value.trim() : '';
    };

    const firstName = getVal('step3-first-name', 'user_first_name');
    const lastName = getVal('step3-last-name', 'user_last_name');
    const mobile = getVal('step3-mobile', 'user_phone');
    const email = getVal('step3-email', 'user_email');
    
    const pickupChecked = document.getElementById('pickup') ? document.getElementById('pickup').checked : false;
    const deliveryChecked = document.getElementById('delivery') ? document.getElementById('delivery').checked : false;
    
    const pickupAddressEl = document.getElementById('step3-pickup-address');
    const deliveryAddressEl = document.getElementById('step3-delivery-address');
    
    const pickupAddress = pickupAddressEl ? pickupAddressEl.value.trim() : '';
    const deliveryAddress = deliveryAddressEl ? deliveryAddressEl.value.trim() : '';

    // First Name
    if (!firstName) {
        if (document.getElementById('step3-first-name')) errors['step3-first-name'] = 'First name is required';
        isValid = false;
    }

    // Last Name
    if (!lastName) {
        if (document.getElementById('step3-last-name')) errors['step3-last-name'] = 'Last name is required';
        isValid = false;
    }

    // Mobile
    if (!mobile || mobile.length < 10) {
        if (document.getElementById('step3-mobile')) errors['step3-mobile'] = 'Valid mobile number is required';
        isValid = false;
    }

    // Email
    if (!email || !isValidEmail(email)) {
        if (document.getElementById('step3-email')) errors['step3-email'] = 'Valid email address is required';
        isValid = false;
    }

    // Pickup Address (only if checked)
    if (pickupChecked && !pickupAddress) {
        if (pickupAddressEl) {
            errors['step3-pickup-address'] = 'Pickup address is required';
            isValid = false;
        }
    }

    // Delivery Address (only if checked)
    if (deliveryChecked && !deliveryAddress) {
        if (deliveryAddressEl) {
            errors['step3-delivery-address'] = 'Delivery address is required';
            isValid = false;
        }
    }

    // Display errors
    Object.keys(errors).forEach(fieldId => {
        const errorElement = document.getElementById('error-' + fieldId.replace('step3-', ''));
        if (errorElement) {
            errorElement.textContent = errors[fieldId];
            errorElement.classList.remove('d-none');
            errorElement.style.display = 'block';
        }
    });

    if (!isValid && Object.keys(errors).length === 0) {
        // If invalid but no visible errors (e.g. missing contact info but fields are hidden)
        Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Please ensure your profile information is complete.',
            confirmButtonText: 'OK'
        });
    }

    if (isValid) {
        showSuccess('Booking details ready!', 'step-3-success');
    }

    return isValid;
}

// ===== DATA MANAGEMENT =====

function saveCurrentStepData() {
    switch(currentStep) {
        case 1:
            const dateEl = document.getElementById('booking_date');
            const timeEl = document.getElementById('selected_time_slot');
            if (dateEl) bookingData.step1.date = dateEl.value;
            if (timeEl) bookingData.step1.timeSlot = timeEl.value;
            break;
        case 2:
            const serviceType = document.getElementById('service-type');
            // Use jQuery .val() to correctly get array from multi-select
            bookingData.step2.services = serviceType ? ($(serviceType).val() || []) : [];
            bookingData.step2.isSelfService = isSelfServiceSelected(bookingData.step2.services);
            
            const machineType = document.getElementById('machine-type');
            bookingData.step2.machines = machineType ? ($(machineType).val() || []) : [];
            
            const countEl = document.getElementById('machine-count');
            bookingData.step2.machineCount = countEl ? (countEl.value || 1) : 1;
            const weightEl2 = document.getElementById('laundry-weight');
            bookingData.step2.weight = weightEl2 ? (parseFloat(weightEl2.value) || 0) : 0;
            bookingData.step2.laundryItems = window.selectedLaundryItems ? [...window.selectedLaundryItems] : [];
            break;
        case 3:
            const firstNameEl = document.getElementById('step3-first-name') || document.getElementById('user_first_name');
            const lastNameEl = document.getElementById('step3-last-name') || document.getElementById('user_last_name');
            const mobileEl = document.getElementById('step3-mobile') || document.getElementById('user_phone');
            const emailEl = document.getElementById('step3-email') || document.getElementById('user_email');
            const pickupEl = document.getElementById('step3-pickup-address');
            const deliveryEl = document.getElementById('step3-delivery-address');
            
            if (firstNameEl) bookingData.step3.firstName = firstNameEl.value;
            if (lastNameEl) bookingData.step3.lastName = lastNameEl.value;
            if (mobileEl) bookingData.step3.mobile = mobileEl.value;
            if (emailEl) bookingData.step3.email = emailEl.value;
            if (pickupEl) bookingData.step3.pickupAddress = pickupEl.value;
            if (deliveryEl) bookingData.step3.deliveryAddress = deliveryEl.value;
            break;
    }
    saveToLocalStorage();
}

function restoreFromLocalStorage() {
    const stored = localStorage.getItem('bookingWizardData');
    if (stored) {
        try {
            const data = JSON.parse(stored);
            bookingData = { ...bookingData, ...data };
            restoreFormData();
        } catch(e) {
            console.log('Could not restore from localStorage');
        }
    }
}

function saveToLocalStorage() {
    localStorage.setItem('bookingWizardData', JSON.stringify(bookingData));
}

function clearBookingData() {
    bookingData = {
        step1: { date: '', timeSlot: '' },
        step2: { services: [], machines: [], machineCount: 0, weight: 0, laundryItems: [], isSelfService: false },
        step3: { firstName: '', lastName: '', mobile: '', email: '', pickupAddress: '', deliveryAddress: '', pickupCoords: '', deliveryCoords: '' }
    };
    localStorage.removeItem('bookingWizardData');
}

function restoreFormData() {
    // Restore Step 1
    if (bookingData.step1.date) {
        document.getElementById('booking_date').value = bookingData.step1.date;
    }
    if (bookingData.step1.timeSlot) {
        document.getElementById('selected_time_slot').value = bookingData.step1.timeSlot;
        const timeSlotElement = document.querySelector(`.time-slot[data-slot="${bookingData.step1.timeSlot}"]`);
        if (timeSlotElement) {
            timeSlotElement.classList.add('selected');
        }
    }

    // Restore Step 2
    if (bookingData.step2.services && bookingData.step2.services.length > 0) {
        const serviceSelect = document.getElementById('service-type');
        $(serviceSelect).val(bookingData.step2.services).trigger('change');
    }
    if (bookingData.step2.machines && bookingData.step2.machines.length > 0) {
        const machineSelect = document.getElementById('machine-type');
        $(machineSelect).val(bookingData.step2.machines).trigger('change');
    }
    if (bookingData.step2.machineCount) {
        document.getElementById('machine-count').value = bookingData.step2.machineCount;
    }

    // Restore Step 3
    if (bookingData.step3.firstName) {
        document.getElementById('step3-first-name').value = bookingData.step3.firstName;
    }
    if (bookingData.step3.lastName) {
        document.getElementById('step3-last-name').value = bookingData.step3.lastName;
    }
    if (bookingData.step3.mobile) {
        document.getElementById('step3-mobile').value = bookingData.step3.mobile;
    }
    if (bookingData.step3.email) {
        document.getElementById('step3-email').value = bookingData.step3.email;
    }
    if (bookingData.step3.pickupAddress) {
        document.getElementById('step3-pickup-address').value = bookingData.step3.pickupAddress;
    }
    if (bookingData.step3.deliveryAddress) {
        document.getElementById('step3-delivery-address').value = bookingData.step3.deliveryAddress;
    }
}

// ===== WIZARD DISPLAY =====

function updateWizardDisplay() {
    // Hide all steps
    document.querySelectorAll('.booking-step').forEach(step => {
        step.style.display = 'none';
    });

    // Show current step
    const currentStepElement = document.getElementById(`step-${currentStep}-content`);
    if (currentStepElement) {
        currentStepElement.style.display = 'block';
    }

    // Update step circles
    updateStepIndicators();

    // Update buttons
    updateButtons();

    // Update progress bar
    updateProgressBar();
}

function updateStepIndicators() {
    for (let i = 1; i <= totalSteps; i++) {
        const circle = document.getElementById(`step-${i}-circle`);
        if (circle) {
            if (i === currentStep) {
                circle.classList.add('active');
            } else {
                circle.classList.remove('active');
            }
        }
    }
}

function updateProgressBar() {
    const progressBar = document.getElementById('progress-bar');
    if (progressBar) {
        const percentage = (currentStep / totalSteps) * 100;
        progressBar.style.width = percentage + '%';
    }
}

function updateButtons() {
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.getElementById('submit-btn');

    if (currentStep === 1) {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'block';
        submitBtn.style.display = 'none';
    } else if (currentStep === 2 && bookingData.step2.isSelfService) {
        // Self-service on Step 2: show Submit button instead of Next
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'block';
    } else if (currentStep === totalSteps) {
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'block';
    } else {
        prevBtn.style.display = 'block';
        nextBtn.style.display = 'block';
        submitBtn.style.display = 'none';
    }
}

// ===== FORM SUBMISSION =====

// Function to submit the final booking
async function submitBooking() {
    if (validateCurrentStep()) {
        saveCurrentStepData();

        // Self-service submits straight from Step 2 — confirm excess weight here too
        if (currentStep === 2 && !(await confirmExcessWeight())) {
            return;
        }

        // --- Calculate Summary & Prices ---
        const allServiceOptions = Array.from(document.querySelectorAll('#service-type option'));
        const foldServiceOpt = allServiceOptions.find(opt => opt.text.toLowerCase().includes('fold'));
        const foldPrice = foldServiceOpt ? parseFloat(foldServiceOpt.getAttribute('data-price')) || 0 : 0;

        let currentTotal = 0;
        const selectedServicesNames = [];
        
        // Use $(el).val() to get current selection
        const currentServices = $('#service-type').val() || [];
        currentServices.forEach(val => {
            const opt = allServiceOptions.find(o => o.value === val);
            if (opt) {
                const price = parseFloat(opt.getAttribute('data-price')) || 0;
                currentTotal += price;
                selectedServicesNames.push(opt.getAttribute('data-name') || opt.text.split(' — ')[0].trim());
            }
        });

        // Supplies Total
        bookingData.step2.laundryItems.forEach(item => {
            const supplyOpt = Array.from(document.querySelectorAll('#laundry-item-select option')).find(o => o.value === item.name);
            const price = supplyOpt ? parseFloat(supplyOpt.getAttribute('data-price')) || 0 : 0;
            currentTotal += (price * item.qty);
        });

        const hasFoldAlready = selectedServicesNames.some(n => n.toLowerCase().includes('fold'));
        const foldHtml = (foldServiceOpt && !hasFoldAlready) ? `
            <div style="text-align: left; margin: 15px 0; padding: 15px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                <label style="display: flex; align-items: flex-start; cursor: pointer; gap: 12px; margin-bottom: 0;">
                    <input type="checkbox" id="foldCheckbox" style="margin-top: 4px; width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1;">
                    <div>
                        <b style="color: #1e293b; font-size: 1rem;">Add Fold Service (Optional)</b>
                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">We'll neatly fold your clean clothes for you</div>
                        <div style="font-size: 0.9rem; color: #6366f1; font-weight: 700; margin-top: 4px;">+ ₱${foldPrice.toFixed(2)}</div>
                    </div>
                </label>
            </div>
        ` : '';

        // --- Determine requested services (Pickup/Delivery) ---
        const requestedServices = [];
        if (document.getElementById('pickup') && document.getElementById('pickup').checked) requestedServices.push('Pickup');
        if (document.getElementById('delivery') && document.getElementById('delivery').checked) requestedServices.push('Delivery');

        // --- Show Confirmation Modal ---
        const confirmResult = await Swal.fire({
            title: 'Confirm Booking',
            html: `
                <div style="text-align: left; font-size: 0.95rem;">
                    <div style="margin-bottom: 10px;"><i class="fas fa-calendar-alt me-2" style="color: #6366f1;"></i> <b>Date:</b> ${bookingData.step1.date}</div>
                    <div style="margin-bottom: 10px;"><i class="fas fa-clock me-2" style="color: #6366f1;"></i> <b>Time:</b> ${bookingData.step1.timeSlot}</div>
                    <div style="margin-bottom: 10px;"><i class="fas fa-concierge-bell me-2" style="color: #6366f1;"></i> <b>Services:</b> ${selectedServicesNames.join(', ')}</div>
                    <div style="margin-bottom: 10px;"><i class="fas fa-weight-hanging me-2" style="color: #6366f1;"></i> <b>Weight:</b> ${bookingData.step2.weight} kg (${bookingData.step2.machineCount} machine/s)</div>
                    <div style="margin-bottom: 10px;"><i class="fas fa-truck me-2" style="color: #6366f1;"></i> <b>Request:</b> ${requestedServices.join(' & ') || 'None (Drop-off)'}</div>
                    
                    <div style="background: #f1f5f9; padding: 15px; border-radius: 12px; margin: 15px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span>Booking Subtotal:</span>
                            <span style="font-weight: 600;">₱${currentTotal.toFixed(2)}</span>
                        </div>
                        <div id="foldRow" style="display: none; justify-content: space-between; margin-bottom: 8px; color: #6366f1;">
                            <span>+ Fold Service:</span>
                            <span style="font-weight: 600;">₱${foldPrice.toFixed(2)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 2px solid #cbd5e1; padding-top: 10px; font-size: 1.1rem; color: #1e293b;">
                            <b>Final Amount:</b>
                            <b id="finalAmountDisplay">₱${currentTotal.toFixed(2)}</b>
                        </div>
                    </div>
                    ${foldHtml}
                </div>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check me-2"></i> Confirm & Book',
            cancelButtonText: 'Go Back',
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#94a3b8',
            didOpen: () => {
                const cb = document.getElementById('foldCheckbox');
                if (cb) {
                    cb.addEventListener('change', (e) => {
                        const foldRow = document.getElementById('foldRow');
                        const finalDisplay = document.getElementById('finalAmountDisplay');
                        const total = e.target.checked ? (currentTotal + foldPrice) : currentTotal;
                        foldRow.style.display = e.target.checked ? 'flex' : 'none';
                        finalDisplay.innerText = `₱${total.toFixed(2)}`;
                    });
                }
            }
        });

        if (!confirmResult.isConfirmed) return;

        // --- Process Submission ---
        // Collect location details
        const locationDetails = document.getElementById('location-details') ? document.getElementById('location-details').value : '';
        
        // Final service list (add fold if checked)
        const finalServices = [...currentServices];
        if (document.getElementById('foldCheckbox') && document.getElementById('foldCheckbox').checked) {
            finalServices.push(foldServiceOpt.value);
        }

        const formData = {
            booking_date: bookingData.step1.date,
            time_slot: bookingData.step1.timeSlot,
            service_type: finalServices,
            machine: $('#machine-type').val() || [],
            machine_count: bookingData.step2.machineCount,
            estimated_weight: bookingData.step2.weight,
            detergent: bookingData.step2.laundryItems,
            customer_first_name: bookingData.step3.firstName,
            customer_last_name: bookingData.step3.lastName,
            customer_mobile: bookingData.step3.mobile,
            customer_email: bookingData.step3.email,
            pickup_address: bookingData.step3.pickupAddress,
            delivery_address: bookingData.step3.deliveryAddress,
            pickup_coordinates: bookingData.step3.pickupCoords,
            delivery_coordinates: bookingData.step3.deliveryCoords,
            location_details: locationDetails,
            request_service: requestedServices
        };

        // Show loading state
        Swal.fire({
            title: 'Processing Booking...',
            text: 'Please wait while we secure your slot.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Send via AJAX
        fetch('booking_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Server returned non-JSON response:', text);
                throw new Error('Server returned an invalid response. Check console for details.');
            }
        })
        .then(data => {
            if (data.status === 'success' || data.success === true) {
                // ✅ Clear localStorage after successful form submission
                localStorage.removeItem('bookingWizardData');
                
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Successful!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = data.redirect || 'booking_confirmation.php?id=' + data.booking_id;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Booking Failed',
                    text: data.message || 'An error occurred during processing.'
                });
            }
        })
        .catch(error => {
            console.error('Submission Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Submission Error',
                text: error.message || 'Could not connect to the server. Please try again.'
            });
        });
    }
}

function prepareFormForSubmission() {
    // Step 1 data
    const dateEl = document.getElementById('booking_date');
    const timeEl = document.getElementById('selected_time_slot');
    if (dateEl) dateEl.value = bookingData.step1.date;
    if (timeEl) timeEl.value = bookingData.step1.timeSlot;

    // Step 2 data
    const servicesEl = document.getElementById('selected_services_json');
    const machinesEl = document.getElementById('selected_machines_json');
    const machineCountEl = document.getElementById('hidden_machine_count');
    const suppliesEl = document.getElementById('selected_laundry_supplies_json');
    
    if (servicesEl) servicesEl.value = JSON.stringify(bookingData.step2.services);
    if (machinesEl) machinesEl.value = JSON.stringify(bookingData.step2.machines);
    if (machineCountEl) machineCountEl.value = bookingData.step2.machineCount;
    if (suppliesEl) suppliesEl.value = JSON.stringify(bookingData.step2.laundryItems);

    // Step 3 data
    const fNameEl = document.getElementById('customer_first_name');
    const lNameEl = document.getElementById('customer_last_name');
    const mobileEl = document.getElementById('customer_mobile');
    const emailEl = document.getElementById('customer_email');
    const pickupEl = document.getElementById('pickup_address');
    const deliveryEl = document.getElementById('delivery_address');
    const pickupCoordsEl = document.getElementById('pickup_coordinates');
    const deliveryCoordsEl = document.getElementById('delivery_coordinates');
    const locationDetailsEl = document.getElementById('location_details_hidden'); // New hidden field if we add it, or just use ID match

    if (fNameEl) fNameEl.value = bookingData.step3.firstName;
    if (lNameEl) lNameEl.value = bookingData.step3.lastName;
    if (mobileEl) mobileEl.value = bookingData.step3.mobile;
    if (emailEl) emailEl.value = bookingData.step3.email;
    if (pickupEl) pickupEl.value = bookingData.step3.pickupAddress;
    if (deliveryEl) deliveryEl.value = bookingData.step3.deliveryAddress;
    if (pickupCoordsEl) pickupCoordsEl.value = bookingData.step3.pickupCoords;
    if (deliveryCoordsEl) deliveryCoordsEl.value = bookingData.step3.deliveryCoords;
    
    // Request service (checkboxes)
    // Note: The checkboxes in Step 3 are already part of the form and will be submitted normally 
    // because they have 'name="request_service[]"'.
}

// ===== UTILITY FUNCTIONS =====

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showError(message, containerId = null) {
    if (containerId) {
        let container = document.getElementById(containerId);
        if (!container) {
            const step = document.getElementById(`step-${currentStep}-content`);
            if (step) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'alert alert-danger mt-3';
                step.insertBefore(container, step.firstChild);
            }
        }
        if (container) {
            container.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            container.style.display = 'block';
        }
    }
    Swal.fire({
        icon: 'warning',
        title: 'Validation Error',
        text: message,
        confirmButtonText: 'OK'
    });
}

function showSuccess(message, containerId = null) {
    if (containerId) {
        let container = document.getElementById(containerId);
        if (!container) {
            const step = document.getElementById(`step-${currentStep}-content`);
            if (step) {
                container = document.createElement('div');
                container.id = containerId;
                container.className = 'alert alert-success mt-3';
                step.insertBefore(container, step.firstChild);
            }
        }
        if (container) {
            container.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            container.style.display = 'block';
            setTimeout(() => {
                container.style.display = 'none';
            }, 3000);
        }
    }
}

function clearAllValidationErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.classList.add('d-none');
    });
    document.querySelectorAll('[id$="-error"], [id$="-success"]').forEach(el => {
        el.style.display = 'none';
    });
}

// ===== SELECT2 INITIALIZATION FOR STEP 2 =====

function initializeSelectTwoForStep2() {
    // Already handled in book-now.php, but ensure it's ready when needed
    setTimeout(() => {
        if ($('#service-type').length && !$('#service-type').data('select2')) {
            $('#service-type').select2({
                placeholder: "Select services",
                allowClear: true,
                width: '100%'
            });
        }
        
        if ($('#machine-type').length && !$('#machine-type').data('select2')) {
            $('#machine-type').select2({
                placeholder: "Select machines",
                allowClear: true,
                width: '100%'
            });
        }
    }, 100);
}

// ===== ADDRESS VALIDATION =====

function setupAddressValidation() {
    const pickupInput = document.getElementById('step3-pickup-address');
    const deliveryInput = document.getElementById('step3-delivery-address');

    if (pickupInput) {
        pickupInput.addEventListener('change', function() {
            validateAddress(this.value, 'pickup');
        });
    }

    if (deliveryInput) {
        deliveryInput.addEventListener('change', function() {
            validateAddress(this.value, 'delivery');
        });
    }
}

function validateAddress(address, type) {
    // Basic validation - can be enhanced with Google Geocoding API later
    if (address.trim().length < 5) {
        showError(`Please enter a valid ${type} address`);
        return false;
    }
    return true;
}

// ===== GOOGLE MAPS INTEGRATION (Placeholder for Phase 3) =====

function initializeGoogleMaps() {
    // This will be implemented with actual Google Maps API
    console.log('Google Maps initialization - to be implemented');
}

// ===== SELF-SERVICE BOOKING CONFIRMATION =====

function confirmSelfServiceBooking() {
    // Close the confirmation modal
    closeSelfServiceConfirmationModal();
    
    // For self-service bookings, auto-fill Step 3 fields with user info (if available)
    // so they don't need to fill delivery addresses
    const firstNameField = document.getElementById('step3-first-name');
    const lastNameField = document.getElementById('step3-last-name');
    
    // Use existing user profile data if available
    if (firstNameField && !firstNameField.value && document.getElementById('user_first_name')) {
        firstNameField.value = document.getElementById('user_first_name').value;
    }
    if (lastNameField && !lastNameField.value && document.getElementById('user_last_name')) {
        lastNameField.value = document.getElementById('user_last_name').value;
    }
    
    // For self-service, we fill mobile and email from user profile
    const mobileField = document.getElementById('step3-mobile');
    const emailField = document.getElementById('step3-email');
    
    if (mobileField && !mobileField.value && document.getElementById('user_phone')) {
        mobileField.value = document.getElementById('user_phone').value;
    }
    if (emailField && !emailField.value && document.getElementById('user_email')) {
        emailField.value = document.getElementById('user_email').value;
    }
    
    // For self-service, pickup and delivery addresses are not needed
    // Set them to placeholder values so validation passes
    const pickupAddressField = document.getElementById('step3-pickup-address');
    const deliveryAddressField = document.getElementById('step3-delivery-address');
    
    if (pickupAddressField && !pickupAddressField.value) {
        pickupAddressField.value = 'Self-Service - On Location';
    }
    if (deliveryAddressField && !deliveryAddressField.value) {
        deliveryAddressField.value = 'Self-Service - On Location';
    }
    
    // Save the data and submit
    bookingData.step3.firstName = firstNameField ? firstNameField.value : '';
    bookingData.step3.lastName = lastNameField ? lastNameField.value : '';
    bookingData.step3.mobile = mobileField ? mobileField.value : '';
    bookingData.step3.email = emailField ? emailField.value : '';
    bookingData.step3.pickupAddress = pickupAddressField ? pickupAddressField.value : 'Self-Service - On Location';
    bookingData.step3.deliveryAddress = deliveryAddressField ? deliveryAddressField.value : 'Self-Service - On Location';
    
    submitBooking();
}

function cancelSelfServiceBooking() {
    // Close the confirmation modal and return to Step 2
    closeSelfServiceConfirmationModal();
}

function closeSelfServiceConfirmationModal() {
    const modal = document.getElementById('selfServiceConfirmationModal');
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

// Ensure existing calendar and time slot functions still work
window.addEventListener('load', function() {
    // Make sure existing functions from booking-form.js are compatible
    console.log('Wizard loaded and ready');
});
