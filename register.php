<?php
require_once 'config.php';

// Initialize variables
$selected_dorm = isset($_GET['dorm']) ? (int)$_GET['dorm'] : 0;
$username = $password = $confirm_password = $email = $full_name = $student_id = "";
$username_err = $password_err = $confirm_password_err = $email_err = $full_name_err = $student_id_err = $dormitory_err = "";
$dormitory_id = 0;
$room_id = 0;

// Load dormitories for dropdown
function getDormitories($conn) {
    $dorms = [];
    $sql = "SELECT id, name FROM dormitories";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $dorms[] = $row;
        }
    }
    return $dorms;
}

// Validate username uniqueness
function isUsernameUnique($conn, $username) {
    $sql = "SELECT id FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        $isUnique = $stmt->num_rows === 0;
        $stmt->close();
        return $isUnique;
    }
    return false;
}

// Validate email uniqueness
function isEmailUnique($conn, $email) {
    $sql = "SELECT id FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $isUnique = $stmt->num_rows === 0;
        $stmt->close();
        return $isUnique;
    }
    return false;
}

// Validate student ID uniqueness
function isStudentIdUnique($conn, $student_id) {
    $sql = "SELECT id FROM resident_profiles WHERE student_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $stmt->store_result();
        $isUnique = $stmt->num_rows === 0;
        $stmt->close();
        return $isUnique;
    }
    return false;
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } else {
        $full_name = sanitize($_POST["full_name"]);
    }
    
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $param_username = sanitize($_POST["username"]);
        if (!isUsernameUnique($conn, $param_username)) {
            $username_err = "This username is already taken.";
        } else {
            $username = $param_username;
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $param_email = sanitize($_POST["email"]);
        if (!isEmailUnique($conn, $param_email)) {
            $email_err = "This email is already registered.";
        } else {
            $email = $param_email;
        }
    }
    
    // Validate student ID
    if (empty(trim($_POST["student_id"]))) {
        $student_id_err = "Student ID (NIM) is required.";
    } else {
        $param_student_id = sanitize($_POST["student_id"]);
        if (!isStudentIdUnique($conn, $param_student_id)) {
            $student_id_err = "This Student ID is already registered.";
        } else {
            $student_id = $param_student_id;
        }
    }
    
    // Validate dormitory
    if (empty($_POST["dormitory_id"])) {
        $dormitory_err = "Please select a dormitory.";
    } else {
        $dormitory_id = (int)$_POST["dormitory_id"];
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && 
        empty($email_err) && empty($full_name_err) && empty($student_id_err) && empty($dormitory_err)) {
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert user
            $sql = "INSERT INTO users (username, password, full_name, email, user_type) VALUES (?, ?, ?, ?, 'resident')";
            
            if ($stmt = $conn->prepare($sql)) {
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt->bind_param("ssss", $username, $param_password, $full_name, $email);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Get random room for the dormitory
                    $room = getRandomAvailableRoom($dormitory_id);
                    
                    if ($room) {
                        $room_id = $room['id'];
                        
                        // Create resident profile
                        $sql2 = "INSERT INTO resident_profiles (user_id, student_id, dormitory_id, room_id, phone, move_in_date) 
                                VALUES (?, ?, ?, ?, '', NOW())";

                        if ($stmt2 = $conn->prepare($sql2)) {
                            $stmt2->bind_param("isii", $user_id, $student_id, $dormitory_id, $room_id);
                            
                            if ($stmt2->execute()) {
                                // Assign room to resident
                                if (assignRoomToResident($room_id, $user_id, $dormitory_id)) {
                                    // Commit transaction
                                    $conn->commit();
                                    
                                    // Store data in session variables
                                    session_start();
                                    $_SESSION["user_id"] = $user_id;
                                    $_SESSION["username"] = $username;
                                    $_SESSION["user_type"] = "resident";
                                    $_SESSION["full_name"] = $full_name;
                                    
                                    // Redirect to dashboard
                                    redirect("resident/dashboard.php");
                                } else {
                                    throw new Exception("Error assigning room");
                                }
                            } else {
                                throw new Exception("Error creating resident profile");
                            }
                            $stmt2->close();
                        }
                    } else {
                        throw new Exception("No available rooms");
                    }
                } else {
                    throw new Exception("Error creating user account");
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            echo "Error: " . $e->getMessage();
        }
    }
}

