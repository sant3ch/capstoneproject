/**
 * In-Store Payment Handler
 */

function initInStorePayment() {
    console.log("Initializing in-store payment handler");

    const submitBtn = document.getElementById("submitInStorePaymentBtn");
    const inStoreModal = document.getElementById("inStoreModal");
    const inStoreSuccessModal = document.getElementById("inStoreSuccessModal");

    console.log("submitBtn found:", !!submitBtn);
    console.log("inStoreModal found:", !!inStoreModal);
    console.log("inStoreSuccessModal found:", !!inStoreSuccessModal);

    if (!submitBtn) {
        console.warn("Submit button not found, retrying in 500ms");
        setTimeout(initInStorePayment, 500);
        return;
    }

    // Attach the main submit handler
    submitBtn.addEventListener("click", handleInStoreSubmit);
    console.log("In-store payment handler initialized successfully");
}

// Handler function for in-store submit
function handleInStoreSubmit(e) {
    e.preventDefault();
    console.log("In-store submit button clicked");

    // Get total amount from finalAmount field (includes voucher deductions)
    const finalAmountField = document.getElementById("finalAmount");
    let totalAmount = finalAmountField ? parseFloat(finalAmountField.value) : 0;
    console.log("Total amount from finalAmount field:", totalAmount);

    // Fallback to breakdown calculation if finalAmount not found
    if (!totalAmount) {
        const breakdownItems = document.querySelectorAll("#inStoreBreakdownContainer .breakdown-item");
        console.log("Fallback: Breakdown items found:", breakdownItems.length);

        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector("span:last-child").textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ""));
        }
    }

    // Confirmation dialog before submitting in-store order
    Swal.fire({
        title: "Confirm In-Store Payment",
        html: "You are about to confirm an in-store booking.<br>" +
            "<strong>You will pay ₱" + totalAmount.toFixed(2) + " when you arrive.</strong><br>" +
            "Please bring your laundry at the scheduled time.",
        icon: "info",
        showCancelButton: true,
        confirmButtonText: "Yes, Confirm Booking",
        cancelButtonText: "Cancel",
        reverseButtons: true,
        confirmButtonColor: "#6f42c1",
        cancelButtonColor: "#6c757d",
        buttonsStyling: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didRender: function () {
            const confirmBtn = document.querySelector('.swal2-confirm');
            const cancelBtn = document.querySelector('.swal2-cancel');
            const actions = document.querySelector('.swal2-actions');

            if (actions) {
                actions.style.visibility = 'visible';
                actions.style.display = 'flex';
                actions.style.opacity = '1';
                actions.style.gap = '10px';
            }

            if (confirmBtn) {
                confirmBtn.style.visibility = 'visible';
                confirmBtn.style.display = 'block';
                confirmBtn.style.opacity = '1';
                confirmBtn.style.color = 'white';
                confirmBtn.style.fontWeight = '600';
                confirmBtn.style.fontSize = '14px';
                confirmBtn.style.padding = '10px 24px';
                confirmBtn.style.borderRadius = '6px';
                confirmBtn.style.border = 'none';
                confirmBtn.style.cursor = 'pointer';
                confirmBtn.style.backgroundColor = '#6f42c1';
            }

            if (cancelBtn) {
                cancelBtn.style.visibility = 'visible';
                cancelBtn.style.display = 'block';
                cancelBtn.style.opacity = '1';
                cancelBtn.style.color = 'white';
                cancelBtn.style.fontWeight = '600';
                cancelBtn.style.fontSize = '14px';
                cancelBtn.style.padding = '10px 24px';
                cancelBtn.style.borderRadius = '6px';
                cancelBtn.style.border = 'none';
                cancelBtn.style.cursor = 'pointer';
                cancelBtn.style.backgroundColor = '#6c757d';
            }
        }
    }).then((result) => {
        console.log("Confirmation result:", result.isConfirmed);
        if (result.isConfirmed) {
            submitInStorePayment();
        }
    });
}

