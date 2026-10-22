let map = null;
let marker = null;
let locationPermissionAsked = false;

// =========================
// 1. HELPERS
// =========================
function hasValidSavedLocation() {
    const addressInput = document.getElementById('Address');
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');

    return !!(
        addressInput &&
        latInput &&
        lngInput &&
        addressInput.value.trim() !== '' &&
        addressInput.value !== 'Invalid location' &&
        addressInput.value !== 'Error fetching address' &&
        addressInput.value !== '📍 Locating...' &&
        latInput.value.trim() !== '' &&
        lngInput.value.trim() !== ''
    );
}

function resetStep1Errors() {
    document.querySelectorAll('#part-1 .error-text').forEach(el => {
        el.style.display = 'none';
    });

    const username = document.getElementById('username-input');
    const email = document.getElementById('email-input');
    const phoneWrapper = document.querySelector('.phone-input-wrapper');
    const genderWrapper = document.getElementById('gender-wrapper');

    if (username) username.classList.remove('error');
    if (email) email.classList.remove('error');
    if (phoneWrapper) phoneWrapper.classList.remove('error');
    if (genderWrapper) {
        genderWrapper.classList.remove('error');
        genderWrapper.style.borderColor = 'transparent';
    }
}

function shakeElement(el) {
    if (!el) return;
    el.style.animation = "shake 0.5s";
    setTimeout(() => {
        el.style.animation = "";
    }, 500);
}

// =========================
// 2. GOAL 1 — PHONE NORMALISATION (mirrors otp_proses.php logic)
// Strips +60 / 60 prefix → local 01X format
// =========================
function normalisePhone(raw) {
    let digits = raw.replace(/\D/g, '');
    if (digits.length >= 11 && digits.startsWith('60')) {
        digits = '0' + digits.slice(2);
    }
    return digits;
}

/**
 * Returns true if the already-normalised local phone is valid Malaysia mobile.
 * - Starts with 01
 * - 011XXXXXXXX → exactly 11 digits
 * - 01XXXXXXXXX → exactly 10 digits
 */
function isValidMalaysiaPhone(local) {
    if (!/^01/.test(local)) return false;
    return local.startsWith('011') ? local.length === 11 : local.length === 10;
}

// =========================
// 3. GOAL 2 — GMAIL-ONLY EMAIL VALIDATION
// =========================
function isGmailOnly(email) {
    return /^[a-zA-Z0-9._%+\-]+@gmail\.com$/i.test(email.trim());
}

// =========================
// 4. STEP SWITCHING
// =========================
function showPart(partNumber) {
    document.querySelectorAll('.form-part').forEach(part => {
        part.style.display = 'none';
        part.classList.remove('active');
    });

    const targetPart = document.getElementById(`part-${partNumber}`);
    if (targetPart) {
        targetPart.style.display = 'block';
        targetPart.classList.add('active');

        if (partNumber === 2) {
            requestAnimationFrame(() => {
                initMap();

                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                    }
                }, 250);

                // Only show modal if never asked AND no valid saved location exists
                if (!locationPermissionAsked && !hasValidSavedLocation()) {
                    showLocationModal();
                }
            });
        }
    }
}

// =========================
// 5. LOCATION MODAL
// =========================
function showLocationModal() {
    const modal = document.getElementById('location-modal');
    if (modal) {
        modal.style.display = 'flex';
        locationPermissionAsked = true;
    }
}