// Load dormitories
$dorms = getDormitories($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PresDorm</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="images/President_University_Logo.png">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #4c6fff;
            --primary-dark: #3a4fe0;
            --text-color: #333;
            --text-light: #6c757d;
            --border-color: #e1e5eb;
            --background-color: #f5f7fa;
            --white: #fff;
            --shadow: 0 5px 15px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            display: flex;
            max-width: 1100px;
            width: 100%;
            margin: 2rem;
            background-color: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .left-panel {
            width: 45%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: var(--white);
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 70% 20%, rgba(76, 111, 255, 0.3) 0%, transparent 70%);
        }

        .back-to-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            opacity: 0.9;
            transition: var(--transition);
            z-index: 10;
        }

        .back-to-home:hover {
            opacity: 1;
            transform: translateX(-3px);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            z-index: 5;
        }

        .left-panel h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 5;
        }

        .left-panel p {
            font-size: 1rem;
            margin-bottom: 2rem;
            max-width: 80%;
            opacity: 0.9;
            position: relative;
            z-index: 5;
        }

        .building-image {
            max-width: 85%;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            position: relative;
            z-index: 5;
        }

        .right-panel {
            width: 55%;
            padding: 40px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: 700px;
        }

        .register-header {
            margin-bottom: 1.5rem;
        }

        .register-header h2 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }

        .register-header h2::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .register-header p {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--border-color);
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--white);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-light);
            margin: 0 auto 8px;
            transition: var(--transition);
        }

        .step.active .step-number {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--white);
        }

        .step-label {
            font-size: 0.85rem;
            color: var(--text-light);
            white-space: nowrap;
            transition: var(--transition);
        }

        .step.active .step-label {
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
            background-color: var(--background-color);
            position: relative;
            z-index: 1;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 111, 255, 0.1);
            background-color: var(--white);
        }

        .form-group i.field-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            transition: var(--transition);
            opacity: 0.7;
            z-index: 2;
            pointer-events: none;
        }

        .form-control:focus ~ i.field-icon {
            color: var(--primary-color);
            opacity: 1;
        }

        .form-info {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-light);
            font-size: 0.8rem;
            margin-top: 6px;
        }

        .invalid-feedback {
            font-size: 0.85rem;
            color: #dc3545;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-register {
            width: 100%;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 8px;
            padding: 15px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .btn-register:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
            transform: translateY(-2px);
        }

        .login-link {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M8 12l-6-6h12l-6 6z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        /* Password strength */
        .password-strength {
            height: 4px;
            background-color: var(--border-color);
            margin-top: 10px;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.1rem;
            opacity: 0.7;
            transition: var(--transition);
            z-index: 3;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        /* FIX: Ensure icons are properly aligned and visible */
        .form-group {
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .form-group i.field-icon {
            position: absolute;
            left: 15px;
            top: 16px;
            transform: none;
            z-index: 2;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 16px;
            transform: none;
            z-index: 2;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                margin: 0;
                max-height: 100vh;
                border-radius: 0;
            }

            .left-panel, .right-panel {
                width: 100%;
            }

            .left-panel {
                padding: 30px 20px;
                min-height: 200px;
            }

            .left-panel h1 {
                font-size: 2rem;
            }

            .building-image {
                display: none;
            }

            .right-panel {
                padding: 30px 20px;
                max-height: none;
            }

            .step-label {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .back-to-home span {
                display: none;
            }
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .right-panel::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .right-panel {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <a href="index.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Home</span>
            </a>
            <div class="logo-text">PresDorm</div>
            <h1>Join PresDorm</h1>
            <p>Create your account and streamline your campus living experience with our dormitory management system.</p>
            <img src="images/dormitory-hero.png" alt="Dormitory Building" class="building-image" onerror="this.src='/api/placeholder/400/300'; this.onerror=null;">
        </div>

        <div class="right-panel">
            <div class="register-header">
                <h2>Create Account</h2>
                <p>Fill out the form to join the PresDorm community</p>
            </div>

            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-label">Account Details</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-label">Dormitory Selection</div>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . (isset($_GET['dorm']) ? '?dorm=' . $_GET['dorm'] : '')); ?>" method="post" id="registerForm">
                <!-- Personal Information Section -->
                <div class="form-group">
                    <input type="text" name="full_name" 
                           id="full_name"
                           class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Full Name" 
                           value="<?php echo $full_name; ?>">
                    <i class="fas fa-user field-icon"></i>
                    <?php if (!empty($full_name_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $full_name_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input type="text" name="student_id" 
                        id="student_id"
                        class="form-control <?php echo (!empty($student_id_err)) ? 'is-invalid' : ''; ?>" 
                        placeholder="Student ID (NIM)" 
                        value="<?php echo $student_id; ?>">
                    <i class="fas fa-id-card field-icon"></i>
                    <?php if (!empty($student_id_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $student_id_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input type="email" name="email" 
                           id="email"
                           class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Email Address" 
                           value="<?php echo $email; ?>">
                    <i class="fas fa-envelope field-icon"></i>
                    <?php if (!empty($email_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $email_err; ?>
                        </div>
                    <?php else: ?>
                        <div class="form-info">
                            <i class="fas fa-info-circle"></i>
                            We'll never share your email with anyone else.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Account Details Section -->
                <div class="form-group">
                    <input type="text" name="username" 
                           id="username"
                           class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Username" 
                           value="<?php echo $username; ?>">
                    <i class="fas fa-user-circle field-icon"></i>
                    <?php if (!empty($username_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $username_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input type="password" name="password" 
                           id="password"
                           class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Password">
                    <i class="fas fa-lock field-icon"></i>
                    <span class="password-toggle" id="togglePassword">
                        <i class="far fa-eye"></i>
                    </span>
                    <?php if (!empty($password_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $password_err; ?>
                        </div>
                    <?php else: ?>
                        <div class="password-strength">
                            <div class="strength-meter" id="passwordStrength"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input type="password" name="confirm_password" 
                           id="confirm_password"
                           class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Confirm Password">
                    <i class="fas fa-lock field-icon"></i>
                    <span class="password-toggle" id="toggleConfirmPassword">
                        <i class="far fa-eye"></i>
                    </span>
                    <?php if (!empty($confirm_password_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $confirm_password_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Dormitory Selection Section -->
                <div class="form-group">
                    <select name="dormitory_id" 
                            id="dormitory_id"
                            class="form-control <?php echo (!empty($dormitory_err)) ? 'is-invalid' : ''; ?>">
                        <option value="">-- Select Your Dormitory --</option>
                        <?php foreach ($dorms as $dorm): ?>
                            <option value="<?php echo $dorm['id']; ?>" 
                                    <?php echo ($selected_dorm == $dorm['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dorm['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-building field-icon"></i>
                    <?php if (!empty($dormitory_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $dormitory_err; ?>
                        </div>
                    <?php else: ?>
                        <div class="form-info">
                            <i class="fas fa-info-circle"></i>
                            A room will be automatically assigned to you from available options.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Room Type Selection (only for New Beverly Hills) -->
                <div class="form-group" id="room_type_group" style="display: none;">
                    <select class="form-control" id="room_type" name="room_type">
                        <option value="sharing">Sharing Room</option>
                        <option value="single" id="single_room_option">Single Room</option>
                    </select>
                    <i class="fas fa-bed field-icon"></i>
                    <div class="form-info">
                        <i class="fas fa-info-circle"></i>
                        <span id="room_type_info">Single rooms have limited availability.</span>
                    </div>
                </div>

                <button type="submit" class="btn-register">
                    Create Account
                    <i class="fas fa-user-plus"></i>
                </button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form elements
            const form = document.getElementById('registerForm');
            const inputs = form.querySelectorAll('.form-control');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthMeter = document.getElementById('passwordStrength');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const dormitorySelect = document.getElementById('dormitory_id');
            const roomTypeGroup = document.getElementById('room_type_group');
            
            // Update progress steps based on form completion
            function updateProgressSteps() {
                // Personal Info (Step 1)
                const fullName = document.getElementById('full_name').value;
                const email = document.getElementById('email').value;
                const studentId = document.getElementById('student_id').value;
                
                // Account Details (Step 2)
                const username = document.getElementById('username').value;
                const password = passwordInput.value;
                const confirmPassword = confirmInput.value;
                
                // Dormitory Selection (Step 3)
                const dormitory = dormitorySelect.value;
                
                // Update step indicators
                document.getElementById('step1').classList.add('active');
                
                if (fullName && email && studentId) {
                    document.getElementById('step2').classList.add('active');
                } else {
                    document.getElementById('step2').classList.remove('active');
                    document.getElementById('step3').classList.remove('active');
                    return;
                }
                
                if (username && password && confirmPassword && password === confirmPassword) {
                    document.getElementById('step3').classList.add('active');
                } else {
                    document.getElementById('step3').classList.remove('active');
                }
            }
            
            // Update password strength meter
            function updatePasswordStrength(value) {
                if (!strengthMeter) return;
                
                let strength = 0;
                
                if (value.length > 0) {
                    // Length check
                    if (value.length >= 8) {
                        strength += 25;
                    } else if (value.length >= 6) {
                        strength += 10;
                    }
                    
                    // Character variety checks
                    if (value.match(/[A-Z]/)) strength += 25;  // Uppercase
                    if (value.match(/[0-9]/)) strength += 25;  // Numbers
                    if (value.match(/[^A-Za-z0-9]/)) strength += 25;  // Special chars
                    
                    // Set meter width
                    strengthMeter.style.width = strength + '%';
                    
                    // Set color based on strength
                    if (strength <= 25) {
                        strengthMeter.style.backgroundColor = '#dc3545';  // Weak (red)
                    } else if (strength <= 50) {
                        strengthMeter.style.backgroundColor = '#ffc107';  // Fair (yellow)
                    } else if (strength <= 75) {
                        strengthMeter.style.backgroundColor = '#3a86ff';  // Good (blue)
                    } else {
                        strengthMeter.style.backgroundColor = '#20c997';  // Strong (green)
                    }
                } else {
                    strengthMeter.style.width = '0%';
                }
            }
            
            // Toggle password visibility
            function togglePasswordVisibility(field, toggle) {
                if (!field || !toggle) return;
                
                toggle.addEventListener('click', function() {
                    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
                    field.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
            
            // Check single room availability
            function checkSingleRoomAvailability() {
                // Ajax request to check availability
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'check_single_room.php', true);
                xhr.onload = function() {
                    if (this.status == 200) {
                        const singleRoomOption = document.getElementById('single_room_option');
                        const roomTypeInfo = document.getElementById('room_type_info');
                        
                        if (this.responseText === '0') {
                            // If no single room available
                            singleRoomOption.disabled = true;
                            roomTypeInfo.innerHTML = 'Sorry, single rooms are full. Only sharing rooms are available.';
                            roomTypeInfo.style.color = '#dc3545';
                        } else {
                            // If single rooms are available
                            singleRoomOption.disabled = false;
                            roomTypeInfo.innerHTML = 'There are ' + this.responseText + ' single rooms available.';
                            roomTypeInfo.style.color = '#28a745';
                        }
                    }
                };
                xhr.send();
            }
            
            // Attach event listeners
            inputs.forEach(input => {
                input.addEventListener('input', updateProgressSteps);
                
                // Input animation
                input.addEventListener('focus', () => {
                    input.style.transform = 'translateY(-1px)';
                    input.style.transition = 'transform 0.3s ease';
                });
                
                input.addEventListener('blur', () => {
                    input.style.transform = 'translateY(0)';
                });
            });
            
            // Password strength
            if (passwordInput && strengthMeter) {
                passwordInput.addEventListener('input', function() {
                    updatePasswordStrength(this.value);
                    
                    // Update confirm password validation
                    if (confirmInput.value.length > 0) {
                        if (confirmInput.value === this.value) {
                            confirmInput.style.borderColor = '#20c997';
                        } else {
                            confirmInput.style.borderColor = '#dc3545';
                        }
                    }
                });
            }
            
            // Confirm password validation
            if (confirmInput && passwordInput) {
                confirmInput.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        if (this.value === passwordInput.value) {
                            this.style.borderColor = '#20c997';
                        } else {
                            this.style.borderColor = '#dc3545';
                        }
                    } else {
                        this.style.borderColor = '';
                    }
                });
            }
            
            // Toggle password visibility
            togglePasswordVisibility(passwordInput, togglePassword);
            togglePasswordVisibility(confirmInput, toggleConfirmPassword);
            
            // Dormitory selection
            if (dormitorySelect && roomTypeGroup) {
                dormitorySelect.addEventListener('change', function() {
                    const dormId = this.value;
                    
                    // ID 2 is New Beverly Hills (based on database)
                    if (dormId == 2) {
                        roomTypeGroup.style.display = 'block';
                        checkSingleRoomAvailability();
                    } else {
                        roomTypeGroup.style.display = 'none';
                    }
                });
                
                // Check if New Beverly Hills is already selected
                if (dormitorySelect.value == 2) {
                    roomTypeGroup.style.display = 'block';
                    checkSingleRoomAvailability();
                }
            }
            
            // Initialize progress steps
            updateProgressSteps();
        });
    </script>
</body>
</html>