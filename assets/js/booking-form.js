// assets/js/booking-form.js
// Booking form functionality including calendar, time slots, and form submission

// Initialize Select2
$(document).ready(function () {
    $('#service-type').select2({
        placeholder: "Select services",
        width: '100%',
        closeOnSelect: false
    });

    $('#laundry-supplies').select2({
        placeholder: "Select detergents and fabric conditioners",
        width: '100%',
        closeOnSelect: false,
        templateResult: formatInventoryOption,
        templateSelection: formatInventorySelection
    });

    // Add these formatter functions
    function formatInventoryOption(item) {
        if (!item.id) return item.text;

        // Parse "Name — ₱price (N available)"
        const match = item.text.match(/^(.+?)\s*—\s*₱([\d.]+)\s*\((\d+)\s*available\)/);
        const name = match ? match[1].trim() : item.text;
        const price = match ? match[2] : null;
        const stock = match ? parseInt(match[3]) : null;

        let icon, iconColor;
        if (stock === null || stock > 5) {
            icon = 'fa-check-circle'; iconColor = '#22c55e';
        } else if (stock > 0) {
            icon = 'fa-exclamation-triangle'; iconColor = '#f59e0b';
        } else {
            icon = 'fa-times-circle'; iconColor = '#ef4444';
        }

        const stockLabel = stock === null ? '' :
            stock <= 0 ? ' <span style="color:#ef4444;font-size:.75rem">(Out of Stock)</span>'
                : ` <span style="color:#6b7280;font-size:.75rem">(${stock} left)</span>`;

        const priceTag = price
            ? `<span style="margin-left:auto;background:#eef2ff;color:#4338ca;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:700;">₱${price}</span>`
            : '';

        return $(`<span style="display:flex;align-items:center;gap:6px;width:100%">
            <i class="fas ${icon}" style="color:${iconColor};font-size:.85rem;flex-shrink:0"></i>
            <span>${name}${stockLabel}</span>
            ${priceTag}
        </span>`);
    }

    function formatInventorySelection(item) {
        // Show clean "Name — ₱price" in the selected tag
        const match = item.text.match(/^(.+?)\s*—\s*₱([\d.]+)/);
        if (match) return match[1].trim() + ' — ₱' + match[2];
        return item.text;
    }

    // Optional: Add function to refresh inventory periodically
    function refreshInventoryDisplay() {
        fetch(`book-now.php?action=get_inventory`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update each option's text with current stock
                    $('#laundry-supplies option').each(function () {
                        const option = $(this);
                        const itemName = option.val();
                        if (itemName && itemName !== '') {
                            const inventoryItem = data.all_items?.find(i => i.item_name === itemName);
                            if (inventoryItem) {
                                const oldText = option.text();
                                // Pattern: "Name — ₱price (N available)"  or legacy "(N available)"
                                const newText = oldText.replace(/\(\d+ available\)/, `(${inventoryItem.stock_quantity} available)`);
                                option.text(newText);
                            }
                        }
                    });
                    // Trigger Select2 to refresh
                    $('#laundry-supplies').trigger('change.select2');
                }
            })
            .catch(error => console.error('Error refreshing inventory:', error));
    }

    // Refresh inventory every 30 seconds
    setInterval(refreshInventoryDisplay, 30000);

    $('#machine-type').select2({
        placeholder: "Select machines",
        width: '100%',
        closeOnSelect: false
    });
});

// Function to check inventory availability before booking
async function checkInventoryAvailability(selectedItems) {
    try {
        const response = await fetch(`book-now.php?action=get_inventory`);
        if (!response.ok) throw new Error('Failed to fetch inventory');

        const data = await response.json();

        // Check if data.all_items exists
        if (!data.all_items) {
            console.warn('Inventory data missing all_items property');
            return { success: true, unavailableItems: [] }; // Proceed if can't check
        }

        const unavailableItems = [];

        // Check each selected item against current stock
        selectedItems.forEach(itemName => {
            const inventoryItem = data.all_items.find(i => i.item_name === itemName);
            if (!inventoryItem) {
                console.warn(`Item "${itemName}" not found in inventory`);
                return; // Skip if item not found
            }
            if (inventoryItem.stock_quantity <= 0) {
                unavailableItems.push(itemName);
            }
        });

        return {
            success: unavailableItems.length === 0,
            unavailableItems: unavailableItems
        };
    } catch (error) {
        console.error('Error checking inventory:', error);
        return { success: true, unavailableItems: [] };
    }
}

// Helper function to check if only Self-Service - Dryer is selected (ignoring Fold as it's a modifier)
function isDryerOnlySelected() {
    const selectElement = document.getElementById('service-type');
    if (!selectElement) return false;

    const selectedOptions = Array.from(selectElement.selectedOptions);

    // Filter out Fold services - treat fold as optional modifier, not a primary service
    const meaningfulServices = selectedOptions.filter(opt => {
        const serviceName = (opt.getAttribute('data-name') || opt.text).toLowerCase();
        return !serviceName.includes('fold');
    });

    // Check if only 1 meaningful service remains AND it contains "Dryer"
    if (meaningfulServices.length === 1) {
        const serviceName = (meaningfulServices[0].getAttribute('data-name') || meaningfulServices[0].text).toLowerCase();
        return serviceName.includes('dryer');
    }
    return false;
}

