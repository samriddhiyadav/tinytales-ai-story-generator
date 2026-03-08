<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Initialize variables
$error = null;
$generated_text = null;
$recent_stories = [];

// Get user preferences
$user_preferences = [
    'word_count' => 100,
    'genre' => null
];

try {
    $stmt = $pdo->prepare("SELECT default_word_count, default_genre FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $prefs = $stmt->fetch();
    if ($prefs) {
        $user_preferences = $prefs;
    }
} catch (PDOException $e) {
    // Preferences are optional, so we'll just log the error
    error_log("Could not load user preferences: " . $e->getMessage());
}

// Get recent stories from database with more details
try {
    $stmt = $pdo->prepare("
        SELECT s.*, COUNT(f.id) AS favorite_count, COUNT(c.id) AS comment_count
        FROM stories s
        LEFT JOIN favorites f ON s.id = f.story_id
        LEFT JOIN comments c ON s.id = c.story_id
        WHERE s.user_id = ? AND s.is_deleted = FALSE
        GROUP BY s.id
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_stories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Could not load story history: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prompt'])) {
    $prompt = isset($_POST['prompt']) ? trim((string) $_POST['prompt']) : '';
    $word_count = isset($_POST['word_count']) ? (int) $_POST['word_count'] : $user_preferences['default_word_count'];
    $genre = isset($_POST['genre']) ? $_POST['genre'] : $user_preferences['default_genre'];

    if (!empty($prompt)) {
        // Use the verified Python path
        $pythonPath = 'C:\\Users\\Samriddhi\\AppData\\Local\\Programs\\Python\\Python313\\python.exe';
        $generateScript = __DIR__ . '\\..\\generate.py';

        // Pass word count and genre to the Python script
        $command = '"' . $pythonPath . '" "' . $generateScript . '" ' .
            escapeshellarg($prompt) . ' ' . $word_count . ' ' . escapeshellarg($genre);

        // Execute and capture output in real-time
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Close stdin (we don't need to write anything)
            fclose($pipes[0]);

            // Read output
            $generated_text = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            // Close pipes
            fclose($pipes[1]);
            fclose($pipes[2]);

            // Close process
            $return_value = proc_close($process);

            // Debug logging
            file_put_contents('../log/debug.log', date('Y-m-d H:i:s') . " - Command: $command\nOutput: $generated_text\nError: $stderr\n", FILE_APPEND);

            if ($return_value !== 0 || empty(trim($generated_text))) {
                $error = "Story generation failed. " . ($stderr ?: "No output from Python script");
            } else {
                // Calculate reading time (assuming 200 words per minute)
                $reading_time = max(1, ceil(str_word_count($generated_text) / 200));

                // Save to database with all metadata
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO stories 
                        (user_id, title, prompt, generated_text, word_count, genre, reading_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    // Create a simple title from the prompt
                    $title = substr($prompt, 0, 50) . (strlen($prompt) > 50 ? '...' : '');

                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $prompt,
                        $generated_text,
                        str_word_count($generated_text),
                        $genre,
                        $reading_time
                    ]);

                    // Get the new story ID
                    $story_id = $pdo->lastInsertId();

                    // Add to reading history
                    $stmt = $pdo->prepare("
                        INSERT INTO reading_history (user_id, story_id) 
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE last_read = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$_SESSION['user_id'], $story_id]);

                    // Refresh recent stories
                    $stmt = $pdo->prepare("
                        SELECT s.*, COUNT(f.id) AS favorite_count, COUNT(c.id) AS comment_count
                        FROM stories s
                        LEFT JOIN favorites f ON s.id = f.story_id
                        LEFT JOIN comments c ON s.id = c.story_id
                        WHERE s.user_id = ? AND s.is_deleted = FALSE
                        GROUP BY s.id
                        ORDER BY s.created_at DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $recent_stories = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $error = "Could not start Python process";
        }
    } else {
        $error = "Please enter a prompt";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Tiny Tales</title>
    <link href="../assets/style.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header>
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-book-open logo-icon"></i>
                Tiny Tales
            </a>
            <nav class="main-nav">
                <ul>
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
        </div>
    </header>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="story-form">
            <div class="form-group">
                <label for="prompt">Story Prompt</label>
                <textarea name="prompt" id="prompt" placeholder="Enter your story prompt..." required><?=
                    isset($_POST['prompt']) ? htmlspecialchars($_POST['prompt']) : ''
                    ?></textarea>
            </div>

            <div class="form-group">
                <label for="word_count">Word Count</label>
                <input type="number" name="word_count" id="word_count"
                    value="<?= isset($_POST['word_count']) ? htmlspecialchars($_POST['word_count']) : $user_preferences['default_word_count'] ?>"
                    min="50" max="2000">
            </div>

            <div class="form-group">
                <label for="genre">Genre (optional)</label>
                <select name="genre" id="genre">
                    <option value="">Select a genre...</option>
                    <option value="Fantasy" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Fantasy') || $user_preferences['default_genre'] === 'Fantasy' ? 'selected' : '' ?>>Fantasy</option>
                    <option value="Sci-Fi" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Sci-Fi') || $user_preferences['default_genre'] === 'Sci-Fi' ? 'selected' : '' ?>>Sci-Fi</option>
                    <option value="Mystery" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Mystery') || $user_preferences['default_genre'] === 'Mystery' ? 'selected' : '' ?>>Mystery</option>
                    <option value="Romance" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Romance') || $user_preferences['default_genre'] === 'Romance' ? 'selected' : '' ?>>Romance</option>
                    <option value="Horror" <?= (isset($_POST['genre']) && $_POST['genre'] === 'Horror') || $user_preferences['default_genre'] === 'Horror' ? 'selected' : '' ?>>Horror</option>
                </select>
            </div>

            <button type="submit">Generate Story</button>
            <div id="loading" class="loading">Generating your story... Please wait.</div>
        </form>

        <div id="story-output">
            <?php if (isset($generated_text) && !isset($error)): ?>
                <div class="story-card">
                    <h3>Your New Story</h3>
                    <p><?= nl2br(htmlspecialchars($generated_text)) ?></p>
                    <div class="story-meta">
                        <span class="timestamp">Generated just now</span>
                        <span><?= str_word_count($generated_text) ?> words</span>
                        <span><?= max(1, ceil(str_word_count($generated_text) / 200)) ?> min read</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($recent_stories)): ?>
                <h2>Your Recent Stories</h2>
                <?php foreach ($recent_stories as $story): ?>
                    <div class="story-card">
                        <h3><?= htmlspecialchars($story['title']) ?></h3>
                        <p><?= nl2br(htmlspecialchars(substr($story['generated_text'], 0, 200) . (strlen($story['generated_text'])) > 200 ? '...' : '')) ?>
                        </p>
                        <div class="story-meta">
                            <span class="timestamp">
                                <?= date('M j, Y g:i a', strtotime($story['created_at'])) ?>
                            </span>
                            <span><?= $story['word_count'] ?> words</span>
                            <span><?= $story['reading_time'] ?> min read</span>
                            <span><?= $story['favorite_count'] ?> favorites</span>
                            <span><?= $story['comment_count'] ?> comments</span>
                        </div>
                        <a href="story.php?id=<?= $story['id'] ?>">Read full story</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('story-form').addEventListener('submit', function () {
            document.getElementById('loading').style.display = 'block';
        });
    </script>
</body>

</html>