<?php
require_once __DIR__ . '/includes/config.php';

// Check if user is logged in to show appropriate navigation
$logged_in = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiny Tales - AI-Powered Story Generator</title>
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
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
            position: relative;
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
            transition: all 0.3s ease;
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

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--forest);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            height: 90vh;
            min-height: 700px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-top: 60px;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
        }

        .hero h1 {
            font-size: 3.2rem;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            animation: fadeInUp 1s ease;
            color: var(--forest);
        }

        .hero p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease 0.2s forwards;
            opacity: 0;
            font-weight: 400;
            color: #555;
        }

        .cta-button {
            padding: 0.8rem 2rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            animation: fadeInUp 1s ease 0.4s forwards;
            opacity: 0;
            background: var(--gradient);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            box-shadow: 0 8px 15px rgba(62, 132, 64, 0.3);
        }

        /* Features Section */
        .features {
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
            background-color: var(--cream);
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.4rem;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .section-title h2:after {
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

        .section-title p {
            color: #555;
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .feature-card {
            background: var(--light-blush);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--blush);
            opacity: 0;
            transform: translateY(30px);
        }

        .feature-card.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .feature-card p {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.6;
        }

        /* How It Works Section */
        .how-it-works {
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .how-it-works-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .step {
            background-color: var(--cream);
            border-radius: 12px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(186, 213, 128, 0.3);
            opacity: 0;
            transform: translateY(30px);
        }

        .step.animated {
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .step-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--blush);
            opacity: 0.5;
            margin-bottom: 1rem;
            font-family: 'Playfair Display', serif;
        }

        .step-content h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .step-content p {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.6;
        }

        /* Story Showcase */
        .story-showcase {
            padding: 5rem 0;
            background-color: var(--cream);
            position: relative;
        }

        .story-card {
            background: var(--light-blush);
            border-radius: 12px;
            padding: 2rem;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--blush);
        }

        .story-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--forest);
        }

        .story-card p {
            margin-bottom: 1rem;
            color: #555;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .story-card em {
            color: var(--blush);
            font-style: normal;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Testimonials */
        .testimonials {
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .testimonials-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        .testimonials-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .testimonial {
            background: var(--cream);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(186, 213, 128, 0.3);
            position: relative;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.3s ease;
        }

        .testimonial.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .testimonial::after {
            content: '"';
            position: absolute;
            top: 20px;
            right: 20px;
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            color: var(--blush);
            opacity: 0.1;
            line-height: 1;
            z-index: 0;
        }

        .testimonial-content {
            position: relative;
            z-index: 1;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 1rem;
        }

        .author {
            color: var(--forest);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Footer */
        footer {
            background-color: var(--forest);
            color: var(--cream);
            padding: 4rem 0 2rem;
            position: relative;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-about h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .footer-about p {
            font-weight: 300;
            line-height: 1.6;
            margin-bottom: 1rem;
            opacity: 0.8;
            font-size: 0.95rem;
        }

        .footer-social {
            display: flex;
            gap: 1rem;
        }

        .social-icon {
            color: var(--cream);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .footer-copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(248, 240, 218, 0.2);
            font-size: 0.85rem;
            opacity: 0.7;
            font-weight: 300;
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
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 2.8rem;
            }

            .section-title h2 {
                font-size: 2.2rem;
            }
        }

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

            .hero {
                height: auto;
                padding: 6rem 0;
                text-align: center;
            }

            .hero h1 {
                font-size: 2.4rem;
            }

            .steps {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .logo {
                font-size: 1.6rem;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .section-title h2 {
                font-size: 1.8rem;
            }

            .feature-card,
            .testimonial,
            .story-card {
                padding: 1.5rem;
            }
        }

        /* Overlay styles */
        .hero::before,
        .how-it-works::before,
        .testimonials::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .hero::before {
            background-color: rgba(248, 240, 218, 0);
        }

        .how-it-works::before,
        .testimonials::before {
            background-color: rgba(227, 193, 193, 0.48);
        }

        .hero-content,
        .how-it-works .container,
        .testimonials .container {
            position: relative;
            z-index: 2;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header id="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-book-open logo-icon"></i>
                Tiny Tales
            </a>
            <nav class="main-nav">
                <ul id="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <?php if ($logged_in): ?>
                        <li><a href="public/dashboard.php">Generator</a></li>
                        <li><a href="public/stories.php">Your Stories</a></li>
                        <li><a href="public/profile.php">Profile</a></li>
                        <li><a href="public/auth.php?logout">Logout</a></li>
                    <?php else: ?>
                        <li><a href="public/auth.php">Login/Signup</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <button class="mobile-menu-btn" id="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <img src="assets/img/hero-bg.jpg" alt="Open book with magical light coming out" class="hero-bg">
        <div class="container hero-content">
            <h1>Unleash Your Creativity with AI</h1>
            <p>Tiny Tales transforms your ideas into captivating stories with the power of AI. Whether you're a writer
                looking for inspiration, a teacher creating educational content, or just want to have fun, our platform
                helps you create unique stories in seconds.</p>
            <?php if ($logged_in): ?>
                <a href="public/dashboard.php" class="cta-button">Start Creating</a>
            <?php else: ?>
                <a href="public/auth.php" class="cta-button">Get Started - It's Free</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Tiny Tales?</h2>
                <p>Discover the power of AI-assisted storytelling with our innovative platform</p>
            </div>

            <div class="features-container">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-magic"></i>
                    </div>
                    <h3>AI-Powered Generation</h3>
                    <p>Our advanced AI understands your prompts and generates coherent, creative stories tailored to
                        your specifications. No more writer's block!</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <h3>Customizable Output</h3>
                    <p>Control the length, genre, and style of your stories to match exactly what you're looking for.
                        Perfect for any audience or purpose.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Story Management</h3>
                    <p>Save, organize, and revisit your favorite creations whenever you want. Export them in multiple
                        formats for sharing or publishing.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <img src="assets/img/how-it-works-bg.jpg" alt="Person typing on laptop with notebook" class="how-it-works-bg">
        <div class="container">
            <div class="section-title">
                <h2>How Tiny Tales Works</h2>
                <p>Creating amazing stories has never been easier. Here's how you can get started:</p>
            </div>

            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Enter Your Prompt</h3>
                        <p>Start with a simple idea, sentence, or even just a few words. Our AI will use this as
                            inspiration to craft your unique story.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Customize Settings</h3>
                        <p>Choose your preferred word count, genre, and tone to get exactly the type of story you want.
                            Fine-tune the parameters for perfect results.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Generate & Enjoy</h3>
                        <p>Watch as our AI crafts a unique story just for you in seconds. Edit, save, or generate
                            another variation with a single click!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Story Showcase -->
    <section class="story-showcase">
        <div class="container">
            <div class="section-title">
                <h2>Example Story Created with Tiny Tales</h2>
                <p>See what our AI can create from a simple prompt</p>
            </div>

            <div class="story-card">
                <h3>The Mysterious Door in the Old Library</h3>
                <p>When librarian Emma discovered a hidden door behind the antique bookcase, she never expected it would
                    lead to a world where stories came to life. The characters from classic novels roamed freely, and
                    the very books she had cared for held the keys to this magical realm...</p>
                <p><em>Genre: Fantasy | Words: 450 | Reading Time: 2 min</em></p>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <img src="assets/img/testimonials-bg.jpg" alt="Happy people reading books together" class="testimonials-bg">
        <div class="container">
            <div class="section-title">
                <h2>What Our Community Says</h2>
                <p>Join thousands of satisfied users who have discovered the joy of AI-assisted storytelling</p>
            </div>

            <div class="testimonials-container">
                <div class="testimonial">
                    <div class="testimonial-content">
                        "Tiny Tales has completely transformed my writing process. Whenever I'm stuck, I use it to generate
                        ideas and it never fails to inspire me. I've published three short stories that started as Tiny
                        Tales prompts!"
                    </div>
                    <div class="author">- Sarah J., Aspiring Author</div>
                </div>

                <div class="testimonial">
                    <div class="testimonial-content">
                        "As an elementary school teacher, I use Tiny Tales to create engaging reading materials for my
                        students. They love the stories and I love how easy it is to generate content tailored to their
                        reading level."
                    </div>
                    <div class="author">- Michael T., 4th Grade Teacher</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-about">
                    <h3>Tiny Tales</h3>
                    <p>Your AI-powered storytelling companion. Spark creativity, overcome writer's block, and discover
                        new worlds with every prompt.</p>
                    <div class="footer-social">
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <div class="footer-copyright">
                <p>&copy; <?= date('Y') ?> Tiny Tales. All rights reserved.</p>
            </div>
        </div>
    </footer>

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

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();

                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;

                    const targetElement = document.querySelector(targetId);

                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });

                        // Close mobile menu if open
                        if (navMenu.classList.contains('show')) {
                            navMenu.classList.remove('show');
                            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                        }
                    }
                });
            });

            // Animation on scroll
            const animateOnScroll = () => {
                const elements = document.querySelectorAll('.feature-card, .testimonial, .step');

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