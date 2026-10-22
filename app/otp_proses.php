<?php
// ============================================================================
// PHP Setups
// ============================================================================
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================================
// General Page Functions
// ============================================================================
function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post($key, $value = null) {
    $value = $_POST[$key] ?? $value;
    return is_array($value) ? array_map('trim', $value) : trim((string)$value);
}

// ============================================================================
// GOAL 1 — Phone normalisation helper (local Malaysia mobile format)
// Strips +60 or 60 country-code prefix → local 01X format.
// Returns the normalised string; empty string if input is empty.
// ============================================================================
function normalise_phone(string $raw): string {
    $digits = preg_replace('/\D/', '', $raw);
    // Strip 60-prefix only when the result would still start with 01
    if (strlen($digits) >= 11 && substr($digits, 0, 2) === '60') {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

/**
 * Validates a normalised local phone number.
 * - Must start with 01
 * - 011XXXXXXXX → exactly 11 digits
 * - 01XXXXXXXXX → exactly 10 digits
 */
function is_valid_malaysia_phone(string $local): bool {
    if (!preg_match('/^01/', $local)) return false;
    return substr($local, 0, 3) === '011'
        ? strlen($local) === 11
        : strlen($local) === 10;
}

// ============================================================================
// GOAL 2 — Gmail-only email validation
// Only allows addresses ending exactly with @gmail.com (case-insensitive).
// ============================================================================
function is_gmail_only(string $email): bool {
    return (bool) preg_match('/^[a-zA-Z0-9._%+\-]+@gmail\.com$/i', trim($email));
}

function redirect_back_with_errors($errors = [], $data = [], $step = 1) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['temp_user'] = $data;
    $_SESSION['form_step'] = $step;
    header("Location: register.php?edit=1");
    exit();
}

function save_temp_user_and_redirect($message, $data = [], $step3 = true) {
    $_SESSION['temp_user'] = $data;
    $target = $step3 ? 'register.php?edit=1' : 'register.php';
    echo "<script>alert(" . json_encode($message) . "); window.location.href='{$target}';</script>";
    exit();
}

// ============================================================================
// Generate New User ID
// ----------------------------------------------------------------------------
// REQUIRES a counter table in your database. Run these two SQL statements
// once in phpMyAdmin (Query tab) before using this function:
//
//   Step 1 — Create the counter table:
//   CREATE TABLE IF NOT EXISTS id_counters (
//       counter_name VARCHAR(50) NOT NULL PRIMARY KEY,
//       last_number  INT         NOT NULL DEFAULT 0
//   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
//
//   Step 2 — Insert the initial row for the user counter.
//   Replace 6 below with the highest UR number already in your users table.
//   For example, if your last user was UR006, use 6. If starting fresh, use 0.
//   INSERT INTO id_counters (counter_name, last_number)
//   VALUES ('user', 6)
//   ON DUPLICATE KEY UPDATE last_number = last_number; -- safe re-run guard
//
// After this, every new registration increments last_number by 1 inside a
// transaction with FOR UPDATE, so deleted IDs are NEVER reused and concurrent
// registrations NEVER receive the same ID.
// ============================================================================
function generate_user_id($_db) {
    try {
        $_db->beginTransaction();

        // Lock the counter row so concurrent requests wait their turn
        $stmt = $_db->prepare(
            "SELECT last_number FROM id_counters
             WHERE counter_name = 'user'
             FOR UPDATE"
        );
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            // Counter row is missing — abort and give a clear error message
            $_db->rollBack();
            throw new RuntimeException(
                "User ID counter not found. " .
                "Please run the id_counters setup SQL in phpMyAdmin first."
            );
        }

        $newNumber = (int)$row->last_number + 1;

        $update = $_db->prepare(
            "UPDATE id_counters
             SET    last_number = :num
             WHERE  counter_name = 'user'"
        );
        $update->execute([':num' => $newNumber]);

        $_db->commit();

        return 'UR' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        // Roll back only if a transaction is still open
        if ($_db->inTransaction()) {
            $_db->rollBack();
        }
        throw $e; // re-throw so the caller's catch block handles it
    }
}

