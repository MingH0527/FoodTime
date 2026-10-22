<?php
session_start();
$_title = "Register";

$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';

/* Only reuse temp_user when coming back from a failed/edited flow */
$temp_user = $edit_mode ? ($_SESSION['temp_user'] ?? []) : [];

$form_errors = $_SESSION['form_errors'] ?? [];
$form_step = $_SESSION['form_step'] ?? null;

unset($_SESSION['form_errors']);
unset($_SESSION['form_step']);

$old_username = htmlspecialchars($temp_user['username'] ?? '');
$old_email    = htmlspecialchars($temp_user['email'] ?? '');
$old_gender   = $temp_user['gender'] ?? '';

// GOAL 1: Normalise old phone to local format (strip +60 or 60 prefix)
$old_phone = '';
if (!empty($temp_user['phone'])) {
    $raw = preg_replace('/\D/', '', $temp_user['phone']);
    // Strip 60 country code prefix → local 01X format
    if (strlen($raw) >= 11 && substr($raw, 0, 2) === '60') {
        $raw = '0' . substr($raw, 2);
    }
    $old_phone = $raw;
}
$old_phone = htmlspecialchars($old_phone);

$old_address = htmlspecialchars($temp_user['address'] ?? '');
$old_lat     = htmlspecialchars($temp_user['latitude'] ?? '');
$old_lng     = htmlspecialchars($temp_user['longitude'] ?? '');

/* Optional: when entering normal register page, clear old temp data */
if (!$edit_mode && isset($_SESSION['temp_user'])) {
    unset($_SESSION['temp_user']);
}

$step_to_show = 1;

