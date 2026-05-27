// rewards-claim.js - Handles reward claiming functionality with modal support

function initializeRewardButtons() {
    document.querySelectorAll('.claim-btn').forEach(button => {
        // Remove existing listeners to prevent duplicates
        button.replaceWith(button.cloneNode(true));
    });
    
    // Re-attach event listeners
    document.querySelectorAll('.claim-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const rewardType = this.dataset.rewardType;
            const pointsNeeded = parseInt(this.dataset.pointsNeeded);
            
            // Check if user has enough points - get the actual available points from the page
            const userPointsElement = document.querySelector('.points-number');
            const userPoints = userPointsElement ? parseInt(userPointsElement.textContent.trim()) : 0;
            
            if (userPoints < pointsNeeded) {
                Swal.fire({
                    title: 'Insufficient Points',
                    html: `You need ${pointsNeeded} points to claim this reward.<br>You currently have ${userPoints} points.`,
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Disable button immediately to prevent double clicks
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            claimReward(rewardType, pointsNeeded, this, originalHtml);
        });
    });
}

function claimReward(rewardType, pointsNeeded, buttonElement, originalHtml) {
    Swal.fire({
        title: 'Claim Reward?',
        html: `
            <div class="text-center">
                <i class="bi bi-gift-fill text-purple fs-1 mb-3 d-block"></i>
                <p>You're about to claim: <strong>${rewardType.replace(/_/g, ' ')}</strong></p>
                <p>This will deduct <strong class="text-danger">${pointsNeeded} points</strong> from your account.</p>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> Your reward request will be reviewed by our staff.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#6f42c1',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-check-circle"></i> Yes, Claim It',
        cancelButtonText: '<i class="bi bi-x-circle"></i> Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return processRewardClaimAjax(rewardType);
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        // Always re-enable button
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHtml;
        
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Success!',
                html: `
                    <div class="text-center">
                        <i class="bi bi-check-circle text-success display-4 mb-3"></i>
                        <p>Reward claimed successfully!</p>
                        <p class="text-muted small">Your request has been submitted for review.</p>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'OK',
                willClose: () => {
                    // Refresh the page to update points
                    window.location.reload();
                }
            });
        }
    });
}

function processRewardClaimAjax(rewardType) {
    return fetch('process_claimed_rewards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `reward_type=${encodeURIComponent(rewardType)}&action=claim`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            return data;
        } else {
            throw new Error(data.message || 'Failed to claim reward');
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error!',
            html: `
                <div class="text-start">
                    <p>Failed to claim reward:</p>
                    <p class="text-danger"><small>${error.message}</small></p>
                </div>
            `,
            icon: 'error',
            confirmButtonText: 'OK'
        });
        throw error;
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeRewardButtons();
    
    // Re-initialize after modal content changes
    document.addEventListener('shown.bs.modal', function(event) {
        if (event.target.id === 'task1Modal' || event.target.id === 'task2Modal' || event.target.id === 'task3Modal') {
            setTimeout(initializeRewardButtons, 100);
        }
    });
});