/**
 * Cash on Delivery Payment Handler
 */

// Try to attach handler immediately, not just on DOMContentLoaded
function initCashPayment() {
    console.log("Initializing cash payment handler");

    const submitBtn = document.getElementById("submitCashPaymentBtn");
    const editAddressBtn = document.getElementById("editAddressBtn");
    const saveAddressBtn = document.getElementById("saveAddressBtn");
    const cancelAddressBtn = document.getElementById("cancelAddressBtn");
    const addressDisplay = document.getElementById("addressDisplay");
    const addressEditForm = document.getElementById("addressEditForm");
    const addressText = document.getElementById("addressText");
    const addressInput = document.getElementById("addressInput");
    const cashModal = document.getElementById("cashModal");
    const cashSuccessModal = document.getElementById("cashSuccessModal");

    console.log("submitBtn found:", !!submitBtn);
    console.log("cashModal found:", !!cashModal);
    console.log("cashSuccessModal found:", !!cashSuccessModal);

    if (!submitBtn) {
        console.warn("Submit button not found, retrying in 500ms");
        setTimeout(initCashPayment, 500);
        return;
    }

    if (editAddressBtn) {
        editAddressBtn.addEventListener("click", function (e) {
            e.preventDefault();
            addressDisplay.style.display = "none";
            addressEditForm.style.display = "block";
            addressInput.focus();
        });
    }

    if (cancelAddressBtn) {
        cancelAddressBtn.addEventListener("click", function (e) {
            e.preventDefault();
            addressEditForm.style.display = "none";
            addressDisplay.style.display = "block";
            addressInput.value = addressText.textContent;
        });
    }

    if (saveAddressBtn) {
        saveAddressBtn.addEventListener("click", function (e) {
            e.preventDefault();
            const newAddress = addressInput.value.trim();

            if (!newAddress) {
                Swal.fire({
                    title: "Address Required",
                    text: "Please enter a delivery address",
                    icon: "warning",
                    confirmButtonText: "OK"
                });
                return;
            }

            addressText.textContent = newAddress;
            addressEditForm.style.display = "none";
            addressDisplay.style.display = "block";

            Swal.fire({
                title: "Address Updated",
                text: "Delivery address has been updated",
                icon: "success",
                timer: 1500,
                showConfirmButton: false
            });
        });
    }

    // Attach the main submit handler
    submitBtn.addEventListener("click", handleCashSubmit);
    console.log("Cash payment handler initialized successfully");
}

// Handler function for cash submit
function handleCashSubmit(e) {
    e.preventDefault();
    console.log("Submit button clicked");

    const addressText = document.getElementById("addressText");
    const currentAddress = addressText.textContent.trim();
    console.log("Current address:", currentAddress);

    if (!currentAddress || currentAddress === "No address provided") {
        Swal.fire({
            title: "Address Required",
            text: "Please provide a delivery address",
            icon: "warning",
            confirmButtonText: "OK"
        });
        return;
    }

    // Get total amount from finalAmount field (includes voucher deductions)
    const finalAmountField = document.getElementById("finalAmount");
    let totalAmount = finalAmountField ? parseFloat(finalAmountField.value) : 0;
    console.log("Total amount from finalAmount field:", totalAmount);

    // Fallback to breakdown calculation if finalAmount not found
    if (!totalAmount) {
        const breakdownItems = document.querySelectorAll(".breakdown-item");
        console.log("Fallback: Breakdown items found:", breakdownItems.length);

        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector("span:last-child").textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ""));
        }
    }

    // Confirmation dialog before submitting COD order
    Swal.fire({
        title: "Confirm Cash on Delivery Order",
        html: "You are about to submit a Cash on Delivery order.<br>" +
            "<strong>You will pay ₱" + totalAmount.toFixed(2) + " upon delivery.</strong><br>" +
            "Please ensure your address is correct.",
        icon: "info",
        showCancelButton: true,
        confirmButtonText: "Yes, Confirm Order",
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
            submitCODPayment();
        }
    });
}