if (!empty($form_step)) {
    $step_to_show = (int)$form_step;
} elseif ($edit_mode) {
    $step_to_show = 3;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?? 'Untitled' ?></title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/register.css">

    <style>
        /* ══ Progress Bar — sits below each step's h2 ══ */
        .progress-wrap {
            width: 100%;
            margin: 0 0 24px 0;
            padding: 0 4px;
        }
        .progress-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .progress-row::before {
            content: '';
            position: absolute;
            top: 18px; left: 18px; right: 18px;
            height: 4px;
            background: #e8e8f0;
            border-radius: 4px;
            z-index: 0;
        }
        .progress-fill {
            position: absolute;
            top: 18px; left: 18px;
            height: 4px;
            background: linear-gradient(to right, #C8E5E4, #7a7adb);
            border-radius: 4px;
            z-index: 1;
            width: 0%;
            transition: width .45s ease;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 2;
        }
        .step-circle {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 3px solid #ddd;
            background: #f0f0f5;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #bbb;
            transition: all .4s ease;
        }
        .step-item.s-active .step-circle {
            background: linear-gradient(135deg,#7a7adb,#a78bfa);
            border-color: #7a7adb; color: #fff;
            box-shadow: 0 4px 14px rgba(122,122,219,.4);
            transform: scale(1.1);
        }
        .step-item.s-done .step-circle {
            background: linear-gradient(135deg,#C8E5E4,#7a7adb);
            border-color: #7a7adb; color: #fff;
        }
        .step-item.s-done .step-circle::after {
            content:'✓';
            font-size:16px;
            font-weight:700;
        }
        .step-item.s-done .step-num {
            display:none;
        }
        .step-lbl {
            font-size: 11px;
            font-weight: 600;
            color: #bbb;
            margin-top: 6px;
            text-align: center;
            transition: color .4s;
        }
        .step-item.s-active .step-lbl,
        .step-item.s-done .step-lbl {
            color: #7a7adb;
        }
        .progress-pct {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            color: #7a7adb;
            margin-top: 6px;
        }

        .error {
            border: 2px solid #ff4d4d !important;
        }

        .error-text {
            color: #ff4d4d;
            font-size: 12px;
            margin-left: 6px;
            display: none;
        }

        .error-text.show {
            display: inline;
        }

        .server-error {
            display: inline !important;
        }

        .gender-selection-wrapper {
            transition: all .3s ease;
            border: 2px solid transparent;
            border-radius: 15px;
            padding: 10px;
        }

        .gender-selection-wrapper.error {
            border: 2px solid #ff4d4d !important;
        }

        /* ══ Location permission modal ══ */
        .location-modal-overlay {
            display: none;
            position: fixed;
            top:0; left:0;
            width:100%;
            height:100%;
            background: rgba(0,0,0,.5);
            z-index:9999;
            justify-content:center;
            align-items:center;
        }
        .location-modal {
            background:#fff;
            padding:30px;
            border-radius:20px;
            max-width:400px;
            text-align:center;
            box-shadow:0 10px 40px rgba(0,0,0,.2);
        }
        .location-modal h3 {
            margin-bottom:15px;
            color:#333;
            font-size:20px;
        }
        .location-modal p {
            margin-bottom:25px;
            color:#666;
            font-size:14px;
            line-height:1.6;
        }
        .modal-buttons {
            display:flex;
            gap:10px;
        }
        .modal-btn {
            flex:1;
            padding:12px 20px;
            border:none;
            border-radius:25px;
            font-weight:bold;
            cursor:pointer;
            transition:.3s;
        }
        .modal-btn-allow {
            background:linear-gradient(to right,#7a7adb,#a78bfa);
            color:white;
        }
        .modal-btn-allow:hover {
            transform:translateY(-2px);
            box-shadow:0 5px 15px rgba(122,122,219,.3);
        }
        .modal-btn-skip {
            background:#f1f1f1;
            color:#666;
        }
        .modal-btn-skip:hover {
            background:#e0e0e0;
        }

        /* GOAL 3: Address read-only style */
        #Address[readonly] {
            background: #f5f5fa;
            cursor: not-allowed;
            color: #666;
        }
        .address-readonly-msg {
            display: none;
            color: #e07b00;
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
        }
        .address-readonly-msg.show {
            display: block;
        }
    </style>
</head>

<body>
    <!-- Location Permission Modal -->
    <div class="location-modal-overlay" id="location-modal">
        <div class="location-modal">
            <h3>📍 Enable Location Access</h3>
            <p>We'd like to use your current location to help pin your address on the map.</p>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-skip" onclick="skipLocationPermission()">Skip for Now</button>
                <button type="button" class="modal-btn modal-btn-allow" onclick="requestLocationPermission()">Allow Location</button>
            </div>
        </div>
    </div>

    <div class="register-card">
        <form id="multiStepForm" action="otp_proses.php" method="post" autocomplete="off">

            <input style="display:none" type="text" name="fake_email">
            <input style="display:none" type="password" name="fake_password">

            <!-- STEP 1 -->
            <div class="form-part active" id="part-1">
                <a href="index.php" class="back">↩️ BACK</a>
                <h2>Register</h2>

                <div class="progress-wrap" id="progressWrap">
                    <div class="progress-row">
                        <div class="progress-fill" id="progressFill"></div>

                        <div class="step-item s-active" id="si-1">
                            <div class="step-circle"><span class="step-num">1</span></div>
                            <div class="step-lbl">Profile</div>
                        </div>

                        <div class="step-item" id="si-2">
                            <div class="step-circle"><span class="step-num">2</span></div>
                            <div class="step-lbl">Location</div>
                        </div>

                        <div class="step-item" id="si-3">
                            <div class="step-circle"><span class="step-num">3</span></div>
                            <div class="step-lbl">Security</div>
                        </div>
                    </div>
                    <div class="progress-pct" id="progressPct">Step 1 of 3 — 33% complete</div>
                </div>

                <div class="input-field">
                    <label>👤 Username
                        <span class="error-text <?= !empty($form_errors['username']) ? 'show server-error' : '' ?>" id="err-username">
                            <?= !empty($form_errors['username']) ? htmlspecialchars($form_errors['username']) : '* Required' ?>
                        </span>
                    </label>
                    <input
                        type="text"
                        name="username"
                        id="username-input"
                        placeholder="Please Enter Your Name"
                        autocomplete="new-password"
                        maxlength="30"
                        value="<?= $old_username ?>"
                        class="<?= !empty($form_errors['username']) ? 'error' : '' ?>">
                </div>

                <div class="input-field">
                    <label>✉️ Email
                        <span class="error-text" id="err-email-empty">* Required</span>
                        <span class="error-text" id="err-email-format">* Only Gmail addresses ending with @gmail.com are allowed</span>
                        <?php if (!empty($form_errors['email'])): ?>
                            <span class="error-text show server-error"><?= htmlspecialchars($form_errors['email']) ?></span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="email"
                        name="email"
                        id="email-input"
                        placeholder="example@gmail.com"
                        autocomplete="new-password"
                        maxlength="100"
                        value="<?= $old_email ?>"
                        class="<?= !empty($form_errors['email']) ? 'error' : '' ?>">
                </div>

                <div class="input-field">
                    <label>📱 Phone Number
                        <span class="error-text <?= !empty($form_errors['phone']) ? 'show server-error' : '' ?>" id="err-phone">
                           <?= !empty($form_errors['phone']) ? htmlspecialchars($form_errors['phone']) : '* Required' ?>
                        </span>
                    </label>
                    <!-- GOAL 1: +60 shown as fixed prefix; user types local digits only -->
                    <div class="phone-input-wrapper">
                        <span class="country-code">🇲🇾</span>
                        <input type="tel" id="phone-input" name="phone_display"
                               placeholder="e.g. 0123456789 or 01112345678"
                               maxlength="11" inputmode="numeric" autocomplete="new-password"
                               value="<?= $old_phone ?>"
                               class="<?= !empty($form_errors['phone']) ? 'error' : '' ?>">
                    </div>
                    <small style="color:#888;font-size:11px;margin-left:4px;">
                        012–019: 10 digits &nbsp;|&nbsp; 011: 11 digits &nbsp;|&nbsp; Must start with 01
                    </small>
                </div>

                <div class="gender-selection-wrapper <?= !empty($form_errors['gender']) ? 'error' : '' ?>" id="gender-wrapper">
                    <label class="gender-title">Gender
                        <span class="error-text <?= !empty($form_errors['gender']) ? 'show server-error' : '' ?>" id="err-gender">
                            <?= !empty($form_errors['gender']) ? htmlspecialchars($form_errors['gender']) : '* Required' ?>
                        </span>
                    </label>
                    <div class="gender-options">
                        <label class="custom-radio">
                            <input type="radio" name="gender" value="male" <?= ($old_gender === 'male') ? 'checked' : '' ?>>
                            <span class="radio-dot"></span>
                            <span class="gender-text">Male ♂️</span>
                        </label>

                        <label class="custom-radio">
                            <input type="radio" name="gender" value="female" <?= ($old_gender === 'female') ? 'checked' : '' ?>>
                            <span class="radio-dot"></span>
                            <span class="gender-text">Female ♀️</span>
                        </label>
                    </div>
                </div>

                <button type="button" id="next-1" class="next-button">Next Step ➡️</button>
            </div>

            <!-- STEP 2 -->
            <div class="form-part" id="part-2" style="display:none;">
                <h2>Select Location</h2>

                <div class="location-wrapper">
                    <div class="search-container">
                        <!-- GOAL 3: Address is readonly — only map pin/geocode may fill it -->
                        <input
                            type="text"
                            id="Address"
                            name="address"
                            placeholder="Move the pin on the map to get your address"
                            autocomplete="off"
                            readonly
                            value="<?= $old_address ?>"
                            class="<?= !empty($form_errors['address']) ? 'error' : '' ?>">
                        <button type="button" id="locate-me-btn" title="Get Current Location">🎯</button>
                    </div>

                    <!-- GOAL 3: Inline message shown when user tries to type manually -->
                    <div class="address-readonly-msg" id="err-address-readonly">
                        📍 Please drag the pin or click on the map to set your address — manual typing is not allowed.
                    </div>

                    <div id="map"></div>

                    <span class="error-text <?= !empty($form_errors['location']) ? 'show server-error' : '' ?>" id="err-location" style="margin-top:10px;">
                        <?= !empty($form_errors['location']) ? htmlspecialchars($form_errors['location']) : '* Required — please pin your location on the map' ?>
                    </span>

                    <span class="error-text" id="err-invalid-loc" style="display:none; margin-top:10px;">
                        * Invalid Location — please select a location on land
                    </span>

                    <input type="hidden" id="lat" name="latitude" value="<?= $old_lat ?>">
                    <input type="hidden" id="lng" name="longitude" value="<?= $old_lng ?>">
                </div>

                <div class="button-group">
                    <button type="button" onclick="showPart(1)" class="back-button-btn-back-step">↩️ Back</button>
                    <button type="button" id="next-2" class="next-button">Next Step ➡️</button>
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="form-part" id="part-3" style="display:none;">
                <h2>Security</h2>

                <div class="input-field">
                    <label>🔒 Password
                        <span class="error-text <?= !empty($form_errors['password']) ? 'show server-error' : '' ?>" id="err-password-empty">
                            <?= !empty($form_errors['password']) ? htmlspecialchars($form_errors['password']) : '* Required' ?>
                        </span>
                        <span class="error-text" id="err-password-format">* Password format does not match</span>
                    </label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Create Password"
                            autocomplete="new-password"
                            maxlength="15"
                            class="<?= !empty($form_errors['password']) ? 'error' : '' ?>">
                        <span class="toggle-password" onclick="togglePassword('password', this)">🙈</span>
                    </div>

                    <div class="requirements-container">
                        <p id="req-length" class="invalid">❌ 8 to 15 characters</p>
                        <p id="req-case" class="invalid">❌ Upper & Lowercase</p>
                        <p id="req-number" class="invalid">❌ At least one number</p>
                        <p id="req-symbol" class="invalid">❌ Special symbol (@$!%*?&)</p>
                    </div>
                </div>

                <div class="input-field">
                    <label>🛡️ Confirm Password
                        <span class="error-text <?= !empty($form_errors['confirm_password']) ? 'show server-error' : '' ?>" id="err-confirm-empty">
                            <?= !empty($form_errors['confirm_password']) ? htmlspecialchars($form_errors['confirm_password']) : '* Required' ?>
                        </span>
                        <span class="error-text" id="err-confirm-match">* Passwords do not match</span>
                    </label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            placeholder="Confirm Password"
                            autocomplete="new-password"
                            maxlength="15"
                            class="<?= !empty($form_errors['confirm_password']) ? 'error' : '' ?>">
                        <span class="toggle-password" onclick="togglePassword('confirm_password', this)">🙈</span>
                    </div>

                    <div class="requirements-container">
                        <p id="conf-req-length" class="invalid">❌ Exactly 8 characters</p>
                        <p id="conf-req-case" class="invalid">❌ Upper & Lowercase</p>
                        <p id="conf-req-number" class="invalid">❌ At least one number</p>
                        <p id="conf-req-symbol" class="invalid">❌ Special symbol (@$!%*?&)</p>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" onclick="showPart(2)" class="back-button-btn-back-step">↩️ Back</button>
                    <input type="hidden" name="register_step" value="1">
                    <button type="submit" id="submit-btn" class="next-button">Register ✅</button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        function updateProgress(step) {
            const fills  = ['0%', '50%', '100%'];
            const labels = [
                'Step 1 of 3 — 33% complete',
                'Step 2 of 3 — 67% complete',
                'Step 3 of 3 — 100% complete'
            ];

            [1, 2, 3].forEach(function (n) {
                var item = document.getElementById('si-' + n);
                if (!item) return;
                item.classList.remove('s-active', 's-done');
                if (n < step) item.classList.add('s-done');
                else if (n === step) item.classList.add('s-active');
            });

            var fill = document.getElementById('progressFill');
            var pct  = document.getElementById('progressPct');

            if (fill) fill.style.width = fills[step - 1];
            if (pct)  pct.textContent  = labels[step - 1];

            var wrap = document.getElementById('progressWrap');
            var target = document.getElementById('part-' + step);

            if (wrap && target) {
                var h2 = target.querySelector('h2');
                if (h2 && h2.nextSibling) {
                    target.insertBefore(wrap, h2.nextSibling);
                } else if (h2) {
                    target.appendChild(wrap);
                } else {
                    target.prepend(wrap);
                }
            }
        }
    </script>

    <script src="js/register.js"></script>

    <script>
        (function () {
            var _orig = showPart;
            showPart = function (n) {
                _orig(n);
                updateProgress(n);
            };
        })();

        // GOAL 1: Phone input — digits only, enforce local format display
        var phoneInput = document.getElementById('phone-input');
        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                // Strip non-digits
                this.value = this.value.replace(/[^0-9]/g, '');
            });

            document.getElementById('multiStepForm').addEventListener('submit', function () {
                var oldHidden = document.getElementById('hidden-phone');
                if (oldHidden) oldHidden.remove();

                // GOAL 1: Build hidden phone field — store local format (01X...)
                // normalisePhone is defined in register.js but we do it inline here too
                if (phoneInput.value.trim()) {
                    var local = phoneInput.value.replace(/\D/g, '');
                    // If user somehow entered with 60 prefix, strip it
                    if (local.length >= 11 && local.startsWith('60')) {
                        local = '0' + local.slice(2);
                    }
                    var h = document.createElement('input');
                    h.id   = 'hidden-phone';
                    h.type = 'hidden';
                    h.name = 'phone';
                    h.value = local; // store local 01X format, no +60
                    this.appendChild(h);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            showPart(<?= (int)$step_to_show ?>);
        });
    </script>
</body>
</html>