// assets/js/reschedule.js
// Reschedule booking functionality - matches book-now.js behavior

// ============================================
// GLOBAL VARIABLES
// ============================================
let currentDate = new Date();
let selectedDate = null;
let selectedLaundryItems = [];
let bookedSlotsCache = {};

// ============================================
// INITIALIZE ON PAGE LOAD
// ============================================
document.addEventListener("DOMContentLoaded", function () {
    console.log("Reschedule JS: DOMContentLoaded fired");
    initReschedulePage();
    initSelect2();
    setupEventListeners();
});

// ============================================
// CALENDAR FUNCTIONS
// ============================================
async function initReschedulePage() {
    console.log("initReschedulePage: Starting initialization");

    // Get initial booking date from hidden field
    const bookingDateField = document.getElementById('booking_date');
    if (bookingDateField && bookingDateField.value) {
        selectedDate = bookingDateField.value;
        const dateParts = selectedDate.split('-');
        currentDate = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
        console.log("initReschedulePage: Booking date found:", selectedDate);
    } else {
        currentDate = new Date();
        console.log("initReschedulePage: No booking date, using today");
    }

    // Load laundry items from JSON
    const laundryField = document.getElementById('selected_laundry_supplies_json');
    if (laundryField && laundryField.value) {
        try {
            selectedLaundryItems = JSON.parse(laundryField.value);
            updateLaundryDisplay();
            console.log("initReschedulePage: Laundry items loaded:", selectedLaundryItems);
        } catch (e) {
            console.error('Error parsing laundry items:', e);
        }
    }

    // Initialize the calendar
    console.log("initReschedulePage: Calling updateCalendar");
    await updateCalendar();

    // Load initial time slot availability if date is selected
    if (selectedDate) {
        console.log("initReschedulePage: Loading time slots for", selectedDate);
        await updateTimeSlots(selectedDate);
    }
}

