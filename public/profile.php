<?php
require_once __DIR__ . '/../includes/config.php';

class User {
    private $pdo;
    public $id;
    public $username;
    public $email;
    public $created_at;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function loadById($id) {
        $stmt = $this->pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $this->id = $userData['id'];
            $this->username = $userData['username'];
            $this->email = $userData['email'];
            $this->created_at = $userData['created_at'];
            return true;
        }
        return false;
    }

    public function updateProfile($data) {
        $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        return $stmt->execute([$data['username'], $data['email'], $this->id]);
    }

    public function changePassword($currentPassword, $newPassword) {
        // Verify current password
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPassword, $hash)) {
            throw new Exception("Current password is incorrect");
        }

        // Update password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$newHash, $this->id]);
    }

    public function getStats() {
        $stats = [
            'story_count' => 0,
            'total_words' => 0
        ];

        // Get story count and word count
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as story_count, SUM(word_count) as total_words FROM stories WHERE user_id = ?");
        $stmt->execute([$this->id]);
        $storyData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($storyData) {
            $stats['story_count'] = $storyData['story_count'] ?? 0;
            $stats['total_words'] = $storyData['total_words'] ?? 0;
        }

        return $stats;
    }

    public function getRecentStories($limit = 3) {
        $stmt = $this->pdo->prepare("SELECT * FROM stories WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$this->id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Initialize messages
$error = null;
$message = null;

// Load user data
$user = new User($pdo);
if (!$user->loadById($_SESSION['user_id'])) {
    $error = "User not found";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $data = [
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email'])
            ];

            $user->updateProfile($data);
            $_SESSION['username'] = $data['username'];
            $message = "Profile updated successfully";
            
        } elseif (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords don't match");
            }
            
            $user->changePassword($current_password, $new_password);
            $message = "Password changed successfully";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user stats and recent stories
try {
    $stats = $user->getStats();
    $recent_stories = $user->getRecentStories();
} catch (PDOException $e) {
    $error = "Failed to load user stats: " . $e->getMessage();
    $stats = ['story_count' => 0, 'total_words' => 0];
    $recent_stories = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - Tiny Tales</title>
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
    background-image: url('../assets/img/hero-bg.jpg');
    background-size: cover;
    background-attachment: fixed;
    background-position: center;
    position: relative;
    /* Add these new properties */
    background-repeat: no-repeat;
    image-rendering: -webkit-optimize-contrast; /* Sharper rendering */
    image-rendering: crisp-edges;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* Modified overlay for better clarity */
    background: linear-gradient(
        rgba(248, 240, 218, 0.7), 
    );
    z-index: -1;
    backdrop-filter: blur(0.3px); /* Micro-blur to hide compression */
}

        h1,
        h2,
        h3,
        h4 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            color: var(--forest);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: rgba(248, 240, 218, 0.95);
            color: var(--text-dark);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            backdrop-filter: blur(5px);
            border-bottom: 1px solid rgba(186, 213, 128, 0.3);
        }

        header.scrolled {
            padding: 0.8rem 0;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            background-color: rgba(248, 240, 218, 0.98);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--forest);
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo:hover {
            color: var(--blush);
        }

        .logo-icon {
            margin-right: 8px;
            color: var(--blush);
            animation: pulse 2s infinite;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 1.5rem;
        }

        nav ul li a {
            color: var(--forest);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            padding: 0.3rem 0;
            font-size: 0.95rem;
        }

        nav ul li a:hover {
            color: var(--blush);
        }

        nav ul li a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: var(--gradient-green);
            transition: width 0.3s ease;
        }

        nav ul li a:hover::after {
            width: 100%;
        }

        .user-info {
            font-weight: 500;
            color: var(--forest);
            margin-right: 1.5rem;
            background-color: var(--light-blush);
            color: var(--forest);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            border: 1px solid var(--blush);
            cursor: pointer;
            transition: var(--transition);
            margin-left: 1rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-info:hover {
            background-color: var(--blush);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--soft-glow);
        }

        .user-info::before {
            content: "\f2bd";
            font-family: 'Font Awesome 6 Free';
            font-weight: 1000;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--forest);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Profile Container */
        .profile-container {
            padding: 6rem 0 3rem;
        }

        /* Messages */
        .error {
            background: #ffebee;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            color: #c62828;
            border-left: 4px solid #c62828;
            animation: fadeInUp 0.5s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
            animation: fadeInUp 0.5s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .error .close, .message .close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
        }

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            padding: 2rem;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(186, 213, 128, 0.1) 0%, rgba(239, 171, 163, 0.1) 100%);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(239, 171, 163, 0.3);
            animation: fadeInUp 0.6s ease;
            backdrop-filter: blur(5px);
        }

        .profile-header h2 {
            font-size: 2.4rem;
            margin-bottom: 0.5rem;
            color: var(--forest);
            position: relative;
            display: inline-block;
        }

        .profile-header h2::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 3px;
            background: var(--gradient);
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .email {
            color: #555;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .member-since {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 1.5rem;
        }

        .stat {
            text-align: center;
            background: var(--light-blush);
            padding: 1.5rem 2rem;
            border-radius: 12px;
            min-width: 150px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--blush);
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease;
            backdrop-filter: blur(5px);
        }

        .stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: bold;
            color: var(--forest);
            margin-bottom: 0.3rem;
            font-family: 'Playfair Display', serif;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #555;
        }

        /* Profile Sections */
        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 768px) {
            .profile-sections {
                grid-template-columns: 1fr;
            }
        }

        .profile-section {
            background: rgba(250, 216, 212, 0.7);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--blush);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(30px);
            backdrop-filter: blur(5px);
        }

        .profile-section.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .profile-section h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .profile-section h3::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 3px;
            background: var(--gradient);
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--forest);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--forest);
            outline: none;
            box-shadow: 0 0 0 3px rgba(62, 132, 64, 0.2);
            background: white;
        }

        button[type="submit"] {
            background: var(--gradient);
            color: white;
            padding: 0.8rem 1.8rem;
            border: none;
            border-radius: 30px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            box-shadow: 0 8px 15px rgba(62, 132, 64, 0.3);
            width: 100%;
            justify-content: center;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(62, 132, 64, 0.4);
        }

        /* Stories Sections */
        .recent-stories {
            margin-bottom: 3rem;
            animation: fadeInUp 1s ease;
        }

        .recent-stories h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .recent-stories h3::after {
            content: '';
            position: absolute;
            width: 60px;
            height: 3px;
            background: var(--gradient);
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .story-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .story-card {
            background: rgba(250, 216, 212, 0.7);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--blush);
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(30px);
            backdrop-filter: blur(5px);
        }

        .story-card.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            background: rgba(250, 216, 212, 0.9);
        }

        .story-card h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--forest);
        }

        .story-card .excerpt {
            color: #555;
            margin-bottom: 1rem;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .story-meta {
            display: flex;
            justify-content: space-between;
            color: #777;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }

        .view-story {
            display: inline-block;
            color: var(--forest);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .view-story:hover {
            color: var(--blush);
            transform: translateX(5px);
        }

        /* User Avatar */
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient);
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* ================ Custom Scrollbar ================ */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-track {
    background: var(--cream);
    border-radius: 15px;
}

::-webkit-scrollbar-thumb {
    background: var(--sage);
    border-radius: 15px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--forest);
}

        /* Animations */
        @keyframes pulse {
            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            nav ul {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: rgba(248, 240, 218, 0.98);
                flex-direction: column;
                padding: 1.5rem;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            }

            nav ul.show {
                display: flex;
            }

            nav ul li {
                margin: 0.5rem 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .user-info {
                display: none;
            }

            .stats {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }

            .stat {
                width: 100%;
                max-width: 200px;
            }

            .profile-header h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .profile-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Header - Matched with stories.php -->
    <header id="header">
        <div class="container header-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open logo-icon"></i>
                Tiny Tales
            </a>
            <nav class="main-nav">
                <ul id="nav-menu">
                    <li><a href="dashboard.php">Generator</a></li>
                    <li><a href="stories.php">Your Stories</a></li>
                    <li><a href="public.php">Public Stories</a></li>
                    <li><a href="profile.php" class="active">Profile</a></li>
                    <li><a href="auth.php?logout">Logout</a></li>
                </ul>
            </nav>
            <div class="user-info">
                Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </div>
            <button class="mobile-menu-btn" id="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="container profile-container">
        <?php if (isset($error) && $error): ?>
            <div class="error">
                <p><?= htmlspecialchars($error) ?></p>
                <button class="close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($message) && $message): ?>
            <div class="message">
                <p><?= htmlspecialchars($message) ?></p>
                <button class="close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="user-avatar"><?= strtoupper(substr($user->username, 0, 1)) ?></div>
            <h2><?= htmlspecialchars($user->username) ?></h2>
            <p class="email"><?= htmlspecialchars($user->email) ?></p>
            <p class="member-since">Member since <?= date('F Y', strtotime($user->created_at)) ?></p>

            <div class="stats">
                <div class="stat">
                    <span class="stat-number"><?= $stats['story_count'] ?></span>
                    <span class="stat-label">Stories Created</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= $stats['total_words'] ?></span>
                    <span class="stat-label">Total Words</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= round($stats['total_words'] / max($stats['story_count'], 1)) ?></span>
                    <span class="stat-label">Avg. Words</span>
                </div>
            </div>
        </div>

        <div class="profile-sections">
            <section class="profile-section" id="edit-profile-section">
                <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                <form method="post">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username"
                            value="<?= htmlspecialchars($user->username) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" 
                            value="<?= htmlspecialchars($user->email) ?>" required>
                    </div>

                    <button type="submit"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </section>

            <section class="profile-section" id="change-password-section">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <form method="post">
                    <input type="hidden" name="change_password" value="1">

                    <div class="form-group">
                        <label for="current_password"><i class="fas fa-lock"></i> Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit"><i class="fas fa-key"></i> Change Password</button>
                </form>
            </section>
        </div>

        <section class="recent-stories">
            <h3><i class="fas fa-book-open"></i> Your Recent Stories</h3>
            <?php if (empty($recent_stories)): ?>
                <p style="text-align: center;">No stories yet. <a href="dashboard.php" style="color: var(--forest); font-weight: 600;">Create your first story!</a></p>
            <?php else: ?>
                <div class="story-grid">
                    <?php foreach ($recent_stories as $index => $story): ?>
                        <div class="story-card" style="transition-delay: <?= $index * 0.1 ?>s">
                            <h4><?= htmlspecialchars($story['prompt']) ?></h4>
                            <p class="excerpt"><?= nl2br(htmlspecialchars(substr($story['generated_text'], 0, 100) . '...')) ?></p>
                            <div class="story-meta">
                                <span><i class="fas fa-ruler-combined"></i> <?= $story['word_count'] ?> words</span>
                                <span><i class="far fa-calendar-alt"></i> <?= date('M j', strtotime($story['created_at'])) ?></span>
                            </div>
                            <a href="stories.php?view=<?= $story['id'] ?>" class="view-story"><i class="fas fa-book-open"></i> View Story</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const navMenu = document.getElementById('nav-menu');

            mobileMenuBtn.addEventListener('click', () => {
                navMenu.classList.toggle('show');
                mobileMenuBtn.innerHTML = navMenu.classList.contains('show') ?
                    '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            });

            // Header scroll effect
            const header = document.getElementById('header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // Close error/message notifications
            document.querySelectorAll('.close').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            });

            // Animation on scroll
            const animateOnScroll = () => {
                const elements = document.querySelectorAll('.profile-section, .story-card');

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animated');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });

                elements.forEach(element => {
                    observer.observe(element);
                });
            };

            animateOnScroll();
        });
    </script>
</body>
</html>