// Submit function
function submitInStorePayment() {
    console.log("submitInStorePayment called");

    const submitBtn = document.getElementById("submitInStorePaymentBtn");
    const inStoreModal = document.getElementById("inStoreModal");
    const inStoreSuccessModal = document.getElementById("inStoreSuccessModal");

    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin me-2\"></i> Processing...";

    const idNumberElement = document.querySelector(".id-number");
    const bookingId = idNumberElement ? idNumberElement.textContent.replace("#", "").trim() : "";

    console.log("Booking ID:", bookingId);

    if (!bookingId) {
        Swal.fire({
            title: "Error",
            text: "Could not find booking ID. Please refresh the page.",
            icon: "error",
            confirmButtonText: "OK"
        });
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        return;
    }

    // Get total amount from finalAmount field (includes voucher deductions)
    const finalAmountField = document.getElementById("finalAmount");
    let totalAmount = finalAmountField ? parseFloat(finalAmountField.value) : 0;

    // Fallback to breakdown calculation if finalAmount not found
    if (!totalAmount) {
        const breakdownItems = document.querySelectorAll("#inStoreBreakdownContainer .breakdown-item");
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector("span:last-child").textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ""));
        }
    }

    const formData = new FormData();
    formData.append("booking_id", bookingId);
    formData.append("guest_token", window.BOOKING_TOKEN || "");
    formData.append("payment_method", "IN_STORE");
    formData.append("amount", totalAmount.toString());

    // Get reward information if applied
    const selectedRewardId = document.getElementById("selectedRewardId");
    const selectedRewardName = document.getElementById("selectedRewardName");

    if (selectedRewardId && selectedRewardId.value) {
        formData.append("reward_id", selectedRewardId.value);
    }
    if (selectedRewardName && selectedRewardName.value) {
        formData.append("reward_name", selectedRewardName.value);
    }

    // Calculate and send discount amount if reward is applied
    if (selectedRewardName && selectedRewardName.value) {
        const displayAmountDiv = document.getElementById("displayAmount");
        if (displayAmountDiv) {
            const displayText = displayAmountDiv.textContent || displayAmountDiv.innerText;
            const originalAmount = parseFloat(displayText.replace(/[^0-9.]/g, ""));
            const discountAmount = originalAmount - totalAmount;
            if (discountAmount > 0) {
                formData.append("discount_amount", discountAmount.toFixed(2));
                formData.append("original_amount", originalAmount.toFixed(2));
            }
        }
    }

    fetch("../assets/ajax/process_in_store_payment.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                const bsModal = bootstrap.Modal.getInstance(inStoreModal);
                if (bsModal) bsModal.hide();

                const successModal = new bootstrap.Modal(inStoreSuccessModal);
                successModal.show();

                document.getElementById("successInStoreBookingId").textContent = "#" + bookingId;
                document.getElementById("successInStoreAmount").textContent = "₱" + totalAmount.toFixed(2);
                document.getElementById("paymentAmountInInStoreModal").textContent = "₱" + totalAmount.toFixed(2);

                document.getElementById("inStoreSuccessOkBtn").addEventListener("click", function () {
                    const rewardName = document.getElementById("selectedRewardName").value;
                    let redirectUrl;
                    if (window.IS_GUEST) {
                        redirectUrl = "booking_confirmation.php?id=" + (window.BOOKING_ID || bookingId) + "&ref=" + encodeURIComponent(window.BOOKING_TOKEN || "");
                    } else {
                        redirectUrl = "user-profile.php";
                        if (rewardName) {
                            redirectUrl += "?reward_used=" + encodeURIComponent(rewardName);
                        }
                    }
                    window.location.href = redirectUrl;
                });
            } else {
                Swal.fire({
                    title: "Submission Failed",
                    text: data.message || "An error occurred while processing your in-store payment",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            }
        })
        .catch(error => {
            console.error("Error:", error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            Swal.fire({
                title: "Error",
                text: "An error occurred: " + error.message,
                icon: "error",
                confirmButtonText: "OK"
            });
        });
}

// Initialize on DOM ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initInStorePayment);
} else {
    initInStorePayment();
}
