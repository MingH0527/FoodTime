<?php
session_start();

$send_count = $_SESSION['otp_send_count'] ?? 1;
$remaining_attempts = max(0, 3 - $send_count);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link rel="stylesheet" href="/css/otp.css">
    <style>
        .timer-container {
            text-align: center;
            margin: 15px 0;
            font-size: 16px;
            color: #333;
        }

        .timer {
            font-weight: bold;
            font-size: 24px;
            color: #4CAF50;
        }

        .timer.warning {
            color: #ff9800;
        }

        .timer.danger {
            color: #f44336;
        }

        .expired-message {
            color: #f44336;
            font-weight: bold;
            display: none;
        }

        .resend-container {
            text-align: center;
            margin-top: 10px;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #2196F3;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
            padding: 5px 10px;
        }

        .resend-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
            text-decoration: none;
        }

        .resend-btn:hover:not(:disabled) {
            color: #1976D2;
        }

        .success-message {
            color: #4CAF50;
            text-align: center;
            margin-top: 10px;
            display: none;
        }

        .attempt-info {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }

        .change-email-btn {
            background: none;
            border: none;
            color: #d32f2f;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
            margin-top: 10px;
        }

        .change-email-btn:hover {
            color: #b71c1c;
        }
    </style>
</head>
<body>

    <div class="otp-card">
        <button class="close-btn" onclick="goBackToRegister()">&times;</button>

        <div class="icon-container">
            <div class="lock-icon">
                <span class="lock-body"></span>
                <span class="key-shape"></span>
            </div>
        </div>

        <h2>Enter OTP Code</h2>
        <p class="description">
            Enter the 6-digit code sent to your email
        </p>

        <div class="timer-container">
            <div>Time remaining:</div>
            <div class="timer" id="timer">01:00</div>
            <div class="expired-message" id="expired-message">⏰ OTP has expired! Please request a new one.</div>
        </div>

        <form action="otp_proses.php" method="post" id="otpForm" autocomplete="off">
            <div class="otp-inputs" id="otp-inputs">
                <!-- Only the FIRST box uses autocomplete="one-time-code" so browsers can auto-fill -->
                <!-- the full OTP into it; the JS paste handler then distributes it across all boxes. -->
                <!-- All subsequent boxes must use autocomplete="off" to suppress autofill dropdowns. -->
                <input type="tel" name="otp_1" id="otp_1"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>

                <input type="tel" name="otp_2" id="otp_2"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>

                <input type="tel" name="otp_3" id="otp_3"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>

                <input type="tel" name="otp_4" id="otp_4"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>

                <input type="tel" name="otp_5" id="otp_5"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>

                <input type="tel" name="otp_6" id="otp_6"
                       maxlength="1" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="off"
                       autocorrect="off" autocapitalize="off" spellcheck="false" required>
            </div>

            <button type="submit" name="verify_otp" id="verify-btn" class="verify-btn">Verify Code</button>
        </form>

        <div class="extra-links">
            <span>OTP Not Received?</span>
            <div class="resend-container">
                <button
                    type="button"
                    id="resend-btn"
                    class="resend-btn"
                    onclick="resendOTP()"
                    <?= $remaining_attempts <= 0 ? 'disabled' : '' ?>
                >
                    Resend Code
                </button>

                <span id="resend-timer" style="color: #666; font-size: 12px; display: none;"></span>

                <div class="attempt-info" id="attempt-info">
                    <?= $remaining_attempts > 0
                        ? "Remaining resend attempts: {$remaining_attempts}"
                        : "No resend attempts left. Please change your email." ?>
                </div>

                <button type="button" class="change-email-btn" onclick="goBackToRegister()">
                    Change Email
                </button>
            </div>
        </div>

        <div class="success-message" id="success-message">
            ✅ New OTP sent successfully!
        </div>
    </div>

    <script>
        <?php
        if (isset($_SESSION['otp_expire'])) {
            $timeRemaining = $_SESSION['otp_expire'] - time();
            echo "let timeLeft = " . max(0, $timeRemaining) . ";\n";
        } else {
            echo "let timeLeft = 60;\n";
        }
        ?>

        let timerInterval;
        let otpExpired = false;
        let resendCooldown = 0;
        let resendInterval;

        let remainingAttempts = <?= (int)$remaining_attempts ?>;

        const otpInputs = document.querySelectorAll('#otp-inputs input');
        const otpForm = document.getElementById('otpForm');
        const verifyBtn = document.getElementById('verify-btn');
        const timerDisplay = document.getElementById('timer');
        const expiredMessage = document.getElementById('expired-message');
        const resendBtn = document.getElementById('resend-btn');
        const resendTimer = document.getElementById('resend-timer');
        const successMessage = document.getElementById('success-message');
        const attemptInfo = document.getElementById('attempt-info');

        function updateAttemptInfo() {
            if (remainingAttempts > 0) {
                attemptInfo.textContent = `Remaining resend attempts: ${remainingAttempts}`;
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
                resendBtn.onclick = resendOTP;
            } else {
                attemptInfo.textContent = 'No resend attempts left. Please change your email.';
                resendBtn.disabled = true;
                resendBtn.textContent = 'Resend Code';
                resendBtn.onclick = resendOTP;
                resendTimer.style.display = 'none';
            }
        }

        function initOTPInputs() {
            otpInputs.forEach((input, index) => {

                // ── Focus: select existing value for easy overwrite ──────────
                input.addEventListener('focus', function () {
                    this.select();
                });

                // ── Input: keep only the last digit typed ────────────────────
                input.addEventListener('input', function () {
                    // Strip all non-digits
                    const digits = this.value.replace(/\D/g, '');
                    // Keep only the last digit (handles Android soft-keyboard double-fire)
                    this.value = digits.slice(-1);

                    if (this.value.length === 1 && index < otpInputs.length - 1) {
                        // setTimeout 0 gives the browser time to finish its input
                        // event cycle before we move focus — prevents skipped boxes
                        setTimeout(() => otpInputs[index + 1].focus(), 0);
                    }

                    checkAutoSubmit();
                });

                // ── Keydown: backspace, arrow navigation ─────────────────────
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace') {
                        if (this.value !== '') {
                            // Clear current box first; let the browser handle it
                            // (we don't preventDefault so the value is cleared normally)
                        } else if (index > 0) {
                            e.preventDefault();
                            otpInputs[index - 1].focus();
                            otpInputs[index - 1].value = '';
                        }
                    } else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        if (index > 0) otpInputs[index - 1].focus();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        if (index < otpInputs.length - 1) otpInputs[index + 1].focus();
                    }
                });

                // ── Paste: distribute pasted digits across all boxes ─────────
                input.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData)
                        .getData('text')
                        .replace(/\D/g, '')   // strip non-digits
                        .slice(0, 6);         // take up to 6 digits

                    if (pasted.length === 0) return;

                    // Fill from the current box onward
                    let fillIndex = index;
                    for (let i = 0; i < pasted.length; i++) {
                        if (fillIndex + i < otpInputs.length) {
                            otpInputs[fillIndex + i].value = pasted[i];
                        }
                    }

                    // Move focus to next empty box or last filled box
                    const nextEmpty = fillIndex + pasted.length;
                    const focusTarget = Math.min(nextEmpty, otpInputs.length - 1);
                    setTimeout(() => otpInputs[focusTarget].focus(), 0);

                    checkAutoSubmit();
                });
            });
        }

        function checkAutoSubmit() {
            const allFilled = Array.from(otpInputs).every(input => input.value.length === 1);
            if (allFilled && !otpExpired) {
                // Uncomment the line below to enable auto-submit when all digits are entered:
                // otpForm.submit();
            }
        }

        function startTimer() {
            clearInterval(timerInterval);

            timerInterval = setInterval(() => {
                timeLeft--;

                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                if (timeLeft <= 10) {
                    timerDisplay.classList.add('danger');
                    timerDisplay.classList.remove('warning');
                } else if (timeLeft <= 30) {
                    timerDisplay.classList.add('warning');
                    timerDisplay.classList.remove('danger');
                } else {
                    timerDisplay.classList.remove('warning', 'danger');
                }

                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    handleExpiry();
                }
            }, 1000);
        }

        function handleExpiry() {
            otpExpired = true;
            timerDisplay.style.display = 'none';
            expiredMessage.style.display = 'block';
            verifyBtn.disabled = true;
            verifyBtn.style.opacity = '0.5';
            verifyBtn.style.cursor = 'not-allowed';

            otpInputs.forEach(input => {
                input.disabled = true;
                input.style.opacity = '0.5';
            });

            if (remainingAttempts <= 0) {
                alert('⛔ No resend attempts left. Please change your email address.');
                resendBtn.disabled = true;
                resendBtn.textContent = 'Resend Code';
                return;
            }

            if (confirm('⏰ OTP has expired! Would you like to request a new OTP code?')) {
                resendOTP();
            }
        }

        function resendOTP() {
            if (resendCooldown > 0) return;

            if (remainingAttempts <= 0) {
                alert('⛔ No resend attempts left. Please change your email address.');
                return;
            }

            resendBtn.disabled = true;
            resendBtn.textContent = 'Sending...';

            fetch('otp_proses.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'resend_otp=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successMessage.textContent = '✅ ' + (data.message || 'New OTP sent successfully!');
                    successMessage.style.display = 'block';

                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 3500);

                    clearInterval(timerInterval);
                    timeLeft = 60;
                    otpExpired = false;

                    timerDisplay.style.display = 'block';
                    expiredMessage.style.display = 'none';
                    timerDisplay.classList.remove('warning', 'danger');
                    verifyBtn.disabled = false;
                    verifyBtn.style.opacity = '1';
                    verifyBtn.style.cursor = 'pointer';

    otpInputs.forEach(input => {
    input.value = '';
    input.disabled = false;
    input.style.opacity = '1';
    input.blur();
    });

    setTimeout(() => {
    otpInputs[0].focus();
    }, 50);

    startTimer();
    otpInputs.forEach(input => {
    input.value = '';
    input.setAttribute('autocomplete', 'off');
});

                    if (typeof data.remaining !== 'undefined') {
                        remainingAttempts = data.remaining;
                        updateAttemptInfo();
                    }

                    if (remainingAttempts > 0) {
                        startResendCooldown(30);
                    } else {
                        resendBtn.disabled = true;
                        resendBtn.textContent = 'Resend Code';
                        resendTimer.style.display = 'none';
                    }

                } else {
                    alert('❌ ' + (data.message || 'Failed to resend OTP. Please try again.'));

                    if ((data.message || '').includes('Maximum OTP send limit reached')) {
                        remainingAttempts = 0;
                        updateAttemptInfo();
                        resendBtn.disabled = true;
                        resendBtn.textContent = 'Resend Code';
                        resendTimer.style.display = 'none';
                    } else {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Code';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Network error. Please check your connection and try again.');
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend Code';
            });
        }

        function startResendCooldown(seconds) {
            clearInterval(resendInterval);

            resendCooldown = seconds;
            resendBtn.disabled = true;
            resendTimer.style.display = 'inline';
            resendTimer.textContent = `(wait ${resendCooldown}s)`;

            resendInterval = setInterval(() => {
                resendCooldown--;
                resendTimer.textContent = `(wait ${resendCooldown}s)`;

                if (resendCooldown <= 0) {
                    clearInterval(resendInterval);

                    if (remainingAttempts > 0) {
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Code';
                        resendTimer.style.display = 'none';
                    } else {
                        resendBtn.disabled = true;
                        resendBtn.textContent = 'Resend Code';
                        resendTimer.style.display = 'none';
                    }
                }
            }, 1000);
        }

        otpForm.addEventListener('submit', function(e) {
            if (otpExpired) {
                e.preventDefault();

                if (remainingAttempts <= 0) {
                    alert('⛔ No resend attempts left. Please change your email address.');
                    return false;
                }

                if (confirm('⏰ OTP has expired! Would you like to request a new OTP code?')) {
                    resendOTP();
                }
                return false;
            }

            const allFilled = Array.from(otpInputs).every(input => input.value.length === 1);
            if (!allFilled) {
                e.preventDefault();
                alert('⚠️ Please enter all 6 digits of the OTP code.');
                return false;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            updateAttemptInfo();

            if (timeLeft <= 0) {
                handleExpiry();
            } else {
                initOTPInputs();
                startTimer();
                otpInputs[0].focus();
            }
        });

        function goBackToRegister() {
            if (confirm('⚠️ Go back and change your email address?')) {
                window.location.href = 'register.php?edit=1';
            }
        }
    </script>

</body>
</html>