// Helper function to update detergent section visibility based on service selection
function updateDetergentSectionState() {
    const isDryerOnly = isDryerOnlySelected();
    const laundryItemSelect = document.getElementById('laundry-item-select');
    const laundryLabel = document.querySelector('h4');  // The "Supplies" label
    const detergentOptgroup = laundryItemSelect ? laundryItemSelect.querySelector('optgroup[label="Detergents"]') : null;
    const fabricConditionerOptgroup = laundryItemSelect ? laundryItemSelect.querySelector('optgroup[label="Fabric Conditioners"]') : null;

    if (isDryerOnly) {
        // Only disable detergent options, keep fabric conditioner available
        if (detergentOptgroup) {
            // Disable all detergent options
            const detergentOptions = detergentOptgroup.querySelectorAll('option');
            detergentOptions.forEach(option => {
                option.disabled = true;
            });
            detergentOptgroup.style.opacity = '0.5';
        }

        // Enable fabric conditioner options
        if (fabricConditionerOptgroup) {
            const fabricOptions = fabricConditionerOptgroup.querySelectorAll('option');
            fabricOptions.forEach(option => {
                option.disabled = false;
            });
            fabricConditionerOptgroup.style.opacity = '1';
        }

        // Update label to show it's optional
        if (laundryLabel && laundryLabel.textContent === 'Supplies') {
            laundryLabel.innerHTML = 'Supplies <small style="color: #999; font-weight: normal;">(Optional - Fabric Conditioner Only)</small>';
        }

        // Keep the select enabled so user can select fabric conditioner
        if (laundryItemSelect) {
            laundryItemSelect.disabled = false;
        }

        console.log("Self-Service - Dryer detected: Detergent disabled, Fabric Conditioner available");
    } else {
        // Restore normal state - enable all options
        if (detergentOptgroup) {
            const detergentOptions = detergentOptgroup.querySelectorAll('option');
            detergentOptions.forEach(option => {
                option.disabled = false;
            });
            detergentOptgroup.style.opacity = '1';
        }

        if (fabricConditionerOptgroup) {
            const fabricOptions = fabricConditionerOptgroup.querySelectorAll('option');
            fabricOptions.forEach(option => {
                option.disabled = false;
            });
            fabricConditionerOptgroup.style.opacity = '1';
        }

        // Restore label
        if (laundryLabel && laundryLabel.innerHTML.includes('Optional')) {
            laundryLabel.textContent = 'Supplies';
        }

        if (laundryItemSelect) {
            laundryItemSelect.disabled = false;
        }

        console.log("Non-Dryer service detected: All supply options restored");
    }
}

