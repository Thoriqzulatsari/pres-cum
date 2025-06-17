<?php
require_once 'config.php';

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = sanitize($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password, user_type, full_name FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if ($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $user_type, $full_name);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["user_type"] = $user_type;
                            $_SESSION["full_name"] = $full_name;
                            
                            // Redirect user to appropriate dashboard
                            if ($user_type == "admin") {
                                redirect("admin/dashboard.php");
                            } else {
                                redirect("resident/dashboard.php");
                            }
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PresDorm</title>
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
            max-width: 1000px;
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
            justify-content: center;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            position: relative;
            display: inline-block;
        }

        .login-header h2::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .login-header p {
            color: var(--text-light);
            font-size: 1rem;
        }

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
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 111, 255, 0.1);
            background-color: var(--white);
        }

        .form-group i.input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            transition: var(--transition);
            opacity: 0.7;
        }

        .form-control:focus + i.input-icon {
            color: var(--primary-color);
            opacity: 1;
        }

        .invalid-feedback {
            font-size: 0.85rem;
            color: #dc3545;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-login {
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

        .btn-login:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
            transform: translateY(-2px);
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
        }

        .login-footer p {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 1rem;
        }

        .social-login a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--background-color);
            color: var(--text-light);
            transition: var(--transition);
        }

        .social-login a:hover {
            transform: translateY(-3px);
        }

        .social-login a.facebook:hover {
            background-color: #1877f2;
            color: white;
        }

        .social-login a.google:hover {
            background-color: #ea4335;
            color: white;
        }

        .social-login a.twitter:hover {
            background-color: #1da1f2;
            color: white;
        }

        .alert-error {
            background-color: #fff4f4;
            color: #dc3545;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 3px solid #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Password field styling */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px; /* Make room for the eye icon */
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 0;
            font-size: 1.1rem;
            opacity: 0.7;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 850px) {
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
                min-height: 250px;
            }

            .left-panel h1 {
                font-size: 2rem;
            }

            .building-image {
                display: none;
            }

            .right-panel {
                padding: 30px 20px;
            }
        }

        @media (max-width: 480px) {
            .back-to-home span {
                display: none;
            }
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
            <h1>Welcome Back!</h1>
            <p>Sign in to access your dormitory management dashboard and stay connected with your campus life.</p>
            <img src="/images/push.jpeg" alt="Dormitory Building" class="building-image" onerror="this.src='/api/placeholder/400/300'; this.onerror=null;">
        </div>

        <div class="right-panel">
            <div class="login-header">
                <h2>Sign In</h2>
                <p>Enter your credentials to continue your journey</p>
            </div>

            <?php if (!empty($login_err)) : ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $login_err; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <input type="text" name="username" 
                           id="username"
                           class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                           placeholder="Username" 
                           value="<?php echo $username; ?>">
                    <i class="fas fa-user input-icon"></i>
                    <?php if (!empty($username_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $username_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div class="password-wrapper">
                        <input type="password" name="password" 
                               id="password"
                               class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                               placeholder="Password">
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <?php if (!empty($password_err)): ?>
                        <div class="invalid-feedback">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $password_err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-login">
                    Sign In
                    <i class="fas fa-sign-in-alt"></i>
                </button>

                <div class="login-footer">
                    <p>Don't have an account yet? <a href="register.php">Register now</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Input animation
        const inputs = document.querySelectorAll('.form-control');
        
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.style.transform = 'translateY(-3px)';
                input.parentElement.style.transition = 'transform 0.3s ease';
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>