function requestLocationPermission() {
    const modal = document.getElementById('location-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    getUserLocation();
}

function skipLocationPermission() {
    const modal = document.getElementById('location-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// =========================
// 6. LOCATION ANALYSIS
// =========================
function analyzeLocationData(data) {
    if (!data || data.error || !data.address) {
        return { valid: false, reason: 'No valid address found' };
    }

    const displayName = (data.display_name || '').toLowerCase().trim();
    const locType = (data.type || '').toLowerCase();
    const locClass = (data.class || '').toLowerCase();
    const addresstype = (data.addresstype || '').toLowerCase();
    const name = (data.name || '').toLowerCase();
    const addr = data.address || {};

    const textToCheck = `${displayName} ${name} ${addresstype}`.toLowerCase();

    const blockedKeywords = [
        'ocean', 'sea', 'river', 'lake', 'strait', 'channel', 'reservoir',
        'forest', 'jungle', 'desert',
        'antarctica', 'arctic', 'south pole', 'north pole', 'bridge'
    ];

    const blockedTypes = [
        'water', 'river', 'sea', 'lake', 'reservoir', 'strait',
        'forest', 'wood', 'desert'
    ];

    const blockedClasses = ['waterway', 'water'];

    if (
        blockedKeywords.some(word => textToCheck.includes(word)) ||
        blockedTypes.includes(locType) ||
        blockedTypes.includes(addresstype) ||
        blockedClasses.includes(locClass)
    ) {
        return { valid: false, reason: 'Blocked area' };
    }

    const isTooBroad =
        displayName === 'malaysia' ||
        (!!addr.country && !addr.road && !addr.neighbourhood && !addr.suburb && !addr.residential && !addr.house_number) ||
        (addresstype === 'country') ||
        (addresstype === 'state') ||
        (addresstype === 'region');

    if (isTooBroad) {
        return { valid: false, reason: 'Too broad location' };
    }

    const hasLocalContext =
        addr.road ||
        addr.neighbourhood ||
        addr.suburb ||
        addr.residential ||
        addr.house_number ||
        addr.village ||
        addr.town ||
        addr.city_district;

    if (!hasLocalContext) {
        return { valid: false, reason: 'No local address context' };
    }

    return { valid: true, reason: 'OK' };
}

// =========================
// 7. ADDRESS + COORDS UPDATE
// =========================
async function updateAddressFromCoords(lat, lng) {
    const addressInput = document.getElementById('Address');
    const errInvalidLoc = document.getElementById('err-invalid-loc');
    const errLocation = document.getElementById('err-location');
    const searchContainer = document.querySelector('.search-container');
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');

    if (!addressInput || !latInput || !lngInput || !searchContainer) return false;

    // Temporarily allow writing (field is readonly, we set via JS)
    addressInput.removeAttribute('readonly');
    addressInput.value = "📍 Locating...";
    addressInput.setAttribute('readonly', '');

    errInvalidLoc.style.display = 'none';
    errLocation.style.display = 'none';
    searchContainer.style.borderColor = "#E3E2F0";

    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        const result = analyzeLocationData(data);

        if (!result.valid) {
            addressInput.removeAttribute('readonly');
            addressInput.value = "Invalid location";
            addressInput.setAttribute('readonly', '');

            if (errInvalidLoc) {
                errInvalidLoc.textContent = "Please select a valid residential address. Non-residential areas such as sea, forest, or empty land are not allowed.";
                errInvalidLoc.style.display = 'block';
            }
            searchContainer.style.borderColor = "#ff4d4d";

            latInput.value = '';
            lngInput.value = '';
            return false;
        }

        addressInput.removeAttribute('readonly');
        addressInput.value = data.display_name || "Address not found";
        addressInput.setAttribute('readonly', '');

        latInput.value = String(lat);
        lngInput.value = String(lng);
        return true;

    } catch (error) {
        console.error('Error fetching address:', error);

        addressInput.removeAttribute('readonly');
        addressInput.value = "Error fetching address";
        addressInput.setAttribute('readonly', '');

        errInvalidLoc.style.display = 'block';
        searchContainer.style.borderColor = "#ff4d4d";

        latInput.value = '';
        lngInput.value = '';
        return false;
    }
}

// =========================
// 8. GEOLOCATION
// =========================
function getUserLocation() {
    if (!navigator.geolocation) {
        alert("Your browser doesn't support location services. Please pin your location manually on the map.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            if (map && marker) {
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                await updateAddressFromCoords(lat, lng);
            }
        },
        (error) => {
            console.error('Geolocation error:', error);

            let errorMsg = "Unable to get your location. ";
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg += "Please allow location access in your browser settings or pin your location manually on the map.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg += "Location information is unavailable. Please pin your location manually.";
                    break;
                case error.TIMEOUT:
                    errorMsg += "Location request timed out. Please pin your location manually.";
                    break;
                default:
                    errorMsg += "Please pin your location manually on the map.";
            }

            alert(errorMsg);
        }
    );
}

// =========================
// 9. MAP INIT
// =========================
function initMap() {
    const mapDiv = document.getElementById('map');
    const latInput = document.getElementById('lat');
    const lngInput = document.getElementById('lng');

    if (!mapDiv) return;

    let startLat = parseFloat(latInput?.value);
    let startLng = parseFloat(lngInput?.value);

    const hasSavedCoords = !isNaN(startLat) && !isNaN(startLng);
    const startCoords = hasSavedCoords ? [startLat, startLng] : [5.4164, 100.3327];
    const startZoom = hasSavedCoords ? 16 : 13;

    if (map) {
        map.setView(startCoords, startZoom);
        if (marker) {
            marker.setLatLng(startCoords);
        }
        map.invalidateSize();
        return;
    }

    map = L.map('map').setView(startCoords, startZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    marker = L.marker(startCoords, { draggable: true }).addTo(map);

    const updateLocation = async (latlng) => {
        marker.setLatLng(latlng);
        await updateAddressFromCoords(latlng.lat, latlng.lng);
    };

    marker.on('dragend', async (e) => {
        await updateLocation(e.target.getLatLng());
    });

    map.on('click', async (e) => {
        await updateLocation(e.latlng);
    });

    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 250);
}