// Calendar logic
document.addEventListener("DOMContentLoaded", function () {
    const monthYearElement = document.getElementById("current-month-year");
    const prevMonthButton = document.getElementById("prev-month");
    const nextMonthButton = document.getElementById("next-month");
    const calendarBody = document.getElementById("calendar-body");

    let currentDate = new Date();
    let bookedSlotsCache = {}; // Cache for booked slots data
    let totalMachinesCache = {
        washers: 6,
        dryers: 6
    }; // Will be updated from API

    // Clear the cache for a specific date
    function clearCacheForDate(date) {
        delete bookedSlotsCache[date];
    }

    // Fetch booked days for the current month view
    async function fetchBookedDays(year, month) {
        const monthString = `${year}-${(month + 1).toString().padStart(2, "0")}`;
        try {
            const response = await fetch(`book-now.php?action=get_booked_days&month=${monthString}`);
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Error fetching booked days:', error);
            return [];
        }
    }

    // Fetch booked time slots for a specific date
    async function fetchBookedTimeSlots(date) {
        // Check cache first
        if (bookedSlotsCache[date]) {
            return bookedSlotsCache[date];
        }

        try {
            const response = await fetch(`book-now.php?action=get_booked_slots&date=${date}`);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            // Cache the result
            bookedSlotsCache[date] = Array.isArray(data) ? data : [];
            return bookedSlotsCache[date];
        } catch (error) {
            console.error('Error fetching booked slots:', error);
            return [];
        }
    }

    // Fetch holidays for the current month view
    async function fetchHolidays(year, month) {
        const monthString = `${year}-${(month + 1).toString().padStart(2, "0")}`;
        try {
            const response = await fetch(`book-now.php?action=get_holidays&month=${monthString}`);
            if (!response.ok) throw new Error('Network response was not ok');
            return await response.json();
        } catch (error) {
            console.error('Error fetching holidays:', error);
            return {};
        }
    }

    // Update time slots with real availability data
    async function updateTimeSlots(selectedDate, preserveSelection = false) {
        const timeSlotElements = document.querySelectorAll(".time-slot");
        try {
            // Remember current pick; only clear it on a foreground (user) refresh
            const prevSlot = document.getElementById("selected_time_slot").value;
            if (!preserveSelection) {
                document.getElementById("selected_time_slot").value = "";
            }

            // Fetch availability data from the server using relative path
            const response = await fetch(`?action=get_availability&date=${selectedDate}`);

            if (!response.ok) {
                console.error(`HTTP error! status: ${response.status}`);
                const text = await response.text();
                console.error("Response text:", text);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const availabilityData = await response.json();
            console.log("Availability data for", selectedDate, ":", availabilityData); // Debug log

            // Check if we got an error response
            if (availabilityData.error) {
                console.error("API Error:", availabilityData.error);
                throw new Error("API Error: " + availabilityData.error);
            }

            // Check if we got valid data
            if (!availabilityData || Object.keys(availabilityData).length === 0) {
                console.warn("Received empty availability data, using defaults");
                // Use default values if no data
                timeSlotElements.forEach(slot => {
                    const slotName = slot.getAttribute("data-slot");
                    updateSlotDisplay(slot, slotName, {
                        washers: { available: 6, booked: 0, total: 6, full: false },
                        dryers: { available: 6, booked: 0, total: 6, full: false },
                        total: { available: 12, booked: 0, total: 12, full: false }
                    });
                });
                return;
            }

            // Update each time slot with its availability
            timeSlotElements.forEach(slot => {
                const slotName = slot.getAttribute("data-slot");
                const slotData = availabilityData[slotName];

                if (slotData) {
                    const availableWashers = slotData.washers?.available ?? 0;
                    const availableDryers = slotData.dryers?.available ?? 0;
                    const totalWashers = slotData.washers?.total ?? 6;
                    const totalDryers = slotData.dryers?.total ?? 6;
                    const washerMachines = slotData.washers?.machines ?? [];
                    const dryerMachines = slotData.dryers?.machines ?? [];
                    const fullyBooked = (availableWashers <= 0 && availableDryers <= 0);

                    // --- Summary text ---
                    const summaryEl = slot.querySelector(".machine-available");
                    if (summaryEl) {
                        summaryEl.textContent = fullyBooked
                            ? "Fully Booked"
                            : `${availableWashers} Washer${availableWashers !== 1 ? 's' : ''} • ${availableDryers} Dryer${availableDryers !== 1 ? 's' : ''} free`;
                    }

                    // --- Machine chips ---
                    const chipsEl = slot.querySelector(".machine-chips");
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

                    // --- Status classes ---
                    slot.classList.remove("booked-slot", "fully-booked", "selected");
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

                    // Store available machine names so selectTimeSlot() can filter the dropdown
                    slot.dataset.availableWashers = availableWashers;
                    slot.dataset.availableDryers = availableDryers;
                    slot.dataset.totalWashers = totalWashers;
                    slot.dataset.totalDryers = totalDryers;
                    slot.dataset.washerMachines = JSON.stringify(washerMachines);
                    slot.dataset.dryerMachines = JSON.stringify(dryerMachines);
                } else {
                    console.warn(`No data found for slot: ${slotName}`);
                    updateSlotDisplay(slot, slotName, {
                        washers: { available: 6, booked: 0, total: 6, full: false, machines: [] },
                        dryers: { available: 6, booked: 0, total: 6, full: false, machines: [] },
                        total: { available: 12, booked: 0, total: 12, full: false }
                    });
                }
            });

            // Background refresh: keep the user's chosen slot highlighted (or warn if it just filled)
            if (preserveSelection && prevSlot) {
                const chosen = Array.from(timeSlotElements).find(s => s.getAttribute('data-slot') === prevSlot);
                if (chosen) {
                    if (chosen.classList.contains('fully-booked')) {
                        document.getElementById("selected_time_slot").value = "";
                        Swal.fire({ icon: 'warning', title: 'Slot just filled up',
                            text: `"${prevSlot}" was just fully booked. Please pick another time slot.`, timer: 4000 });
                    } else {
                        chosen.classList.add('selected');
                    }
                }
            }

        } catch (error) {
            console.error("Error fetching availability:", error);

            // Show error message to user
            Swal.fire({
                title: "Error Loading Availability",
                text: "Could not load time slot availability. Please try again.",
                icon: "error",
                timer: 3000
            });

            // Fallback: Make all slots available but show error state
            timeSlotElements.forEach(slot => {
                slot.classList.remove("booked-slot", "fully-booked", "selected");
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

    // Keep slot availability live while a date is selected (reflects walk-ins / other bookings)
    setInterval(function () {
        const dateEl = document.getElementById('booking_date');
        const d = dateEl ? dateEl.value : '';
        if (d) updateTimeSlots(d, true);
    }, 15000);

    // Helper function to update slot display
    function updateSlotDisplay(slot, slotName, slotData) {
        const availableWashers = slotData.washers?.available ?? 6;
        const availableDryers = slotData.dryers?.available ?? 6;
        const totalWashers = slotData.washers?.total ?? 6;
        const totalDryers = slotData.dryers?.total ?? 6;
        const washerMachines = slotData.washers?.machines ?? [];
        const dryerMachines = slotData.dryers?.machines ?? [];

        // Update summary text
        const machineAvailableElement = slot.querySelector(".machine-available");
        if (machineAvailableElement) {
            if (availableWashers <= 0 && availableDryers <= 0) {
                machineAvailableElement.textContent = "Fully Booked";
            } else {
                machineAvailableElement.textContent =
                    `${availableWashers} Washer${availableWashers !== 1 ? 's' : ''} • ${availableDryers} Dryer${availableDryers !== 1 ? 's' : ''} free`;
            }
        }

        // Update chips
        const chipsEl = slot.querySelector(".machine-chips");
        if (chipsEl) {
            if (availableWashers <= 0 && availableDryers <= 0) {
                chipsEl.innerHTML = '';
            } else {
                let html = '';
                washerMachines.forEach(m => { html += `<span class="mchip mchip-washer"><i class="fas fa-washing-machine"></i>${m}</span>`; });
                dryerMachines.forEach(m => { html += `<span class="mchip mchip-dryer"><i class="fas fa-wind"></i>${m}</span>`; });
                chipsEl.innerHTML = html;
            }
        }

        // Update slot status
        slot.classList.remove("booked-slot", "fully-booked", "selected");
        slot.style.pointerEvents = "auto";
        slot.style.opacity = "1";

        // Check if both washer and dryer types are fully booked
        if (availableWashers <= 0 && availableDryers <= 0) {
            slot.classList.add("fully-booked");
            slot.title = "This slot is fully booked";
            slot.style.pointerEvents = "none";
            slot.style.opacity = "0.6";
        } else if (availableWashers < totalWashers || availableDryers < totalDryers) {
            slot.classList.add("booked-slot");
            slot.title = `Some machines already booked — select from available ones`;
        } else {
            slot.title = "Click to select this time slot";
        }

        // Store availability data in data attributes for later use
        slot.dataset.availableWashers = availableWashers;
        slot.dataset.availableDryers = availableDryers;
        slot.dataset.totalWashers = totalWashers;
        slot.dataset.totalDryers = totalDryers;
    }

    async function updateCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"];

        monthYearElement.textContent = `${monthNames[month]} ${year}`;
        const firstDay = new Date(year, month, 1).getDay();
        const totalDays = new Date(year, month + 1, 0).getDate();

        // Fetch booked days for this month
        const bookedDays = await fetchBookedDays(year, month);
        // Fetch holidays for this month
        const holidays = await fetchHolidays(year, month);

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
                    const isToday = isCurrentDay(year, month, day);
                    const holidayName = holidays[dateString] || null;

                    let className = "calendar-day";
                    if (isBooked) className += " booked-day";
                    if (isToday) className += " today";
                    if (holidayName) className += " holiday-day";

                    let title = "";
                    if (holidayName) title = `Holiday: ${holidayName}`;
                    if (isBooked) title += (title ? " | " : "") + "This day has booked slots";

                    calendarHTML += `
                        <td class="${className}" data-date="${dateString}" data-holiday="${holidayName || ''}" title="${title}">
                            ${day}
                            <div class="indicator-container">
                                ${isBooked ? '<div class="booking-indicator"></div>' : ''}
                                ${holidayName ? '<div class="holiday-indicator"></div>' : ''}
                            </div>
                        </td>`;
                    day++;
                }
            }

            calendarHTML += "</tr>";
        }

        calendarBody.innerHTML = calendarHTML;

        // Attach click events
        document.querySelectorAll(".calendar-day").forEach(day => {
            day.addEventListener("click", async function () {
                document.querySelectorAll(".calendar-day").forEach(d => d.classList.remove("selected"));
                this.classList.add("selected");

                const selectedDate = this.getAttribute("data-date");
                document.getElementById("booking_date").value = selectedDate;

                await updateTimeSlots(selectedDate);

                document.querySelectorAll(".time-slot").forEach(slot => {
                    slot.classList.remove("selected");
                });
                document.getElementById("selected_time_slot").value = "";
            });
        });

        // Highlight today's date by default if no date is selected
        const bookingDateInput = document.getElementById("booking_date");
        if (!bookingDateInput.value) {
            const today = new Date();
            const todayString = `${today.getFullYear()}-${(today.getMonth() + 1).toString().padStart(2, "0")}-${today.getDate().toString().padStart(2, "0")}`;
            const todayCell = document.querySelector(`.calendar-day[data-date='${todayString}']`);
            if (todayCell) {
                todayCell.click();
            }
        } else {
            const preSelected = document.querySelector(`.calendar-day[data-date='${bookingDateInput.value}']`);
            if (preSelected) {
                preSelected.classList.add("selected");
                updateTimeSlots(bookingDateInput.value);
            }
        }
    }

    function isCurrentDay(year, month, day) {
        const today = new Date();
        return today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day;
    }

    prevMonthButton.addEventListener("click", function () {
        currentDate.setMonth(currentDate.getMonth() - 1);
        updateCalendar();
    });

    nextMonthButton.addEventListener("click", function () {
        currentDate.setMonth(currentDate.getMonth() + 1);
        updateCalendar();
    });

    // Initialize the calendar
    updateCalendar();
});

// Time slot selection with machine count validation
function selectTimeSlot(slot) {
    // Don't allow selection if slot is fully booked
    if (slot.classList.contains("fully-booked")) {
        Swal.fire({
            title: "Fully Booked",
            text: "All machines are taken for this slot. Please choose another time.",
            icon: "warning",
            timer: 2000,
            confirmButtonColor: "#6366f1"
        });
        return;
    }

    const availableWashers = parseInt(slot.dataset.availableWashers) || 0;
    const availableDryers = parseInt(slot.dataset.availableDryers) || 0;
    const washerMachines = JSON.parse(slot.dataset.washerMachines || '[]');
    const dryerMachines = JSON.parse(slot.dataset.dryerMachines || '[]');
    const freeMachineNames = [...washerMachines, ...dryerMachines];
    const machineCountInput = document.getElementById("machine-count");
    const maxAvailable = availableWashers + availableDryers;

    if (maxAvailable <= 0) {
        Swal.fire({
            title: "No Machines Available",
            text: "No machines are available in this slot.",
            icon: "warning",
            confirmButtonColor: "#6366f1"
        });
        return;
    }

    // ── Filter the machine dropdown to only free machines ───────────────
    const machineSelect = document.getElementById('machine-type');
    if (machineSelect) {
        // Clear current selection first
        $(machineSelect).val(null).trigger('change');

        // Enable/disable options based on availability for this slot
        $(machineSelect).find('option').each(function () {
            const name = $(this).val();
            if (!name) return; // skip placeholder
            const isFree = freeMachineNames.includes(name);
            $(this).prop('disabled', !isFree);
            if (!isFree) $(this).prop('selected', false);
        });

        // Refresh Select2 so it shows the updated disabled states
        $(machineSelect).trigger('change');

        // Update max machines count to match slot availability
        machineCountInput.max = maxAvailable;
        machineCountInput.title = `Max ${maxAvailable} machine(s) free in this slot`;
        if (parseInt(machineCountInput.value) > maxAvailable) {
            machineCountInput.value = maxAvailable;
        }
    }

    // ── Visual: mark selected slot ───────────────────────────────────────
    document.querySelectorAll(".time-slot").forEach(el => {
        el.classList.remove("selected");
        el.style.backgroundColor = "";
        el.style.color = "";
    });
    slot.classList.add("selected");

    // Set hidden input
    document.getElementById("selected_time_slot").value = slot.getAttribute("data-slot");

    // Show a subtle toast if this is a partially-booked slot
    if (slot.classList.contains("booked-slot") || availableWashers + availableDryers < (parseInt(slot.dataset.totalWashers) || 0) + (parseInt(slot.dataset.totalDryers) || 0)) {
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 2500,
            icon: 'info',
            title: `${maxAvailable} machine(s) available for this slot`,
            text: 'Only the highlighted machines below can be selected.'
        });
    }
}