// ============================================================================
// Email Functions
// ============================================================================
function get_mail() {
    require_once 'lib/PHPMailer.php';
    require_once 'lib/SMTP.php';

    $m = new PHPMailer(true);
    $m->SMTPDebug = 2;
    $m->Debugoutput = 'html';
    $m->isSMTP();
    $m->SMTPAuth = true;
    $m->Host = 'smtp.gmail.com';
    $m->Port = 587;
    $m->SMTPSecure = 'tls';
    $m->Username = 'twu37712@gmail.com';
    $m->Password = 'ghcq wjyq leyh cyzq';
    $m->CharSet = 'utf-8';
    $m->setFrom($m->Username, 'FOODTIME ADMIN');
    $m->addReplyTo($m->Username, 'FOODTIME ADMIN');

    return $m;
}

function send_otp_email($target_email) {
    $otp_code = rand(100000, 999999);

    $_SESSION['otp_code'] = $otp_code;
    $_SESSION['otp_expire'] = time() + 60;
    $_SESSION['otp_generated_at'] = time();

    if (isset($_SESSION['previous_otp'])) {
        unset($_SESSION['previous_otp']);
    }

    $m = get_mail();

    try {
        $m->addAddress($target_email);
        $m->isHTML(true);
        $m->Subject = "Your OTP Verification Code";
        $m->Body = "Your verification code is: <b>$otp_code</b>. Valid for 1 minute.";
        $m->send();

        error_log("OTP sent: " . $otp_code . " (expires at: " . date('H:i:s', $_SESSION['otp_expire']) . ")");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// Database Setups
// ============================================================================
try {
    $_db = new PDO(
        'mysql:dbname=foodtime;host=localhost;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================================================
// AJAX Handler: Resend OTP
// ============================================================================
if (is_post() && isset($_POST['resend_otp'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['temp_user']) || !isset($_SESSION['temp_user']['email'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Session expired. Please register again.'
        ]);
        exit();
    }

    $email = $_SESSION['temp_user']['email'];

    if (!isset($_SESSION['otp_send_count'])) {
        $_SESSION['otp_send_count'] = 1;
    }

    // Total sends limit = 3 (initial send included)
    if ($_SESSION['otp_send_count'] >= 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Maximum OTP send limit reached. Please change your email address.'
        ]);
        exit();
    }

    // Cooldown between sends
    if (isset($_SESSION['otp_generated_at'])) {
        $timeSinceLastOTP = time() - $_SESSION['otp_generated_at'];
        if ($timeSinceLastOTP < 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Please wait ' . (10 - $timeSinceLastOTP) . ' seconds before requesting a new OTP.'
            ]);
            exit();
        }
    }

    if (isset($_SESSION['otp_code'])) {
        $_SESSION['previous_otp'] = $_SESSION['otp_code'];
    }

    if (send_otp_email($email)) {
        $_SESSION['otp_send_count']++;

        $remaining = max(0, 3 - $_SESSION['otp_send_count']);
        $message = 'OTP sent. Please check your Inbox or Spam folder.';

        if ($remaining === 1) {
            $message = 'OTP sent. This is your last resend attempt. Please check Spam/Junk carefully.';
        } elseif ($remaining === 0) {
            $message = 'OTP sent. No resend attempts left after this. Please change your email if you still do not receive it.';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'send_count' => $_SESSION['otp_send_count'],
            'remaining' => $remaining
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP. Please go back and verify your email address.'
        ]);
    }
    exit();
}