async function fetchBookedDays(year, month) {
    const monthString = `${year}-${(month + 1).toString().padStart(2, "0")}`;
    const base = window.RESCHEDULE_API_BASE || 'book-now.php';
    try {
        console.log(`fetchBookedDays: Fetching for ${monthString}`);
        const response = await fetch(`${base}?action=get_booked_days&month=${monthString}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        const data = await response.json();
        console.log(`fetchBookedDays: Success, got ${data.length || 0} booked days`, data);
        return data;
    } catch (error) {
        console.error('Error fetching booked days:', error);
        return [];
    }
}

async function fetchHolidays(year, month) {
    const monthString = `${year}-${(month + 1).toString().padStart(2, "0")}`;
    const base = window.RESCHEDULE_API_BASE || 'book-now.php';
    try {
        const response = await fetch(`${base}?action=get_holidays&month=${monthString}`);
        if (!response.ok) throw new Error('Network response was not ok');
        return await response.json();
    } catch (error) {
        console.error('Error fetching holidays:', error);
        return {};
    }
}

async function updateCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];

    const monthYearElement = document.getElementById("current-month-year");
    if (monthYearElement) {
        monthYearElement.textContent = `${monthNames[month]} ${year}`;
    }

    const firstDay = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();

    console.log(`Calendar: Rendering ${monthNames[month]} ${year} (days: ${totalDays}, firstDay: ${firstDay})`);

    // Fetch booked days for this month
    const bookedDays = await fetchBookedDays(year, month);
    // Fetch holidays for this month
    const holidays = await fetchHolidays(year, month);
    console.log(`Calendar: Fetched booked days:`, bookedDays);

    const calendarBody = document.getElementById("calendar-body");
    if (!calendarBody) {
        console.error('Calendar body not found');
        return;
    }

    let calendarHTML = "";
    let day = 1;

    for (let i = 0; i < 6; i++) {
        if (day > totalDays) break;

        calendarHTML += "<tr>";

        for (let j = 0; j < 7; j++) {
            if (i === 0 && j < firstDay) {
                calendarHTML += "<td></td>";
            } else if (day > totalDays) {
                calendarHTML += "<td></td>";
            } else {
                const dateString = `${year}-${(month + 1).toString().padStart(2, "0")}-${day.toString().padStart(2, "0")}`;
                const isBooked = bookedDays.includes(dateString);
                const isPast = isDateInPast(dateString);
                const holidayName = holidays[dateString] || null;

                let className = "calendar-day";
                if (isPast) {
                    className += " past-date";
                } else {
                    if (isBooked) className += " booked-day";
                    if (holidayName) className += " holiday-day";
                }

                if (selectedDate === dateString) {
                    className += " selected";
                }

                const clickHandler = !isPast ? `onclick="selectCalendarDate(event)"` : "";
                let title = "";
                if (isPast) {
                    title = "Date has passed";
                } else {
                    let statusParts = [];
                    if (holidayName) statusParts.push(`Holiday: ${holidayName}`);
                    if (isBooked) statusParts.push("This day has booked slots");
                    
                    if (statusParts.length > 0) {
                        title = statusParts.join(" | ");
                    } else {
                        title = "Click to select";
                    }
                }

                calendarHTML += `
                    <td class="${className}" data-date="${dateString}" data-holiday="${holidayName || ''}" title="${title}" ${clickHandler}>
                        ${day}
                        <div class="indicator-container">
                            ${isBooked && !isPast ? '<div class="booking-indicator"></div>' : ''}
                            ${holidayName && !isPast ? '<div class="holiday-indicator"></div>' : ''}
                        </div>
                    </td>`;
                day++;
            }
        }

        calendarHTML += "</tr>";
    }

    console.log(`Calendar: HTML generated, rendering ${day - 1} days`);
    calendarBody.innerHTML = calendarHTML;
}

function isDateInPast(dateString) {
    const selectedDateObj = new Date(dateString + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return selectedDateObj < today;
}

function selectCalendarDate(event) {
    if (!event || !event.currentTarget) return;
    event.preventDefault();
    event.stopPropagation();

    const element = event.currentTarget;
    const dateString = element.dataset.date;

    if (isDateInPast(dateString)) {
        Swal.fire({
            title: "Past Date",
            text: "Cannot select a date that has already passed.",
            icon: "warning",
            timer: 2000
        });
        return;
    }

    selectedDate = dateString;
    document.getElementById('booking_date').value = dateString;

    // Update calendar display
    document.querySelectorAll('.calendar-day').forEach(cell => {
        cell.classList.remove('selected');
    });
    document.querySelector(`.calendar-day[data-date='${dateString}']`)?.classList.add('selected');

    // Update time slots and availability  
    updateTimeSlots(dateString);

    // Clear selected time slot
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected');
    });
    document.getElementById('time_slot').value = '';
}

// ============================================
// MONTH NAVIGATION
// ============================================
function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    updateCalendar();
}

// ============================================
// TIME SLOT FUNCTIONS
// ============================================
async function updateTimeSlots(selectedDate) {
    const timeSlotElements = document.querySelectorAll(".time-slot");
    if (timeSlotElements.length === 0) {
        console.warn("No time slot elements found");
        return;
    }

    try {
        // Fetch availability data from the server
        const response = await fetch(`/jorishlaundry/user/book-now.php?action=get_availability&date=${selectedDate}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const availabilityData = await response.json();

        // Update each time slot with its availability
        timeSlotElements.forEach(slot => {
            const slotName = slot.dataset.slot || slot.querySelector('span')?.textContent?.trim();

            if (!slotName) {
                console.warn("Could not extract slot name from slot element");
                return;
            }

            const slotData = availabilityData[slotName];
            const machineAvailableElement = slot.querySelector('.machine-available');
            const chipsEl = slot.querySelector('.machine-chips');

            if (slotData) {
                const availableWashers = slotData.washers?.available ?? 0;
                const availableDryers = slotData.dryers?.available ?? 0;
                const totalWashers = slotData.washers?.total ?? 6;
                const totalDryers = slotData.dryers?.total ?? 6;
                const washerMachines = slotData.washers?.machines ?? [];
                const dryerMachines = slotData.dryers?.machines ?? [];
                const fullyBooked = (availableWashers <= 0 && availableDryers <= 0);

                // Update the display text (summary)
                if (machineAvailableElement) {
                    machineAvailableElement.textContent = fullyBooked
                        ? "Fully Booked"
                        : `${availableWashers} Washer${availableWashers !== 1 ? 's' : ''} • ${availableDryers} Dryer${availableDryers !== 1 ? 's' : ''} free`;
                }

                // Populate machine chips with actual machine names
                if (chipsEl) {
                    if (fullyBooked) {
                        chipsEl.innerHTML = '';
                    } else {
                        let html = '';
                        washerMachines.forEach(m => {
                            html += `<span class="mchip mchip-washer"><i class="fas fa-drum-steelpan"></i>${m}</span>`;
                        });
                        dryerMachines.forEach(m => {
                            html += `<span class="mchip mchip-dryer"><i class="fas fa-wind"></i>${m}</span>`;
                        });
                        chipsEl.innerHTML = html;
                    }
                }

                // Update status and styling
                slot.classList.remove("booked-slot", "fully-booked");
                slot.style.pointerEvents = fullyBooked ? "none" : "auto";
                slot.style.opacity = fullyBooked ? "0.5" : "1";

                if (fullyBooked) {
                    slot.classList.add("fully-booked");
                    slot.title = "All machines are booked for this slot";
                } else if (availableWashers < totalWashers || availableDryers < totalDryers) {
                    slot.classList.add("booked-slot");
                    slot.title = "Some machines are taken — select the free ones below";
                } else {
                    slot.title = "All machines free — click to select this slot";
                }

                // Store availability data in data attributes
                slot.dataset.availableWashers = availableWashers;
                slot.dataset.availableDryers = availableDryers;
                slot.dataset.totalWashers = totalWashers;
                slot.dataset.totalDryers = totalDryers;
                slot.dataset.washerMachines = JSON.stringify(washerMachines);
                slot.dataset.dryerMachines = JSON.stringify(dryerMachines);
            } else {
                // If no data for this slot, show default and mark as available
                if (machineAvailableElement) {
                    machineAvailableElement.textContent = `6 Washers • 6 Dryers free`;
                }

                // Populate default machine chips
                if (chipsEl) {
                    chipsEl.innerHTML = '';
                }

                slot.classList.remove("booked-slot", "fully-booked");
                slot.style.pointerEvents = "auto";
                slot.style.opacity = "1";
                slot.dataset.availableWashers = 6;
                slot.dataset.availableDryers = 6;
                slot.dataset.totalWashers = 6;
                slot.dataset.totalDryers = 6;
            }
        });

    } catch (error) {
        console.error("Error fetching availability:", error);

        // Fallback: Make all slots available but show error state
        timeSlotElements.forEach(slot => {
            slot.classList.remove("booked-slot", "fully-booked");
            slot.style.pointerEvents = "auto";
            slot.style.opacity = "1";

            const machineAvailableElement = slot.querySelector(".machine-available");
            if (machineAvailableElement) {
                machineAvailableElement.textContent = "(Error loading availability)";
            }

            slot.dataset.availableWashers = 6;
            slot.dataset.availableDryers = 6;
            slot.dataset.totalWashers = 6;
            slot.dataset.totalDryers = 6;
        });
    }
}

function selectTimeSlot(event) {
    if (!event || !event.currentTarget) return;
    event.preventDefault();
    event.stopPropagation();

    const element = event.currentTarget;

    // Don't allow selection if slot is fully booked
    if (element.classList.contains("fully-booked")) {
        Swal.fire({
            title: "Fully Booked",
            text: "This time slot is completely full. Please select another time.",
            icon: "warning",
            timer: 2000
        });
        return;
    }

    // Get machine count from existing booking data
    const machineCount = window.rescheduleData?.selectedMachines?.length || 1;

    // Get available machines from data attributes
    const availableWashers = parseInt(element.dataset.availableWashers) || 0;
    const availableDryers = parseInt(element.dataset.availableDryers) || 0;

    let maxAvailable = availableWashers + availableDryers;

    if (maxAvailable <= 0) {
        Swal.fire({
            title: "No Machines Available",
            text: "No machines are available in this slot.",
            icon: "warning"
        });
        return;
    }

    if (machineCount > maxAvailable) {
        Swal.fire({
            title: "Not Enough Machines",
            text: `This slot only has ${maxAvailable} machine(s) available.`,
            icon: "warning"
        });
        return;
    }

    // Remove selected class from all slots
    document.querySelectorAll(".time-slot").forEach(el => {
        el.classList.remove("selected");
    });

    // Add selected class to this slot
    element.classList.add("selected");

    // Set the hidden input value from data-slot attribute
    const slotTime = element.dataset.slot || element.querySelector('span')?.textContent?.trim() || '';
    document.getElementById("time_slot").value = slotTime;
}

function addLaundryItem() {
    const select = document.getElementById('laundry-item-select');
    const qtyInput = document.getElementById('laundry-item-qty');

    if (!select || !qtyInput) {
        return;
    }

    const itemName = select.value;
    const quantity = parseInt(qtyInput.value, 10) || 1;

    if (!itemName) {
        Swal.fire({
            icon: 'warning',
            title: 'No Item Selected',
            text: 'Please select a laundry item first.',
            confirmButtonText: 'OK'
        });
        return;
    }

    if (quantity < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Quantity',
            text: 'Quantity must be at least 1.',
            confirmButtonText: 'OK'
        });
        return;
    }

    const selectedOption = select.options[select.selectedIndex];
    const availableStock = parseInt(selectedOption?.dataset?.stock, 10) || 0;

    if (quantity > availableStock) {
        Swal.fire({
            icon: 'warning',
            title: 'Insufficient Stock',
            text: `Only ${availableStock} units available for ${itemName}.`,
            confirmButtonText: 'OK'
        });
        return;
    }

    selectedLaundryItems.push({
        name: itemName,
        qty: quantity,
        timestamp: Date.now()
    });

    updateLaundryDisplay();

    select.value = '';
    qtyInput.value = '1';
}

