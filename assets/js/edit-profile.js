function initProfilePicturePreview() {
    const fileInput = document.querySelector('input[name="profile_picture"]');
    
    if (!fileInput) {
        return;
    }
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.profile-picture') || document.querySelector('.profile-picture-placeholder');
                if (img.tagName === 'IMG') {
                    img.src = e.target.result;
                } else {
                    // Replace placeholder with image
                    const container = document.querySelector('.profile-picture-container');
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.className = 'profile-picture';
                    newImg.alt = 'Profile Picture';
                    container.innerHTML = '';
                    container.appendChild(newImg);
                }
            }
            reader.readAsDataURL(file);
        }
    });
}

function initFormValidation() {
    const form = document.querySelector('form');
    
    if (!form) {
        return; // No form on this page
    }
    
    form.addEventListener('submit', function(e) {
        const phone = document.querySelector('input[name="phone"]').value;
        if (phone && !/^[\d\s\-\+\(\)]{10,}$/.test(phone)) {
            e.preventDefault();
            alert('Please enter a valid phone number');
            return false;
        }
    });
}

function initEditProfilePage() {
    initProfilePicturePreview();
    initFormValidation();
    
    console.log('Edit profile page initialized');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditProfilePage);
} else {
    // DOM already loaded
    initEditProfilePage();
}

window.EditProfile = {
    initProfilePicturePreview,
    initFormValidation,
    initEditProfilePage
};