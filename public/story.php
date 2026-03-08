<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Initialize variables
$error = null;
$message = null;
$story = null;
$genres = ['Fantasy', 'Sci-Fi', 'Mystery', 'Romance', 'Horror', 'Adventure', 'Comedy'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_story'])) {
            // Handle story creation/update
            $story_id = isset($_POST['story_id']) ? (int) $_POST['story_id'] : null;
            $title = trim($_POST['title']);
            $prompt = trim($_POST['prompt']);
            $content = trim($_POST['content']);
            $word_count = (int) $_POST['word_count'];
            $genre = $_POST['genre'] ?: null;
            $is_public = isset($_POST['is_public']) ? 1 : 0;
            $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;

            // Calculate reading time (200 words per minute)
            $reading_time = max(1, ceil($word_count / 200));

            if ($story_id) {
                // Update existing story
                $stmt = $pdo->prepare("UPDATE stories SET 
                    title = ?, prompt = ?, generated_text = ?, word_count = ?, 
                    genre = ?, reading_time = ?, is_public = ?, allow_comments = ?,
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND user_id = ?");
                $stmt->execute([
                    $title,
                    $prompt,
                    $content,
                    $word_count,
                    $genre,
                    $reading_time,
                    $is_public,
                    $allow_comments,
                    $story_id,
                    $_SESSION['user_id']
                ]);
                $message = "Story updated successfully!";
            } else {
                // Create new story
                $stmt = $pdo->prepare("INSERT INTO stories 
                    (user_id, title, prompt, generated_text, word_count, 
                    genre, reading_time, is_public, allow_comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $prompt,
                    $content,
                    $word_count,
                    $genre,
                    $reading_time,
                    $is_public,
                    $allow_comments
                ]);
                $story_id = $pdo->lastInsertId();
                $message = "Story saved successfully!";
            }

            // Handle tags
            if (isset($_POST['tags'])) {
                // Remove existing tags
                $stmt = $pdo->prepare("DELETE FROM story_tags WHERE story_id = ?");
                $stmt->execute([$story_id]);

                // Add new tags
                $tags = array_unique(array_map('trim', explode(',', $_POST['tags'])));
                foreach ($tags as $tag_name) {
                    if (empty($tag_name))
                        continue;

                    // Get or create tag
                    $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
                    $stmt->execute([$tag_name]);

                    $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                    $stmt->execute([$tag_name]);
                    $tag_id = $stmt->fetchColumn();

                    // Link tag to story
                    $stmt = $pdo->prepare("INSERT IGNORE INTO story_tags (story_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$story_id, $tag_id]);
                }
            }

            // Redirect to avoid form resubmission
            $_SESSION['message'] = $message;
            header("Location: story.php?id=$story_id");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "An error occurred while saving your story";
    }
} elseif (isset($_GET['id'])) {
    // View/edit existing story
    $story_id = (int) $_GET['id'];

    try {
        // Get story
        $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ? AND user_id = ?");
        $stmt->execute([$story_id, $_SESSION['user_id']]);
        $story = $stmt->fetch();

        if (!$story) {
            throw new Exception("Story not found");
        }

        // Get tags for this story
        $stmt = $pdo->prepare("SELECT t.name FROM tags t
            JOIN story_tags st ON t.id = st.tag_id
            WHERE st.story_id = ?");
        $stmt->execute([$story_id]);
        $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $story['tags'] = implode(', ', $tags);
    } catch (Exception $e) {
        error_log("Story load error: " . $e->getMessage());
        $error = "Could not load story";
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE stories SET is_deleted = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
        $_SESSION['message'] = "Story deleted successfully";
        header("Location: stories.php");
        exit();
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        $error = "Could not delete story";
    }
}

// Get message from session if redirected
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($story) ? "Edit: " . htmlspecialchars($story['title']) : "New Story" ?> - Tiny Tales</title>
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
            background-repeat: no-repeat;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(248, 240, 218, 0.7), rgba(248, 240, 218, 0.7));
            z-index: -1;
            backdrop-filter: blur(0.3px);
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
            max-width: 900px;
            margin: 0 auto;
            padding: 6rem 20px 3rem;
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
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
            transition: all 0.3s;
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
            background: var(--gradient);
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
            transition: all 0.3s;
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

        /* Story Form */
        .story-form {
            background: rgba(250, 216, 212, 0.7);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--blush);
            backdrop-filter: blur(5px);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: flex;
            gap: 1.5rem;
        }

        .form-row .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--forest);
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.8);
        }

        textarea {
            min-height: 200px;
            resize: vertical;
        }

        #content {
            min-height: 300px;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--forest);
            outline: none;
            box-shadow: 0 0 0 3px rgba(62, 132, 64, 0.2);
            background: white;
        }

        .word-count {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #555;
            font-style: italic;
        }

        .tag-input {
            width: 100%;
        }

        .tag-hint {
            font-size: 0.85rem;
            color: #777;
            margin-top: 0.5rem;
        }

        .checkbox-group {
            margin: 1rem 0;
            display: flex;
            align-items: center;
        }

        .checkbox-group input {
            margin-right: 0.5rem;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            color: var(--forest);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.8rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
            box-shadow: 0 8px 15px rgba(62, 132, 64, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(62, 132, 64, 0.4);
        }

        .btn-secondary {
            background-color: var(--light-blush);
            color: var(--forest);
            border: 1px solid var(--blush);
        }

        .btn-secondary:hover {
            background-color: var(--blush);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--soft-glow);
        }

        .btn-danger {
            background-color: var(--blush);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333b9;
            transform: translateY(-2px);
        }

        /* Messages */
        .message,
        .error {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeInUp 0.5s ease;
        }

        .message {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
        }

        /* Styled Select Box */
        .styled-select {
            position: relative;
            width: 100%;
        }

        .styled-select select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-dark);
            cursor: pointer;
            padding-right: 2.5rem;
        }

        .styled-select select:focus {
            border-color: var(--forest);
            outline: none;
            box-shadow: 0 0 0 3px rgba(62, 132, 64, 0.2);
            background: white;
        }

        .select-arrow {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--forest);
            transition: all 0.3s;
        }

        .styled-select:hover .select-arrow {
            color: var(--blush);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .checkbox-group input {
            margin-right: 0.8rem;
            width: 18px;
            height: 18px;
            accent-color: var(--forest);
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-dark);
        }

        .tag-hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
            line-height: 1.4;
        }

        /* Custom Scrollbar */
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
                transform: translateY(20px);
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
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .user-info {
                display: none;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
                gap: 0.8rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 5rem 15px 2rem;
            }

            .story-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <header id="header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open logo-icon"></i>
                Tiny Tales
            </a>
            <nav class="main-nav">
                <ul id="nav-menu">
                    <li><a href="dashboard.php">Generator</a></li>
                    <li><a href="stories.php">Your Stories</a></li>
                    <li><a href="public.php">Public Stories</a></li>
                    <li><a href="profile.php">Profile</a></li>
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

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error">
                <p><?= htmlspecialchars($error) ?></p>
                <button class="close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($message)): ?>
            <div class="message">
                <p><?= htmlspecialchars($message) ?></p>
                <button class="close">&times;</button>
            </div>
        <?php endif; ?>

        <form method="post" class="story-form">
            <input type="hidden" name="story_id" value="<?= $story['id'] ?? '' ?>">

            <div class="form-group">
                <label for="title"><i class="fas fa-heading"></i> Title</label>
                <input type="text" id="title" name="title" required
                    value="<?= htmlspecialchars($story['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="prompt"><i class="fas fa-lightbulb"></i> Prompt</label>
                <textarea id="prompt" name="prompt" required><?=
                    htmlspecialchars($story['prompt'] ?? '')
                    ?></textarea>
            </div>

            <div class="form-group">
                <label for="content"><i class="fas fa-book"></i> Story Content</label>
                <textarea id="content" name="content" required><?=
                    htmlspecialchars($story['generated_text'] ?? '')
                    ?></textarea>
                <div class="word-count" id="word-count-display">
                    <?php if (isset($story)): ?>
                        <?= $story['word_count'] ?> words (<?= $story['reading_time'] ?> min read)
                    <?php else: ?>
                        0 words
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="word_count"><i class="fas fa-ruler-combined"></i> Word Count</label>
                    <input type="number" id="word_count" name="word_count" min="<?= MIN_WORD_COUNT ?>"
                        max="<?= MAX_WORD_COUNT ?>"
                        value="<?= $story['word_count'] ?? $_SESSION['prefs']['default_word_count'] ?>">
                </div>

                <div class="form-group">
                    <label for="genre"><i class="fas fa-tags"></i> Genre</label>
                    <select id="genre" name="genre">
                        <option value="">Select Genre</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?= $genre ?>" <?=
                                  (isset($story) && $story['genre'] === $genre) ? 'selected' : ''
                                  ?>><?= $genre ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="tags"><i class="fas fa-tag"></i> Tags (comma separated)</label>
                <input type="text" id="tags" name="tags" class="tag-input"
                    value="<?= htmlspecialchars($story['tags'] ?? '') ?>">
                <div class="tag-hint">Example: fantasy, adventure, magic</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="is_public"><i class="fas fa-globe"></i> Visibility</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_public" name="is_public" value="1" <?= (isset($story) && $story['is_public']) ? 'checked' : '' ?>>
                        <label for="is_public">Make this story public</label>
                    </div>
                    <p class="tag-hint">Public stories can be seen by other users in the Public Stories section</p>
                </div>

                <div class="form-group">
                    <label for="allow_comments"><i class="fas fa-comments"></i> Comments</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_comments" name="allow_comments" value="1" <?= (!isset($story) || $story['allow_comments']) ? 'checked' : '' ?>>
                        <label for="allow_comments">Allow comments</label>
                    </div>
                    <p class="tag-hint">Let other users comment on your story</p>
                </div>
            </div>

            <div class="form-actions">
                <?php if (isset($story)): ?>
                    <a href="story.php?delete=<?= $story['id'] ?>" class="btn btn-danger"
                        onclick="return confirm('Are you sure you want to delete this story?')">
                        <i class="fas fa-trash"></i> Delete Story
                    </a>
                <?php else: ?>
                    <a href="stories.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>

                <button type="submit" name="save_story" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?= isset($story) ? 'Update Story' : 'Save Story' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Calculate word count
        const contentTextarea = document.getElementById('content');
        const wordCountDisplay = document.getElementById('word-count-display');

        function updateWordCount() {
            const text = contentTextarea.value;
            const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
            const readingTime = Math.max(1, Math.ceil(wordCount / 200));

            wordCountDisplay.textContent = `${wordCount} words (${readingTime} min read)`;

            // Update word count field if it exists
            const wordCountInput = document.getElementById('word_count');
            if (wordCountInput) {
                wordCountInput.value = wordCount;
            }
        }

        contentTextarea.addEventListener('input', updateWordCount);

        // Initialize word count on page load
        document.addEventListener('DOMContentLoaded', updateWordCount);

        // Tag input suggestions
        const tagInput = document.getElementById('tags');
        if (tagInput) {
            tagInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const cursorPos = this.selectionStart;
                    const textBefore = this.value.substring(0, cursorPos);
                    const textAfter = this.value.substring(cursorPos);

                    // Add comma if not at end
                    if (cursorPos < this.value.length && !textBefore.endsWith(',')) {
                        this.value = textBefore + ',' + textAfter;
                        this.selectionStart = this.selectionEnd = cursorPos + 1;
                    }
                }
            });
        }

        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function () {
            const navMenu = document.querySelector('.main-nav ul');
            const menuBtn = document.getElementById('mobile-menu-btn');
            navMenu.classList.toggle('show');
            menuBtn.innerHTML = navMenu.classList.contains('show') ?
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

        // Close error messages
        document.querySelectorAll('.error .close, .message .close').forEach(btn => {
            btn.addEventListener('click', function () {
                this.parentElement.style.display = 'none';
            });
        });
    </script>
</body>

</html>