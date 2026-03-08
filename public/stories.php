<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Handle actions
if (isset($_GET['delete'])) {
    // Soft delete story
    try {
        $stmt = $pdo->prepare("UPDATE stories SET is_deleted = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
        $_SESSION['message'] = "Story deleted successfully";
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        $_SESSION['error'] = "Could not delete story";
    }
    header("Location: stories.php");
    exit();
}

if (isset($_GET['toggle_public'])) {
    // Toggle public status
    try {
        // First get current status
        $stmt = $pdo->prepare("SELECT is_public FROM stories WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['toggle_public'], $_SESSION['user_id']]);
        $story = $stmt->fetch();

        if ($story) {
            $new_status = $story['is_public'] ? 0 : 1; // Explicitly set to 0 or 1
            $stmt = $pdo->prepare("UPDATE stories SET is_public = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_status, $_GET['toggle_public'], $_SESSION['user_id']]);

            $_SESSION['message'] = $new_status ? "Story is now public" : "Story is now private";
        } else {
            $_SESSION['error'] = "Story not found or you don't have permission";
        }
    } catch (PDOException $e) {
        error_log("Public toggle error: " . $e->getMessage());
        $_SESSION['error'] = "Could not update story visibility";
    }
    header("Location: stories.php");
    exit();
}

if (isset($_GET['export'])) {
    // Handle export (simplified example)
    $type = $_GET['type'] ?? 'txt';
    $story_id = (int)$_GET['export'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ? AND user_id = ?");
        $stmt->execute([$story_id, $_SESSION['user_id']]);
        $story = $stmt->fetch();
        
        if ($story) {
            $filename = "story_{$story_id}_" . date('Ymd_His') . ".$type";
            $filepath = EXPORT_PATH . $filename;
            
            if ($type === 'txt') {
                $content = "Title: {$story['prompt']}\n\n{$story['generated_text']}";
                file_put_contents($filepath, $content);
            } elseif ($type === 'pdf') {
                // In a real app, you'd use a PDF library here
                $content = "<h1>{$story['prompt']}</h1><p>{$story['generated_text']}</p>";
                file_put_contents($filepath, $content);
            }
            
            // Record export
            $stmt = $pdo->prepare("INSERT INTO story_exports 
                (story_id, user_id, export_type, export_path) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([$story_id, $_SESSION['user_id'], $type, $filename]);
            
            // Offer download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($filepath);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['error'] = "Could not export story";
    }
}

// Get search parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre_filter = isset($_GET['genre']) ? $_GET['genre'] : '';

// Build the SQL query dynamically based on filters
$sql = "SELECT * FROM stories WHERE user_id = ? AND is_deleted = FALSE";
$params = [$_SESSION['user_id']];

// Add search term condition if provided
if (!empty($search_term)) {
    $sql .= " AND (LOWER(prompt) LIKE ? OR LOWER(generated_text) LIKE ?)";
    $search_param = '%' . strtolower($search_term) . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add genre filter if provided
if (!empty($genre_filter)) {
    $sql .= " AND genre = ?";
    $params[] = $genre_filter;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Could not load stories";
    $stories = [];
}

// Get available genres for filter
$genres = ['Fantasy', 'Sci-Fi', 'Mystery', 'Romance', 'Horror', 'Adventure', 'Comedy'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Your Stories - Tiny Tales</title>
    <link href="../assets/style.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <li><a href="stories.php" class="active">Your Stories</a></li>
                    <li><a href="public.php">Public Stories</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="auth.php?logout">Logout</a></li>
                </ul>
            </nav>
            <div class="user-info">
                Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <p><?= htmlspecialchars($_SESSION['message']) ?></p>
                <button class="close">&times;</button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <p><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button class="close">&times;</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="filter-card">
            <h2><i class="fas fa-book-open"></i> Your Stories</h2>

            <form method="get" class="filter-form">
                <div class="filter-row">
                    <div class="form-group">
                        <input type="text" id="search" name="search" placeholder="Search your stories..."
                            value="<?= htmlspecialchars($search_term) ?>">
                    </div>

                    <div class="form-group">
                        <select id="genre" name="genre">
                            <option value="">All Genres</option>
                            <?php foreach ($genres as $genre): ?>
                                <option value="<?= htmlspecialchars($genre) ?>" <?= $genre_filter === $genre ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($genre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($search_term || $genre_filter): ?>
                <div class="clear-filters">
                    <a href="stories.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear all filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="stories-grid">
            <?php if (empty($stories)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>No stories found</h3>
                    <p><?php if (!empty($search_term) || !empty($genre_filter)): ?>
                        We couldn't find any stories matching your criteria.
                    <?php else: ?>
                        You haven't created any stories yet.
                    <?php endif; ?></p>
                    <a href="dashboard.php" class="btn">
                        <i class="fas fa-magic"></i> Generate a new story
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($stories as $story): ?>
                    <div class="story-card">
                        <div class="story-header">
                            <h3><?= htmlspecialchars($story['prompt']) ?></h3>
                        </div>

                        <div class="story-content">
                            <p><?= nl2br(htmlspecialchars(substr($story['generated_text'], 0, 250) . (strlen($story['generated_text']) > 250 ? '...' : ''))) ?>
                            </p>
                        </div>

                        <div class="story-meta">
                            <?php if ($story['genre']): ?>
                                <span class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <?= htmlspecialchars($story['genre']) ?>
                                </span>
                            <?php endif; ?>

                            <span class="meta-item">
                                <i class="fas fa-clock"></i>
                                <?= $story['reading_time'] ?> min read
                            </span>

                            <span class="meta-item">
                                <i class="fas fa-feather-alt"></i>
                                <?= $story['word_count'] ?> words
                            </span>

                            <span class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('M j, Y', strtotime($story['created_at'])) ?>
                            </span>

                            <span class="meta-item">
                                <i class="fas fa-eye"></i>
                                <?= $story['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                        </div>

                        <div class="story-actions">
                            <a href="stories.php?delete=<?= $story['id'] ?>" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to delete this story?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                            <a href="stories.php?toggle_public=<?= $story['id'] ?>" class="btn <?= $story['is_public'] ? 'btn-secondary' : '' ?>">
                                <i class="fas fa-<?= $story['is_public'] ? 'lock' : 'globe' ?>"></i>
                                <?= $story['is_public'] ? 'Make Private' : 'Make Public' ?>
                            </a>
                            <div class="dropdown">
                                <button class="btn">
                                    <i class="fas fa-download"></i> Export ▼
                                </button>
                                <div class="dropdown-content">
                                    <a href="stories.php?export=<?= $story['id'] ?>&type=txt"><i class="fas fa-file-alt"></i> Text</a>
                                    <a href="stories.php?export=<?= $story['id'] ?>&type=pdf"><i class="fas fa-file-pdf"></i> PDF</a>
                                    <a href="stories.php?export=<?= $story['id'] ?>&type=html"><i class="fas fa-code"></i> HTML</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn').addEventListener('click', function () {
            document.querySelector('.main-nav ul').classList.toggle('show');
        });

        // Close error messages
        document.querySelectorAll('.error .close, .message .close').forEach(btn => {
            btn.addEventListener('click', function () {
                this.parentElement.style.display = 'none';
            });
        });

        // Reset form button functionality
        document.querySelector('button[type="reset"]')?.addEventListener('click', function () {
            // Clear all filter inputs
            document.getElementById('search').value = '';
            document.getElementById('genre').value = '';
        });

        // Focus search input if it has content
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
        });

        // Dropdown functionality
        document.querySelectorAll('.dropdown button').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                document.querySelectorAll('.dropdown-content').forEach(d => {
                    if (d !== dropdown) d.style.display = 'none';
                });
                dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
            });
        });

        // Close dropdowns when clicking elsewhere
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-content').forEach(d => {
                d.style.display = 'none';
            });
        });
    </script>
</body>
</html>