// Submit function
function submitCODPayment() {
    console.log("submitCODPayment called");

    const submitBtn = document.getElementById("submitCashPaymentBtn");
    const cashModal = document.getElementById("cashModal");
    const cashSuccessModal = document.getElementById("cashSuccessModal");

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
    console.log("Amount to submit:", totalAmount);

    // Fallback to breakdown calculation if finalAmount not found
    if (!totalAmount) {
        const breakdownItems = document.querySelectorAll(".breakdown-item");
        if (breakdownItems.length > 0) {
            const lastItem = breakdownItems[breakdownItems.length - 1];
            const amountText = lastItem.querySelector("span:last-child").textContent;
            totalAmount = parseFloat(amountText.replace(/[^0-9.]/g, ""));
        }
    }

    const formData = new FormData();
    formData.append("booking_id", bookingId);
    formData.append("amount", totalAmount.toString());

    // Get reward information if applied
    const selectedRewardId = document.getElementById("selectedRewardId");
    const selectedRewardName = document.getElementById("selectedRewardName");
    const originalAmountDisplay = document.getElementById("displayAmount");

    if (selectedRewardId && selectedRewardId.value) {
        formData.append("reward_id", selectedRewardId.value);
    }
    if (selectedRewardName && selectedRewardName.value) {
        formData.append("reward_name", selectedRewardName.value);
    }

    // Calculate and send discount amount if reward is applied
    if (selectedRewardName && selectedRewardName.value) {
        // Get original amount
        const originalAmountElement = document.getElementById("displayAmount");
        if (originalAmountElement) {
            const displayText = originalAmountElement.textContent || originalAmountElement.innerText;
            const originalAmount = parseFloat(displayText.replace(/[^0-9.]/g, ""));
            const discountAmount = originalAmount - totalAmount;
            if (discountAmount > 0) {
                formData.append("discount_amount", discountAmount.toFixed(2));
                formData.append("original_amount", originalAmount.toFixed(2));
            }
        }
    }

    console.log("Fetching to process-cod-payment.php");

    fetch("../includes/process-cod-payment.php", {
        method: "POST",
        body: formData
    })
        .then(response => {
            console.log("Response status:", response.status);
            return response.json();
        })
        .then(data => {
            console.log("Response data:", data);

            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                console.log("Payment submission successful");

                // Hide the cash modal - try multiple ways
                try {
                    const bsModal = bootstrap.Modal.getInstance(cashModal);
                    if (bsModal) {
                        console.log("Hiding cash modal using instance");
                        bsModal.hide();
                    } else {
                        console.log("No modal instance found, hiding directly");
                        cashModal.style.display = "none";
                        document.body.classList.remove("modal-open");
                        const backdrop = document.querySelector(".modal-backdrop");
                        if (backdrop) backdrop.remove();
                    }
                } catch (e) {
                    console.log("Error hiding modal:", e);
                    cashModal.style.display = "none";
                    document.body.classList.remove("modal-open");
                }

                // Show success modal
                try {
                    const successModal = new bootstrap.Modal(cashSuccessModal);
                    console.log("Showing success modal");
                    successModal.show();
                } catch (e) {
                    console.error("Error showing success modal:", e);
                    cashSuccessModal.style.display = "block";
                    document.body.classList.add("modal-open");
                }

                document.getElementById("successCashBookingId").textContent = "#" + bookingId;
                document.getElementById("successCashAmount").textContent = "₱" + totalAmount.toFixed(2);

                // Update the amount in the modal body
                const paymentAmountInModal = document.getElementById("paymentAmountInModal");
                if (paymentAmountInModal) {
                    paymentAmountInModal.textContent = "₱" + totalAmount.toFixed(2);
                }

                const okBtn = document.getElementById("cashSuccessOkBtn");
                if (okBtn) {
                    okBtn.onclick = function () {
                        console.log("OK button clicked, redirecting to user-profile.php");
                        const rewardName = document.getElementById("selectedRewardName").value;
                        let redirectUrl = "user-profile.php";
                        if (rewardName) {
                            redirectUrl += "?reward_used=" + encodeURIComponent(rewardName);
                        }
                        window.location.href = redirectUrl;
                    };
                }
            } else {
                console.log("Payment submission failed:", data.error);
                Swal.fire({
                    title: "Submission Failed",
                    text: data.error || "An error occurred while submitting your order",
                    icon: "error",
                    confirmButtonText: "Try Again"
                });
            }
        })
        .catch(error => {
            console.error("Payment submission error:", error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            Swal.fire({
                title: "Error",
                text: "Failed to submit payment request. Please try again.",
                icon: "error",
                confirmButtonText: "OK"
            });
        });
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", initCashPayment);

// Also try to initialize immediately in case DOM is already loaded
if (document.readyState === "loading") {
    console.log("Document still loading, waiting for DOMContentLoaded");
} else {
    console.log("Document already loaded, initializing immediately");
    initCashPayment();
}
