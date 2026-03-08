<?php
require '../includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    // Update last login time before destroying session
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    session_destroy();
    header("Location: auth.php");
    exit();
}

// Handle login/register
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } elseif (isset($_POST['register']) && empty($email)) {
        $error = "Email is required for registration";
    } else {
        if (isset($_POST['register'])) {
            // Registration
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                try {
                    $pdo->beginTransaction();
                    $stmt->execute([$username, $email, $hashed]);
                    $user_id = $pdo->lastInsertId();

                    // Create default preferences for new user
                    $pref_stmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
                    $pref_stmt->execute([$user_id]);

                    $pdo->commit();

                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    header("Location: dashboard.php");
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if ($e->errorInfo[1] == 1062) {
                        // Duplicate entry
                        if (strpos($e->getMessage(), 'username') !== false) {
                            $error = "Username already taken";
                        } else {
                            $error = "Email already registered";
                        }
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        } else {
            // Login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify($password, $user['password_hash'])) {
                    // Update last login time
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update_stmt->execute([$user['id']]);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "User not found";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($_POST['register']) || isset($_GET['register']) ? 'Register' : 'Login' ?> - Tiny Tales</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cream: #F8F0DA;
            --sage: #BAD580;
            --blush: #EFABA3;
            --light-blush: #fad8d4ff;
            --forest: #3E8440;
            --text-dark: #1A1A1A;
            --text-light: #F5F5F5;
            --gradient: linear-gradient(135deg, var(--forest) 0%, var(--sage) 100%);
            --soft-glow: 0 0 15px rgba(239, 171, 163, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--cream);
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../assets/img/auth-bg.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.75;
            z-index: 0;
        }

        .auth-container {
            background-color: rgba(248, 240, 218, 0.95);
            border-radius: 50px;
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            border: 1px solid rgba(186, 213, 128, 0.3);
        }

        .auth-tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(186, 213, 128, 0.3);
            justify-content: center;
            /* Add this line to center the tabs */
        }

        .auth-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--forest);
            position: relative;
            transition: all 0.3s ease;
        }

        .auth-tab.active {
            color: var(--blush);
        }

        .auth-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient);
        }

        .auth-tab:hover:not(.active) {
            color: var(--blush);
            opacity: 0.8;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            color: var(--forest);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 2rem;
        }

        .error {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 0.75rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--forest);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(186, 213, 128, 0.5);
            border-radius: 15px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--blush);
            box-shadow: 0 0 0 3px rgba(239, 171, 163, 0.2);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--forest);
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .toggle-password:hover {
            opacity: 1;
        }

        button[type="submit"] {
            width: 100%;
            padding: 0.8rem;
            border-radius: 15px;
            border: none;
            background: var(--gradient);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            box-shadow: 0 4px 10px rgba(62, 132, 64, 0.3);
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(62, 132, 64, 0.4);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #555;
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: var(--forest);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: var(--blush);
        }

        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .auth-tab {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Page scrollbar */
        body::-webkit-scrollbar {
            width: 10px;
        }

        body::-webkit-scrollbar-track {
            background: rgba(186, 213, 128, 0.1);
        }

        body::-webkit-scrollbar-thumb {
            background-color: var(--sage);
            border-radius: 10px;
            border: 2px solid var(--cream);
        }

        body::-webkit-scrollbar-thumb:hover {
            background-color: var(--forest);
        }

        /* Auth container scrollbar (in case content overflows) */
        .auth-container::-webkit-scrollbar {
            width: 8px;
        }

        .auth-container::-webkit-scrollbar-track {
            background: rgba(186, 213, 128, 0.1);
            border-radius: 0 15px 15px 0;
        }

        .auth-container::-webkit-scrollbar-thumb {
            background-color: var(--blush);
            border-radius: 10px;
            border: 2px solid rgba(248, 240, 218, 0.95);
        }

        .auth-container::-webkit-scrollbar-thumb:hover {
            background-color: var(--forest);
        }

        /* Make sure the auth container can scroll if needed */
        .auth-container {
            max-height: 90vh;
            overflow-y: auto;
        }

        /* For Firefox */
        body {
            scrollbar-width: thin;
            scrollbar-color: var(--sage) rgba(186, 213, 128, 0.1);
        }

        .auth-container {
            scrollbar-width: thin;
            scrollbar-color: var(--blush) rgba(186, 213, 128, 0.1);
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-tabs">
            <div class="auth-tab <?= !isset($_GET['register']) ? 'active' : '' ?>" onclick="switchTab('login')">Login
            </div>
            <div class="auth-tab <?= isset($_GET['register']) ? 'active' : '' ?>" onclick="switchTab('register')">
                Register</div>
        </div>

        <h1><?= isset($_POST['register']) || isset($_GET['register']) ? 'Create Account' : 'Welcome Back' ?></h1>

        <?php if (isset($error)): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <form method="post">
            <?php if (isset($_GET['register']) || isset($_POST['register'])): ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Your email" required
                        value="<?= htmlspecialchars($email ?? '') ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Your username"
                    required value="<?= htmlspecialchars($username ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="Your password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
                <?php if (isset($_GET['register']) || isset($_POST['register'])): ?>
                    <small style="color: #666; font-size: 0.8rem;">Password must be at least 8 characters</small>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['register']) || isset($_POST['register'])): ?>
                <button type="submit" name="register">Register</button>
                <p class="auth-footer">Already have an account? <a href="auth.php">Login here</a></p>
            <?php else: ?>
                <button type="submit">Login</button>
                <p class="auth-footer">Need an account? <a href="auth.php?register">Register here</a></p>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.toggle-password');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function switchTab(tab) {
            if (tab === 'register') {
                window.location.href = 'auth.php?register';
            } else {
                window.location.href = 'auth.php';
            }
        }
    </script>
</body>

</html>