// =========================
// 10. PASSWORD TOGGLE
// =========================
function togglePassword(inputId, el) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    el.innerText = isPassword ? '🙉' : '🙈';
}

// =========================
// 11. PASSWORD RULES
// =========================
function validatePasswordRequirements(val) {
    return {
        length: val.length >= 8 && val.length <= 15,
        case: /[A-Z]/.test(val) && /[a-z]/.test(val),
        number: /\d/.test(val),
        symbol: /[@$!%*?&]/.test(val)
    };
}

function updatePasswordUI(val, prefix) {
    const specs = validatePasswordRequirements(val);

    const update = (id, valid) => {
        const el = document.getElementById(id);
        if (!el) return;

        const plainText = el.textContent.replace(/^✅\s|^❌\s/, '');
        el.className = valid ? 'valid' : 'invalid';
        el.textContent = `${valid ? '✅' : '❌'} ${plainText}`;
    };

    update(`${prefix}-length`, specs.length);
    update(`${prefix}-case`, specs.case);
    update(`${prefix}-number`, specs.number);
    update(`${prefix}-symbol`, specs.symbol);

    return Object.values(specs).every(Boolean);
}

function isPasswordValid(password) {
    const specs = validatePasswordRequirements(password);
    return Object.values(specs).every(Boolean);
}