// ============================================================================
// 1. Handle registration form submission from register.php
// ============================================================================
if (is_post() && isset($_POST['register_step'])) {
    $username = post('username');
    $email    = post('email');
    $address  = post('address');
    $latitude = post('latitude');
    $longitude = post('longitude');
    $gender   = post('gender');
    $password = post('password');
    $confirm_password = post('confirm_password');

    // ── GOAL 1: Normalise phone to local format before any validation ──
    $phone_raw = post('phone');
    $phone     = normalise_phone($phone_raw);

    $temp_data = [
        'username'  => $username,
        'email'     => $email,
        'phone'     => $phone,      // always store local format in temp
        'address'   => $address,
        'latitude'  => $latitude,
        'longitude' => $longitude,
        'gender'    => $gender
    ];

    // ────────────────────────────────────────────────────────────────────
    // Step 1 validation
    // ────────────────────────────────────────────────────────────────────
    $step1_errors = [];

    if ($username === '') {
        $step1_errors['username'] = '* Username is required';
    }

    // GOAL 2: Gmail-only email validation
    if ($email === '') {
        $step1_errors['email'] = '* Email is required';
    } elseif (!is_gmail_only($email)) {
        $step1_errors['email'] = '* Only Gmail addresses ending with @gmail.com are allowed';
    }

    // GOAL 1: Phone validation using normalised local format
    if ($phone === '') {
        $step1_errors['phone'] = '* Phone number is required';
    } elseif (!preg_match('/^01/', $phone)) {
        $step1_errors['phone'] = '* Phone must start with 01 (e.g. 0123456789)';
    } elseif (substr($phone, 0, 3) === '011' && strlen($phone) !== 11) {
        $step1_errors['phone'] = '* 011 numbers must be exactly 11 digits (e.g. 01112345678)';
    } elseif (substr($phone, 0, 3) !== '011' && strlen($phone) !== 10) {
        $step1_errors['phone'] = '* 012–019 numbers must be exactly 10 digits (e.g. 0123456789)';
    }

    $allowed_genders = ['male', 'female'];
    if ($gender === '') {
        $step1_errors['gender'] = '* Gender is required';
    } elseif (!in_array($gender, $allowed_genders, true)) {
        $step1_errors['gender'] = '* Invalid gender selected';
    }

    if (!empty($step1_errors)) {
        redirect_back_with_errors($step1_errors, $temp_data, 1);
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 2 validation
    // ────────────────────────────────────────────────────────────────────
    $step2_errors = [];

    if ($address === '') {
        $step2_errors['address'] = '* Address is required';
    }

    if ($latitude === '' || $longitude === '') {
        $step2_errors['location'] = '* Please pin your location on the map';
    }

    if (!empty($step2_errors)) {
        redirect_back_with_errors($step2_errors, $temp_data, 2);
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 3 validation
    // ────────────────────────────────────────────────────────────────────
    $step3_errors = [];

    if ($password === '') {
        $step3_errors['password'] = '* Password is required';
    } else {
        if (strlen($password) < 8 || strlen($password) > 15) {
            $step3_errors['password'] = '* Password must be between 8 and 15 characters';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
            $step3_errors['password'] = '* Password must contain uppercase and lowercase letters';
        } elseif (!preg_match('/\d/', $password)) {
            $step3_errors['password'] = '* Password must contain at least one number';
        } elseif (!preg_match('/[@$!%*?&]/', $password)) {
            $step3_errors['password'] = '* Password must contain at least one special character (@$!%*?&)';
        }
    }

    if ($confirm_password === '') {
        $step3_errors['confirm_password'] = '* Confirm password is required';
    } elseif ($password !== '' && $password !== $confirm_password) {
        $step3_errors['confirm_password'] = '* Passwords do not match';
    }

    if (!empty($step3_errors)) {
        redirect_back_with_errors($step3_errors, $temp_data, 3);
    }

    // ────────────────────────────────────────────────────────────────────
    // Database duplicate check
    // GOAL 1: Normalise all stored phone values when comparing to handle
    //         old data that may have +60 or 60 prefix.
    // ────────────────────────────────────────────────────────────────────
    try {
        $db_errors = [];

        $checkUsername = $_db->prepare("SELECT userid FROM users WHERE username = ?");
        $checkUsername->execute([$username]);
        if ($checkUsername->fetch()) {
            $db_errors['username'] = 'This username is already taken.';
        }

        $checkEmail = $_db->prepare("SELECT userid FROM users WHERE LOWER(email) = LOWER(?)");
        $checkEmail->execute([trim($email)]);
        if ($checkEmail->fetch()) {
            $db_errors['email'] = 'This email is already registered.';
        }

        // GOAL 1: Compare against all stored phones, normalising each one
        // so that old +60 / 60 values still match the new local format.
        $allPhones = $_db->query("SELECT userid, phone FROM users WHERE phone IS NOT NULL AND phone != ''");
        foreach ($allPhones as $row) {
            $stored_normalised = normalise_phone($row->phone);
            if ($stored_normalised === $phone) {
                $db_errors['phone'] = 'This phone number is already registered.';
                break;
            }
        }

        if (!empty($db_errors)) {
            redirect_back_with_errors($db_errors, $temp_data, 1);
        }

    } catch (Exception $e) {
        save_temp_user_and_redirect('Database Error: ' . $e->getMessage(), $temp_data, true);
    }

    // Store normalised local phone in session temp data
    $_SESSION['temp_user'] = [
        'username'  => $username,
        'email'     => $email,
        'phone'     => $phone,      // local 01X format, no +60
        'address'   => $address,
        'latitude'  => $latitude,
        'longitude' => $longitude,
        'gender'    => $gender,
        'password'  => $password
    ];

    $_SESSION['otp_send_count'] = 0;

    if (send_otp_email($email)) {
        $_SESSION['otp_send_count'] = 1;
        header("Location: otp.php");
        exit();
    } else {
        save_temp_user_and_redirect('❌ Failed to send email. Check your SMTP settings or internet connection.', $temp_data, true);
    }
}

// ============================================================================
// 2. Handle OTP verification from otp.php
// ============================================================================
if (is_post() && isset($_POST['verify_otp'])) {

    // ── FIX: Read the six individual named fields (otp_1 … otp_6) ──────────
    // This matches the field names used in otp.php's HTML form.
    $user_otp = post('otp_1') . post('otp_2') . post('otp_3') .
                post('otp_4') . post('otp_5') . post('otp_6');

    // Strip anything that is not a digit (extra safety net)
    $user_otp = preg_replace('/\D/', '', $user_otp);

    // Validate completeness before anything else
    if (strlen($user_otp) !== 6) {
        echo "<script>alert('⚠️ Please enter all 6 digits of the OTP.'); window.location.href='otp.php';</script>";
        exit();
    }

    if (!isset($_SESSION['otp_code'])) {
        echo "<script>alert('❌ No OTP found. Please request a new one.'); window.location.href='otp.php';</script>";
        exit();
    }

    if (!isset($_SESSION['temp_user'])) {
        echo "<script>alert('❌ Registration session expired. Please register again.'); window.location.href='register.php';</script>";
        exit();
    }

    if (isset($_SESSION['previous_otp']) && $user_otp == $_SESSION['previous_otp']) {
        echo "<script>alert('❌ This OTP has been invalidated. Please use the latest OTP.'); window.location.href='otp.php';</script>";
        exit();
    }

    if (time() >= $_SESSION['otp_expire']) {
        echo "<script>alert('⏰ OTP has expired! Please request a new OTP.'); window.location.href='otp.php';</script>";
        exit();
    }

    if ($user_otp == $_SESSION['otp_code']) {
        $data = $_SESSION['temp_user'];

        // GOAL 1: Re-normalise phone from session just before insert
        // (belt-and-suspenders: it should already be local format at this point)
        $phone_to_save = normalise_phone($data['phone'] ?? '');

        // Safety check: phone must still be valid before DB insert
        if (!is_valid_malaysia_phone($phone_to_save)) {
            echo "<script>alert('❌ Invalid phone number in session. Please register again.'); window.location.href='register.php';</script>";
            exit();
        }

        try {
            $newUserId = generate_user_id($_db);

            $sql = "INSERT INTO users (userid, username, email, phone, address, gender, password, position, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stm = $_db->prepare($sql);

            $stm->execute([
                $newUserId,
                $data['username'],
                $data['email'],
                $phone_to_save,     // always local 01X format
                $data['address'],
                $data['gender'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                'customer',
                'active'
            ]);

            unset($_SESSION['temp_user']);
            unset($_SESSION['otp_code']);
            unset($_SESSION['otp_expire']);
            unset($_SESSION['otp_generated_at']);
            unset($_SESSION['previous_otp']);
            unset($_SESSION['otp_send_count']);
            unset($_SESSION['form_errors']);
            unset($_SESSION['form_step']);

            echo "<script>alert('🎆 Congratulations! Register successful!'); window.location.href='login.php';</script>";
            exit();

        } catch (Exception $e) {
            echo "<script>alert('Database Error: " . addslashes($e->getMessage()) . "'); window.location.href='otp.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('❌ Invalid OTP! Please check and try again.'); window.location.href='otp.php';</script>";
        exit();
    }
}
?>