function removeLaundryItem(timestamp) {
    selectedLaundryItems = selectedLaundryItems.filter(item => item.timestamp !== timestamp);
    updateLaundryDisplay();
}

function updateLaundryDisplay() {
    const displayDiv = document.getElementById('selected-laundry-items-display');
    const jsonField = document.getElementById('selected_laundry_supplies_json');

    if (!displayDiv || !jsonField) {
        return;
    }

    if (selectedLaundryItems.length === 0) {
        displayDiv.className = 'selected-laundry-items empty';
        displayDiv.innerHTML = '<em>No items selected yet</em>';
        jsonField.value = '[]';
        return;
    }

    displayDiv.className = 'selected-laundry-items';

    let badgesHTML = '';
    let totalQty = 0;

    selectedLaundryItems.forEach(item => {
        totalQty += item.qty;
        badgesHTML += `
            <div class="laundry-item-badge">
                <span class="qty">${item.qty}x</span>
                <span>${item.name}</span>
                <button type="button" class="remove-btn" onclick="removeLaundryItem(${item.timestamp})" title="Remove this item">
                    ×
                </button>
            </div>
        `;
    });

    badgesHTML += `
        <div class="laundry-summary">
            Total Items: <strong>${totalQty}</strong>
        </div>
    `;

    displayDiv.innerHTML = badgesHTML;
    jsonField.value = JSON.stringify(selectedLaundryItems.map(item => ({
        name: item.name,
        qty: item.qty
    })));
}