// =========================
// 12. DOM READY
// =========================
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('multiStepForm');
    const next1Btn = document.getElementById('next-1');
    const next2Btn = document.getElementById('next-2');
    const locateBtn = document.getElementById('locate-me-btn');

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    // If page already has saved valid location, don't ask again
    if (hasValidSavedLocation()) {
        locationPermissionAsked = true;
    }

    // ─────────────────────────────────────────────────────────────
    // GOAL 3: Block all manual input on #Address — read-only field
    // Only map pin / reverse geocode / geolocation may set it.
    // ─────────────────────────────────────────────────────────────
    const addressInput = document.getElementById('Address');
    const readonlyMsg  = document.getElementById('err-address-readonly');

    function showAddressReadonlyWarning(e) {
        // Prevent any attempt to modify the field manually
        e.preventDefault();
        if (readonlyMsg) {
            readonlyMsg.classList.add('show');
            setTimeout(() => readonlyMsg.classList.remove('show'), 3500);
        }
    }

    if (addressInput) {
        // Block keyboard typing
        addressInput.addEventListener('keydown', showAddressReadonlyWarning);
        // Block paste
        addressInput.addEventListener('paste',   showAddressReadonlyWarning);
        // Block cut
        addressInput.addEventListener('cut',     showAddressReadonlyWarning);
        // Show tip on focus
        addressInput.addEventListener('focus',   () => {
            if (readonlyMsg) {
                readonlyMsg.classList.add('show');
                setTimeout(() => readonlyMsg.classList.remove('show'), 3500);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────
    // STEP 1 VALIDATION
    // ─────────────────────────────────────────────────────────────
    if (next1Btn) {
        next1Btn.onclick = () => {
            const username = document.getElementById('username-input');
            const email = document.getElementById('email-input');
            const phone = document.getElementById('phone-input');
            const gender = document.querySelector('input[name="gender"]:checked');
            const genderWrapper = document.getElementById('gender-wrapper');
            const phoneWrapper = document.querySelector('.phone-input-wrapper');

            let isValid = true;
            resetStep1Errors();

            // Username
            if (!username.value.trim()) {
                isValid = false;
                document.getElementById('err-username').style.display = 'inline';
                username.classList.add('error');
            }

            // GOAL 2: Gmail-only email validation
            const emailVal = email.value.trim();
            if (!emailVal) {
                isValid = false;
                document.getElementById('err-email-empty').style.display = 'inline';
                email.classList.add('error');
            } else if (!isGmailOnly(emailVal)) {
                isValid = false;
                document.getElementById('err-email-format').style.display = 'inline';
                email.classList.add('error');
            }

            // GOAL 1: Phone — normalise first then validate length rules
            const rawPhone = phone.value.trim();
            const localPhone = normalisePhone(rawPhone);

            if (!rawPhone) {
                isValid = false;
                document.getElementById('err-phone').textContent = '* Phone number is required';
                document.getElementById('err-phone').style.display = 'inline';
                if (phoneWrapper) phoneWrapper.classList.add('error');
            } else if (!localPhone.startsWith('01')) {
                isValid = false;
                document.getElementById('err-phone').textContent = '* Phone must start with 01 (e.g. 0123456789)';
                document.getElementById('err-phone').style.display = 'inline';
                if (phoneWrapper) phoneWrapper.classList.add('error');
            } else if (localPhone.startsWith('011') && localPhone.length !== 11) {
                isValid = false;
                document.getElementById('err-phone').textContent = '* 011 numbers must be exactly 11 digits (e.g. 01112345678)';
                document.getElementById('err-phone').style.display = 'inline';
                if (phoneWrapper) phoneWrapper.classList.add('error');
            } else if (!localPhone.startsWith('011') && localPhone.length !== 10) {
                isValid = false;
                document.getElementById('err-phone').textContent = '* 012–019 numbers must be exactly 10 digits (e.g. 0123456789)';
                document.getElementById('err-phone').style.display = 'inline';
                if (phoneWrapper) phoneWrapper.classList.add('error');
            }

            if (!gender) {
                isValid = false;
                document.getElementById('err-gender').style.display = 'inline';
                if (genderWrapper) {
                    genderWrapper.classList.add('error');
                    genderWrapper.style.borderColor = "#ff4d4d";
                }
            }

            if (isValid) {
                showPart(2);
            }
        };
    }

    // STEP 2 VALIDATION
    if (next2Btn) {
        next2Btn.onclick = () => {
            const latInput = document.getElementById('lat');
            const lngInput = document.getElementById('lng');
            const errLocation = document.getElementById('err-location');
            const errInvalidLoc = document.getElementById('err-invalid-loc');
            const searchContainer = document.querySelector('.search-container');
            const addrInput = document.getElementById('Address');

            errLocation.style.display = 'none';
            errInvalidLoc.style.display = 'none';
            searchContainer.style.borderColor = "#E3E2F0";

            const hasValidLocation =
                latInput.value.trim() !== '' &&
                lngInput.value.trim() !== '' &&
                addrInput.value.trim() !== '' &&
                addrInput.value !== 'Invalid location' &&
                addrInput.value !== 'Error fetching address' &&
                addrInput.value !== '📍 Locating...';

            if (!hasValidLocation) {
                errLocation.style.display = 'block';
                searchContainer.style.borderColor = "#ff4d4d";
                shakeElement(searchContainer);
                return;
            }

            showPart(3);
        };
    }

    // Locate button
    if (locateBtn) {
        locateBtn.onclick = () => {
            getUserLocation();
        };
    }

    // Password UI
    if (password) {
        updatePasswordUI(password.value, 'req');
        password.addEventListener('input', () => {
            updatePasswordUI(password.value, 'req');
        });
    }

    if (confirmPassword) {
        updatePasswordUI(confirmPassword.value, 'conf-req');
        confirmPassword.addEventListener('input', () => {
            updatePasswordUI(confirmPassword.value, 'conf-req');
        });
    }

    // Final submit validation
    if (registerForm) {
        registerForm.onsubmit = (e) => {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            document.querySelectorAll('#part-3 .error-text').forEach(el => {
                el.style.display = 'none';
            });

            if (passwordInput) passwordInput.classList.remove('error');
            if (confirmPasswordInput) confirmPasswordInput.classList.remove('error');

            let isValid = true;

            if (!passwordInput.value.trim()) {
                isValid = false;
                document.getElementById('err-password-empty').style.display = 'inline';
                passwordInput.classList.add('error');
            } else if (!isPasswordValid(passwordInput.value)) {
                isValid = false;
                document.getElementById('err-password-format').style.display = 'inline';
                passwordInput.classList.add('error');
            }

            if (!confirmPasswordInput.value.trim()) {
                isValid = false;
                document.getElementById('err-confirm-empty').style.display = 'inline';
                confirmPasswordInput.classList.add('error');
            } else if (passwordInput.value !== confirmPasswordInput.value) {
                isValid = false;
                document.getElementById('err-confirm-match').style.display = 'inline';
                confirmPasswordInput.classList.add('error');
            }

            if (!isValid) {
                e.preventDefault();
                return false;
            }

            return true;
        };
    }
});