// Helper function to get available machines for a slot
function getAvailableMachinesForSlot(slot) {
    if (!slot || !slot.dataset) {
        console.error('Invalid slot element:', slot);
        return 12; // Return default value
    }

    // Get selected machine types
    const selectedMachines = Array.from(document.querySelectorAll('#machine-type option:checked')).map(opt => opt.value);

    const availableWashers = parseInt(slot.dataset.availableWashers) || 6;
    const availableDryers = parseInt(slot.dataset.availableDryers) || 6;

    // Check selected options for machine types
    const hasWasher = selectedMachines.some(machine =>
        machine.toLowerCase().includes('washer') ||
        document.querySelector(`#machine-type option[value="${machine}"]`)?.dataset.machineType === 'washer'
    );
    const hasDryer = selectedMachines.some(machine =>
        machine.toLowerCase().includes('dryer') ||
        document.querySelector(`#machine-type option[value="${machine}"]`)?.dataset.machineType === 'dryer'
    );

    // Return appropriate availability based on selected machine types
    if (hasWasher && hasDryer) {
        return Math.min(availableWashers, availableDryers);
    } else if (hasWasher) {
        return availableWashers;
    } else if (hasDryer) {
        return availableDryers;
    }

    return availableWashers + availableDryers; // Default to total
}

