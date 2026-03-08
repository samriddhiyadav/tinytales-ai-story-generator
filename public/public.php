<?php
require_once __DIR__ . '/../includes/config.php';

// Initialize variables
$stories = [];
$search_term = '';
$genre_filter = '';
$author_filter = '';
$error = null;

// Get filter parameters
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre_filter = isset($_GET['genre']) ? $_GET['genre'] : '';
$author_filter = isset($_GET['author']) ? (int) $_GET['author'] : '';

// Get available genres and authors for filters
try {
    $genres = $pdo->query("SELECT DISTINCT genre FROM stories WHERE genre IS NOT NULL AND is_public = TRUE")->fetchAll(PDO::FETCH_COLUMN);
    $authors = $pdo->query("
        SELECT u.id, u.username 
        FROM users u
        JOIN stories s ON u.id = s.user_id
        WHERE s.is_public = TRUE
        GROUP BY u.id
        ORDER BY u.username
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not load filter options: " . $e->getMessage();
}

// Get public stories with filtering
try {
    $query = "
        SELECT s.*, u.username as author_name, 
               COUNT(f.id) as favorite_count,
               COUNT(c.id) as comment_count
        FROM stories s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN favorites f ON s.id = f.story_id
        LEFT JOIN comments c ON s.id = c.story_id
        WHERE s.is_public = TRUE AND s.is_deleted = FALSE
    ";

    $params = [];
    $conditions = [];

    // Add search condition
    if ($search_term) {
        $conditions[] = "(LOWER(s.prompt) LIKE ? OR LOWER(s.generated_text) LIKE ?)";
        $params[] = "%" . strtolower($search_term) . "%";
        $params[] = "%" . strtolower($search_term) . "%";
    }

    // Add genre filter
    if ($genre_filter) {
        $conditions[] = "s.genre = ?";
        $params[] = $genre_filter;
    }

    // Add author filter
    if ($author_filter) {
        $conditions[] = "s.user_id = ?";
        $params[] = $author_filter;
    }

    // Combine conditions
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    // Complete query
    $query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Could not load stories: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Public Stories - Tiny Tales</title>
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
                    <li><a href="stories.php">Your Stories</a></li>
                    <li><a href="public.php" class="active">Public Stories</a></li>
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
        <?php if ($error): ?>
            <div class="error">
                <p><?= htmlspecialchars($error) ?></p>
                <button class="close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="filter-card">
            <h2><i class="fas fa-search"></i> Discover Stories</h2>

            <form method="get" class="filter-form">
                <div class="filter-row">
                    <div class="form-group">
                        <input type="text" id="search" name="search" placeholder="Search stories by content..."
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

                    <div class="form-group">
                        <select id="author" name="author">
                            <option value="">All Authors</option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?= (int) $author['id'] ?>" <?= $author_filter === $author['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($author['username']) ?>
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

            <?php if ($search_term || $genre_filter || $author_filter): ?>
                <div class="clear-filters">
                    <a href="public.php" class="btn btn-secondary">
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
                    <h3>No public stories found</h3>
                    <p>We couldn't find any stories matching your criteria.</p>
                    <?php if ($search_term || $genre_filter || $author_filter): ?>
                        <a href="public.php" class="btn">
                            <i class="fas fa-book"></i> View all public stories
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($stories as $story): ?>
                    <div class="story-card">
                        <div class="story-header">
                            <h3><?= htmlspecialchars($story['title']) ?></h3>
                            <div class="story-author">
                                <i class="fas fa-user-circle"></i>
                                <?= htmlspecialchars($story['author_name']) ?>
                            </div>
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
                                <i class="fas fa-heart"></i>
                                <?= $story['favorite_count'] ?> favorites
                            </span>

                            <span class="meta-item">
                                <i class="fas fa-comment"></i>
                                <?= $story['comment_count'] ?> comments
                            </span>

                            <span class="meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <?= date('M j, Y', strtotime($story['created_at'])) ?>
                            </span>
                        </div>

                        <div class="story-actions">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="story.php?id=<?= $story['id'] ?>" class="btn">
                                    <i class="fas fa-eye"></i> Read Full Story
                                </a>
                            <?php else: ?>
                                <a href="auth.php" class="btn">
                                    <i class="fas fa-sign-in-alt"></i> Login to View
                                </a>
                            <?php endif; ?>
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
        document.querySelectorAll('.error .close').forEach(btn => {
            btn.addEventListener('click', function () {
                this.parentElement.style.display = 'none';
            });
        });

        // Reset form button functionality
        document.querySelector('button[type="reset"]')?.addEventListener('click', function () {
            // Clear all filter inputs
            document.getElementById('search').value = '';
            document.getElementById('genre').value = '';
            document.getElementById('author').value = '';
        });

        // Focus search input if it has content
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                searchInput.focus();
                searchInput.select();
            }
        });
    </script>
</body>

</html>