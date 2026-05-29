/**
 * GCASH Payment Handler with Proof Upload
 */

/**
 * Handle GCASH file selection and show preview
 */
function handleGcashFileSelect(event) {
    const file = event.target.files[0];
    const previewContainer = document.getElementById("gcashFilePreview");
    const fileNameElement = document.getElementById("gcashFileName");
    const fileInfoElement = document.getElementById("gcashFileInfo");
    const imagePreviewElement = document.getElementById("gcashImagePreview");

    if (!file) {
        previewContainer.style.display = "none";
        return;
    }

    // Show preview container
    previewContainer.style.display = "block";

    // Update file name and info
    fileNameElement.textContent = file.name;
    fileInfoElement.textContent = "Size: " + (file.size / (1024 * 1024)).toFixed(2) + " MB";

    // Clear previous image preview
    imagePreviewElement.innerHTML = "";

    // Show image preview if it's an image file
    if (file.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = document.createElement("img");
            img.src = e.target.result;
            img.style.maxWidth = "100%";
            img.style.height = "auto";
            img.style.maxHeight = "200px";
            img.style.borderRadius = "8px";
            img.style.border = "2px solid #6f42c1";
            imagePreviewElement.appendChild(img);
        };
        reader.readAsDataURL(file);
    } else if (file.type === "application/pdf") {
        // Show PDF icon for PDF files
        const pdfIcon = document.createElement("div");
        pdfIcon.style.marginTop = "8px";
        pdfIcon.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 2rem; color: #dc3545;"></i>';
        imagePreviewElement.appendChild(pdfIcon);
    }
}

/**
 * Clear GCASH file selection
 */
function clearGcashFile() {
    const fileInput = document.getElementById("gcashProofFile");
    const previewContainer = document.getElementById("gcashFilePreview");

    fileInput.value = "";
    previewContainer.style.display = "none";
}

document.addEventListener("DOMContentLoaded", function () {
    const submitBtn = document.getElementById("submitGcashPaymentBtn");
    const proofFileInput = document.getElementById("gcashProofFile");
    const gcashModal = document.getElementById("gcashModal");
    const gcashSuccessModal = document.getElementById("gcashSuccessModal");

    if (!submitBtn) return;

    submitBtn.addEventListener("click", function (e) {
        e.preventDefault();

        if (!proofFileInput.files || !proofFileInput.files[0]) {
            Swal.fire({
                title: "Proof Required",
                text: "Please upload a screenshot or proof of your GCASH payment",
                icon: "warning",
                confirmButtonText: "OK"
            });
            return;
        }

        const file = proofFileInput.files[0];
        const max_size = 25 * 1024 * 1024;
        const allowed_types = ["image/jpeg", "image/png", "application/pdf"];

        if (!allowed_types.includes(file.type)) {
            Swal.fire({
                title: "Invalid File Type",
                text: "Only JPG, PNG, and PDF files are allowed",
                icon: "error",
                confirmButtonText: "OK"
            });
            return;
        }

        if (file.size > max_size) {
            Swal.fire({
                title: "File Too Large",
                text: "File size must not exceed 25MB",
                icon: "error",
                confirmButtonText: "OK"
            });
            return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin me-2\"></i> Uploading...";

        const idNumberElement = document.querySelector(".id-number");
        const bookingId = idNumberElement ? idNumberElement.textContent.replace("#", "").trim() : "";

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

        let totalAmount = 0;
        // Get total amount from finalAmount field (includes voucher deductions)
        const finalAmountField = document.getElementById("finalAmount");
        totalAmount = finalAmountField ? parseFloat(finalAmountField.value) : 0;

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
        formData.append("guest_token", window.BOOKING_TOKEN || "");
        formData.append("payment_method", "GCASH");
        formData.append("proof_file", file);
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

        fetch("../assets/ajax/upload_payment_proof.php", {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                if (data.success) {
                    const bsModal = bootstrap.Modal.getInstance(gcashModal);
                    if (bsModal) bsModal.hide();

                    const successModal = new bootstrap.Modal(gcashSuccessModal);
                    successModal.show();

                    document.getElementById("successGcashBookingId").textContent = "#" + bookingId;
                    document.getElementById("successGcashAmount").textContent = "₱" + totalAmount.toFixed(2);

                    document.getElementById("gcashSuccessOkBtn").addEventListener("click", function () {
                        const rewardName = document.getElementById("selectedRewardName").value;
                        let redirectUrl;
                        if (window.IS_GUEST) {
                            // Guests have no account — return to their booking confirmation (token-gated)
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
                        text: data.message || "An error occurred while submitting your payment request",
                        icon: "error",
                        confirmButtonText: "Try Again"
                    });
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;

                console.error("Payment submission error:", error);
                Swal.fire({
                    title: "Error",
                    text: "Failed to submit payment request. Please try again.",
                    icon: "error",
                    confirmButtonText: "OK"
                });
            });
    });
});