// Add event listener for machine count input
document.addEventListener("DOMContentLoaded", function () {
    const machineCountInput = document.getElementById("machine-count");
    if (machineCountInput) {
        machineCountInput.addEventListener("input", function () {
            const selectedSlotValue = document.getElementById("selected_time_slot").value;
            if (selectedSlotValue) {
                // Find the actual slot element
                const slotElement = document.querySelector(`.time-slot[data-slot="${selectedSlotValue}"]`);
                if (slotElement) {
                    const maxAvailable = getAvailableMachinesForSlot(slotElement);
                    if (this.value > maxAvailable) {
                        this.value = maxAvailable;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: "Adjusted",
                                text: `This slot only has ${maxAvailable} machines available for your selected type.`,
                                icon: "info"
                            });
                        }
                    }
                }
            }
        });
    }

    // Add event listener for machine type changes to update max
    const machineTypeSelect = document.getElementById('machine-type');
    if (machineTypeSelect) {
        machineTypeSelect.addEventListener('change', function () {
            const selectedSlotValue = document.getElementById("selected_time_slot").value;
            if (selectedSlotValue) {
                const slotElement = document.querySelector(`.time-slot[data-slot="${selectedSlotValue}"]`);
                if (slotElement) {
                    const maxAvailable = getAvailableMachinesForSlot(slotElement);
                    machineCountInput.max = maxAvailable;
                    if (parseInt(machineCountInput.value) > maxAvailable) {
                        machineCountInput.value = maxAvailable;
                    }
                }
            }
        });
    }
});

// Add event listener for service type changes to update detergent section state
document.addEventListener('DOMContentLoaded', function () {
    const serviceTypeSelect = document.getElementById('service-type');

    if (serviceTypeSelect) {
        // Listen for changes on the service select
        serviceTypeSelect.addEventListener('change', function () {
            updateDetergentSectionState();
        });

        // Also handle Select2 change events if using Select2
        $(serviceTypeSelect).on('change', function () {
            updateDetergentSectionState();
        });

        // Add listener for fold service checkbox changes
        const foldCheckboxes = document.querySelectorAll('input[type="checkbox"][id*="fold"], input[type="checkbox"][data-fold]');
        foldCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                updateDetergentSectionState();
            });
        });

        // Initial state check on page load
        updateDetergentSectionState();
    }
});