// ============================================
// MACHINE SELECTION
// ============================================
function syncSelectedMachines() {
    const machineField = document.getElementById('selected_machines_json');
    const machineSelect = document.getElementById('machine-type');

    if (!machineField || !machineSelect) {
        return;
    }

    const selectedMachines = $(machineSelect).val() || [];
    machineField.value = JSON.stringify(selectedMachines);
}

function initSelect2() {
    // Select2 elements are not present in the reschedule page
    // as it only focuses on date and time changes.
}

// ============================================
// EVENT LISTENERS
// ============================================
function setupEventListeners() {
    // Month navigation
    const prevMonth = document.getElementById('prev-month');
    const nextMonth = document.getElementById('next-month');

    if (prevMonth) {
        prevMonth.addEventListener('click', function (e) {
            e.preventDefault();
            changeMonth(-1);
        });
    }

    if (nextMonth) {
        nextMonth.addEventListener('click', function (e) {
            e.preventDefault();
            changeMonth(1);
        });
    }

    // Form submission
    const form = document.getElementById('rescheduleForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            validateAndSubmit(e);
        });
    }
}

// ============================================
// FORM VALIDATION & SUBMISSION
// ============================================
function validateAndSubmit(e) {
    e.preventDefault();

    const bookingDate = document.getElementById('booking_date')?.value;
    const timeSlot = document.getElementById('time_slot')?.value;
    const machineCount = document.getElementById('machine-count')?.value;

    if (!bookingDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Select Date',
            text: 'Please select a booking date.',
            confirmButtonText: 'OK'
        });
        return;
    }

    if (!timeSlot) {
        Swal.fire({
            icon: 'warning',
            title: 'Select Time',
            text: 'Please select a time slot.',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Machine count is fixed for rescheduling and handled by hidden fields

    // Sync all data before showing confirmation
    updateLaundryDisplay();
    syncSelectedMachines();

    // Show confirmation dialog
    Swal.fire({
        title: 'Confirm Reschedule?',
        text: 'Your booking will be rescheduled to the new date and time.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Reschedule',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('rescheduleForm').submit();
        }
    });
}

// ============================================
// WIZARD NAVIGATION WRAPPER
// ============================================
// Wrapper function for wizard navigation compatibility
async function updateRescheduleTimeSlots(selectedDate) {
    return await updateTimeSlots(selectedDate);
}