// booking form submission
document.addEventListener('DOMContentLoaded', function () {
    const bookingForm = document.getElementById("booking-form");

    if (!bookingForm) {
        console.error("Booking form not found - check your form ID");
        return;
    }

    // MAKE THIS FUNCTION ASYNC
    bookingForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        console.log("Form submission triggered");

        // Get form elements
        const selectElement = document.getElementById('service-type');
        if (!selectElement) {
            console.error("Service type select element not found");
            return;
        }

        // Get selected services
        const selectedOptions = Array.from(selectElement.selectedOptions);
        const selectedServices = selectedOptions.map(option => ({
            id: option.value,
            name: option.getAttribute('data-name') || option.text.trim(),
            price: parseFloat(option.getAttribute('data-price')) || 0
        }));

        // Get laundry items from the new quantity-based selector (stored in global array populated by addLaundryItem())
        // selectedLaundryItems is populated in book-now.php and contains {name, qty, timestamp} objects
        const selectedLaundrySupplies = typeof window.selectedLaundryItems !== 'undefined' && window.selectedLaundryItems.length > 0
            ? window.selectedLaundryItems.map(item => ({
                name: item.name,
                qty: item.qty,
                id: item.name
            }))
            : [];

        // Get selected Machines
        const machineSelect = document.getElementById('machine-type');
        const selectedMachines = Array.from(machineSelect.selectedOptions).map(option => ({
            id: option.getAttribute('data-machineId'),
            name: option.value,
            type: option.getAttribute('data-machineType')
        }));

        const servicesJsonField = document.getElementById("selected_services_json");
        if (servicesJsonField) {
            servicesJsonField.value = JSON.stringify(selectedServices);
        }

        const laundrySuppliesJsonField = document.getElementById("selected_laundry_supplies_json");
        if (laundrySuppliesJsonField) {
            laundrySuppliesJsonField.value = JSON.stringify(selectedLaundrySupplies);
        }

        const machinesJsonField = document.getElementById("selected_machines_json");
        if (machinesJsonField) {
            machinesJsonField.value = JSON.stringify(selectedMachines);
        }

        // Get other form values
        const bookingDate = document.getElementById("booking_date").value;
        const timeSlot = document.getElementById("selected_time_slot").value;
        const machineCount = document.getElementById("machine-count").value;
        const selectedRequestServices = Array.from(
            document.querySelectorAll('input[name="request_service[]"]:checked')
        ).map(input => input.value);

        // Validation
        if (!bookingDate) {
            Swal.fire("Error", "Please select a booking date.", "error");
            return;
        }

        if (!timeSlot) {
            Swal.fire("Error", "Please select a time slot.", "error");
            return;
        }

        if (!machineCount || machineCount <= 0) {
            Swal.fire("Error", "Please enter a valid machine count.", "error");
            return;
        }

        if (selectedServices.length === 0) {
            Swal.fire("Error", "Please select at least one service.", "error");
            return;
        }

        // For Dryer-only bookings, laundry supplies are optional
        // For other services, at least one laundry supply is required
        const isDryerOnly = isDryerOnlySelected();
        if (!isDryerOnly && selectedLaundrySupplies.length === 0) {
            Swal.fire("Error", "Please select at least one laundry supply.", "error");
            return;
        }

        if (isDryerOnly) {
            console.log("Dryer-only booking detected: Skipping laundry supply requirement");
        }

        if (selectedMachines.length === 0) {
            Swal.fire("Error", "Please select at least one machine.", "error");
            return;
        }

        // After validation but before confirmation dialog, add inventory check
        const selectedSupplyNames = selectedLaundrySupplies.map(d => d.name);

        // Check if any supplies are "Bring my own detergent" - these don't need inventory check
        const suppliesToCheck = selectedSupplyNames.filter(name =>
            name !== 'Bring my own detergent' &&
            name !== 'Bring my own' &&
            name !== ''
        );

        if (suppliesToCheck.length > 0) {
            // Show checking message with loading
            Swal.fire({
                title: 'Checking Availability...',
                text: 'Verifying laundry supplies stock',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const inventoryCheck = await checkInventoryAvailability(suppliesToCheck);
                Swal.close();

                // Check if inventoryCheck has the expected structure
                if (inventoryCheck && inventoryCheck.success === false) {
                    // Only show error if there are actually unavailable items
                    if (inventoryCheck.unavailableItems && inventoryCheck.unavailableItems.length > 0) {
                        Swal.fire({
                            title: "Supplies Unavailable",
                            html: `The following items are out of stock:<br>
                           <b>${inventoryCheck.unavailableItems.join('<br>')}</b><br><br>
                           Please remove them from your selection.`,
                            icon: "warning",
                            confirmButtonText: "OK"
                        });
                        return;
                    }
                }
                // If success is true or there are no unavailable items, continue
            } catch (error) {
                console.error('Inventory check failed:', error);
                Swal.close();
                await Swal.fire({
                    title: "Warning",
                    text: "Unable to verify inventory. You can still proceed with booking.",
                    icon: "warning",
                    confirmButtonText: "Continue"
                });
            }
        }
        // Get all available services for Fold option
        const allServices = Array.from(document.querySelectorAll('#service-type option'));
        const foldService = allServices.find(opt => opt.text.includes('Fold'));
        const foldServicePrice = foldService ? parseFloat(foldService.getAttribute('data-price')) : 0;

        // Calculate current booking total for display
        // Sum all services (now that we're extracting data-price)
        let currentBookingTotal = selectedServices.reduce((sum, s) => sum + (s.price || 0), 0);

        // Add laundry supplies
        selectedLaundrySupplies.forEach(item => {
            // Get price from the laundry item selector options (they have data-price)
            const supplyOption = Array.from(document.querySelectorAll('#laundry-item-select option')).find(
                opt => opt.value === item.name
            );
            const pricePerUnit = supplyOption ? parseFloat(supplyOption.getAttribute('data-price')) || 0 : 0;
            currentBookingTotal += pricePerUnit * item.qty;
        });

        const finalTotalWithFold = currentBookingTotal + foldServicePrice;

        // Check if fold service was already added in step 2
        const hasFoldAlready = selectedServices.find(s => s.name && s.name.includes('Fold'));

        // Create checkbox HTML for Fold service only if fold not already selected
        const foldCheckboxHTML = (foldService && !hasFoldAlready) ? `
            <div style="text-align: left; margin: 15px 0; padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 4px solid #f5a623;">
                <label style="display: flex; align-items: flex-start; cursor: pointer; gap: 10px;">
                    <input type="checkbox" id="confirmModalFoldCheckbox" style="margin-top: 4px; width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <b style="color: #333;">Add Fold Service (Optional)</b>
                        <div style="font-size: 0.9em; color: #666; margin-top: 4px;">We neatly fold your clean and dry clothes for you</div>
                        <div style="font-size: 0.95em; color: #f5a623; font-weight: bold; margin-top: 6px;">+ ₱${foldServicePrice.toFixed(2)}</div>
                    </div>
                </label>
            </div>
        ` : '';

        // Confirmation dialog
        Swal.fire({
            title: "Confirm Booking",
            html: `You are booking:<br>
                  <b>Date:</b> ${bookingDate}<br>
                  <b>Time:</b> ${timeSlot}<br>
                  <b>Services:</b> ${selectedServices.map(s => s.name).join(', ')}<br>
                 <b>Laundry Supplies:</b> ${selectedLaundrySupplies.map(d => `${d.qty}x ${d.name}`).join(', ')}<br>
                  <b>Machines:</b> ${selectedMachines.map(d => d.name).join(', ')}<br>
                  <b>Machine Count:</b> ${machineCount}<br>
                  <b>Request:</b> ${selectedRequestServices.join(', ') || 'None'}
                  <br><br>
                  <div style="background: #f0f0f0; padding: 12px; border-radius: 6px; margin: 15px 0; text-align: left;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><b>Booking Total:</b></span>
                        <span><b>₱${currentBookingTotal.toFixed(2)}</b></span>
                    </div>
                    <div id="foldTotalRow" style="display: none; padding-top: 8px; border-top: 1px solid #ddd; padding-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; color: #f5a623;">
                            <span>+ Fold Service:</span>
                            <span>₱${foldServicePrice.toFixed(2)}</span>
                        </div>
                    </div>
                    <div id="finalTotalRow" style="display: none; padding-top: 8px; border-top: 2px solid #6f42c1; font-size: 1.1em;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><b>Final Total:</b></span>
                            <span style="color: #6f42c1;"><b>₱${finalTotalWithFold.toFixed(2)}</b></span>
                        </div>
                    </div>
                  </div>
                  ${foldCheckboxHTML}`,
            icon: "info",
            showCancelButton: true,
            confirmButtonText: "Yes, Book it!",
            cancelButtonText: "Cancel",
            didOpen: () => {
                // Add event listener to checkbox
                const foldCheckbox = document.getElementById('confirmModalFoldCheckbox');
                if (foldCheckbox) {
                    foldCheckbox.addEventListener('change', function () {
                        const foldTotalRow = document.getElementById('foldTotalRow');
                        const finalTotalRow = document.getElementById('finalTotalRow');
                        if (this.checked) {
                            foldTotalRow.style.display = 'block';
                            finalTotalRow.style.display = 'block';
                        } else {
                            foldTotalRow.style.display = 'none';
                            finalTotalRow.style.display = 'none';
                        }
                    });
                }
            }
        }).then((result) => {
            if (result.isDismissed) {
                console.log("User cancelled booking confirmation - modal closed");
                // Modal automatically closes, user can edit form and resubmit
            } else if (result.isConfirmed) {
                console.log("User confirmed booking, preparing JSON data...");

                // Check if user selected Fold service
                const foldCheckbox = document.getElementById('confirmModalFoldCheckbox');
                const addFoldService = foldCheckbox ? foldCheckbox.checked : false;

                console.log("Add Fold Service checked:", addFoldService);

                // If Fold is selected and not already in services, add it
                // Re-lookup foldService here to ensure proper scope
                if (addFoldService) {
                    const allServicesAgain = Array.from(document.querySelectorAll('#service-type option'));
                    const foldServiceOption = allServicesAgain.find(opt => opt.text.includes('Fold'));

                    if (foldServiceOption && !selectedServices.find(s => s.name && s.name.includes('Fold'))) {
                        const foldServiceId = foldServiceOption.value;  // This is the service name from the option value
                        const foldPrice = parseFloat(foldServiceOption.getAttribute('data-price')) || 0;

                        console.log("Adding Fold service - ID:", foldServiceId, "Price:", foldPrice);

                        selectedServices.push({
                            id: foldServiceId,
                            name: foldServiceOption.getAttribute('data-name') || foldServiceOption.text.split(' — ')[0].trim(),
                            price: foldPrice
                        });
                    } else if (!foldServiceOption) {
                        console.warn("Fold service option not found in select element");
                    } else {
                        console.log("Fold service already in selectedServices or not selected");
                    }
                }

                // Prepare JSON data structure - FIXED FIELD NAMES TO MATCH DATABASE
                const bookingData = {
                    booking_date: bookingDate,
                    time_slot: timeSlot,
                    machine_count: parseInt(machineCount),
                    service_type: selectedServices.map(s => s.id),      // Maps to service_type in DB
                    detergent: selectedLaundrySupplies, // Now includes {name, qty} objects for calculation in booking_process.php
                    machine: selectedMachines.map(d => d.name),          // Maps to machine_names in DB
                    request_service: selectedRequestServices             // Maps to request_service in DB
                };

                console.log("Sending booking data:", bookingData);
                console.log("Service type array:", bookingData.service_type);
                console.log("Services being sent:", bookingData.service_type.join(', '));

                // Show loading state
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we process your booking',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Make the fetch request
                fetch('booking_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(bookingData)
                })
                    .then(response => {
                        console.log("Response status:", response.status, response.statusText);

                        // First get the raw text to see what we're getting
                        return response.text().then(text => {
                            console.log("=== FULL RESPONSE START ===");
                            console.log(text);
                            console.log("=== FULL RESPONSE END ===");

                            // Check if we got an error
                            if (text.trim() === '') {
                                throw new Error('Empty response from server');
                            }

                            // Check if it's HTML (error page)
                            if (text.includes('<!DOCTYPE') || text.includes('<html') || text.includes('PHP Error') || text.includes('Parse error')) {
                                console.error("Server returned HTML/Error page instead of JSON");

                                // Try to extract error message from HTML
                                const errorMatch = text.match(/<pre[^>]*>([\s\S]*?)<\/pre>/) ||
                                    text.match(/<b>([^<]+)<\/b>/) ||
                                    text.match(/Fatal error[^<]+/) ||
                                    text.match(/Parse error[^<]+/);

                                let errorMsg = 'Server returned an error page. ';
                                if (errorMatch) {
                                    errorMsg += 'Error: ' + errorMatch[0].substring(0, 200);
                                }

                                throw new Error(errorMsg);
                            }

                            // Try to parse as JSON
                            try {
                                const data = JSON.parse(text);
                                console.log("Successfully parsed JSON:", data);
                                return data;
                            } catch (e) {
                                console.error("Failed to parse JSON:", e.message);
                                throw new Error('Invalid JSON response: ' + e.message);
                            }
                        });
                    })
                    .then(data => {
                        console.log("Processed response data:", data);

                        // Close loading dialog
                        Swal.close();

                        if (data.success || data.status === 'success') {
                            // Update notifications if function exists
                            if (typeof updateNotificationBadge === 'function') {
                                updateNotificationBadge();
                            }

                            Swal.fire({
                                title: "Success!",
                                html: `<div style="text-align: left;">
                                  <p><strong>Booking Confirmed!</strong></p>
                                  <p>Booking ID: #${data.booking_id || 'N/A'}</p>
                                  <p>Queue Number: ${data.queue_code || (data.queue_number ? ('Q-' + String(data.queue_number).padStart(3, '0')) : 'N/A')}</p>
                                  <p>Stage: ${data.order_stage || 'Pending / Booked'}</p>
                                  <p>${data.message || 'Your booking has been successfully created.'}</p>
                                  <p><small>A notification has been added to your account.</small></p>
                                  </div>`,
                                icon: "success",
                                confirmButtonText: "View Booking",
                                showCancelButton: true,
                                cancelButtonText: "Stay Here"
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    if (data.redirect) {
                                        window.location.href = data.redirect;
                                    } else {
                                        window.location.href = 'booking_confirmation.php?id=' + (data.booking_id || '');
                                    }
                                } else {
                                    // Refresh the page to show updated notifications
                                    window.location.reload();
                                }
                            });
                        } else {
                            Swal.fire({
                                title: "Booking Failed",
                                text: data.error || data.message || "An unknown error occurred",
                                icon: "error"
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);

                        // Close loading dialog
                        Swal.close();

                        let errorMessage = error.message;

                        // Friendly error messages
                        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                            errorMessage = 'Network error. Please check your connection and try again.';
                        } else if (error.message.includes('404')) {
                            errorMessage = 'Booking processor not found. Please contact support.';
                        } else if (error.message.includes('Empty response')) {
                            errorMessage = 'Server returned empty response. Please try again.';
                        }

                        Swal.fire({
                            title: "Booking Error",
                            html: `<div style="text-align: left;">
                              <p><strong>${errorMessage}</strong></p>
                              <p><small>Technical details: ${error.message.substring(0, 100)}</small></p>
                              <p><small>Check browser console (F12) for more details.</small></p>
                              </div>`,
                            icon: "error",
                            confirmButtonText: "OK"
                        });
                    });
            }
        });
    });
});