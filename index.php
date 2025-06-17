<?php
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('resident/dashboard.php');
    }
}

// Get current year for copyright
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PresDorm - Modern Dormitory Management System for President University Students. Secure, comfortable, and well-managed student housing.">
    <meta name="keywords" content="dormitory, president university, student housing, accommodation, PresDorm">
    <meta name="author" content="PresDorm">
    <title>PresDorm - Dormitory Management System</title>
    
    <!-- Preload critical fonts -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="images/President_University_Logo.png">
    
    <style>
        :root {
            --primary-color: #073c7d;
            --secondary-color: #eec302;
            --dark-color: #002147;
            --light-color: #f5f5f5;
            --accent-color: #fdc800;
            --text-color: #002147;
            --text-light: #d0d6dd;
            --white: #ffffff;
            --shadow-light: 0 2px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 5px 15px rgba(0,0,0,0.12);
            --shadow-heavy: 0 10px 30px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            font-size: 15px;
            line-height: 1.7;
            font-weight: 400;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Utility Classes */
        .btn-transition {
            transition: var(--transition);
        }

        .text-shadow {
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        /* Header Styles */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: var(--white);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .site-header.scrolled {
            box-shadow: var(--shadow-medium);
            backdrop-filter: blur(10px);
        }

        /* Header Top Bar */
        .header-top {
            background-color: var(--dark-color);
            color: var(--text-light);
            padding: 8px 0;
            font-size: 14px;
        }

        .header-contact {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .header-contact li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-contact i {
            color: var(--accent-color);
            font-size: 12px;
        }

        .header-social {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }

        .header-social a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background-color: rgba(255,255,255,0.1);
            color: var(--text-light);
            border-radius: 50%;
            text-decoration: none;
            font-size: 12px;
            transition: var(--transition);
        }

        .header-social a:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Header Separator */
        .header-separator {
            height: 2px;
            background: linear-gradient(90deg, var(--accent-color), var(--secondary-color));
            border: none;
            margin: 0;
        }

        /* Main Header */
        .header-main {
            padding: 15px 0;
            background-color: var(--white);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Logo/Branding */
        .site-branding {
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: var(--transition);
        }

        .site-branding:hover {
            transform: scale(1.02);
        }

        .site-branding img {
            height: 50px;
            width: auto;
            margin-right: 12px;
        }

        .brand-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .brand-text:hover {
            color: var(--primary-color);
            text-decoration: none;
        }

        /* Navigation */
        .main-navigation {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: center;
        }

        .nav-menu {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-menu li a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 0;
            position: relative;
            transition: var(--transition);
        }

        .nav-menu li a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: width 0.3s ease;
        }

        .nav-menu li a:hover::after,
        .nav-menu li a.active::after {
            width: 100%;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            color: var(--accent-color);
            text-decoration: none;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-header {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-decoration: none;
            transition: var(--transition);
            border: 2px solid transparent;
            font-size: 14px;
        }

        .btn-login {
            background-color: transparent;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-login:hover {
            background-color: var(--primary-color);
            color: var(--white);
            text-decoration: none;
        }

        .btn-register {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .btn-register:hover {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            background-color: var(--light-color);
        }

        .mobile-menu-toggle:focus {
            outline: 2px solid var(--accent-color);
        }

        /* Container and Layout */
        .container-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        .title-icon {
            width: 32px;
            height: 32px;
            background-color: var(--secondary-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .title-text {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            letter-spacing: 1px;
        }

        .container-content p {
            text-align: justify;
            color: var(--text-color);
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.7;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/push.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--white);
            padding: 200px 0 120px;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: fadeInUp 1s ease-out;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero-buttons {
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .hero-btn {
            padding: 16px 32px;
            margin: 0 10px 10px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            border: 2px solid transparent;
        }

        .hero-btn-primary {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
        }

        .hero-btn-primary:hover {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .hero-btn-outline {
            background-color: transparent;
            color: var(--white);
            border-color: var(--white);
        }

        .hero-btn-outline:hover {
            background-color: var(--white);
            color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255,255,255,0.3);
        }

        /* Animations */
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

        /* Section Styles */
        .section {
            padding: 80px 0;
        }

        .section-white {
            background-color: var(--white);
        }

        .section-light {
            background-color: #f9fafc;
        }

        /* Toggle/Accordion Styles */
        .toggle-container {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
        }

        .toggle-item {
            border-bottom: 1px solid #eee;
        }

        .toggle-item:last-child {
            border-bottom: none;
        }

        .toggle-title {
            background-color: #f8f9fa;
            padding: 20px 25px;
            margin: 0;
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-color);
            border: none;
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
            font-size: 16px;
        }

        .toggle-title:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
        }

        .toggle-title:focus {
            outline: 2px solid var(--accent-color);
        }

        .toggle-icon {
            color: var(--primary-color);
            transition: transform 0.3s ease;
            font-size: 14px;
        }

        .toggle-content {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            background-color: var(--white);
        }

        .toggle-content.active {
            padding: 25px;
            max-height: 300px;
        }

        .toggle-content p {
            color: var(--text-color);
            margin: 0;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Card Styles */
        .card-modern {
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            height: 100%;
            margin-bottom: 30px;
        }

        .card-modern:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-heavy);
        }

        /* Achievements Section */
        .achievements-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .achievements-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 50px 50px;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }

        .achievement-item {
            text-align: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .achievement-number {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .achievement-text {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 991px) {
            .main-navigation {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background-color: var(--white);
                box-shadow: var(--shadow-medium);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: var(--transition);
                z-index: 999;
            }

            .main-navigation.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }

            .nav-menu {
                flex-direction: column;
                padding: 20px 0;
                gap: 0;
            }

            .nav-menu li {
                width: 100%;
                text-align: center;
                border-bottom: 1px solid #eee;
            }

            .nav-menu li:last-child {
                border-bottom: none;
            }

            .nav-menu li a {
                display: block;
                padding: 15px 20px;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .header-actions {
                order: -1;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 768px) {
            .header-top {
                padding: 6px 0;
            }

            .header-contact {
                gap: 15px;
                font-size: 13px;
            }

            .header-social {
                display: none;
            }

            .site-branding img {
                height: 40px;
            }

            .brand-text {
                font-size: 20px;
            }

            .btn-header {
                padding: 10px 18px;
                font-size: 13px;
            }

            .hero-section {
                padding: 160px 0 80px;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-btn {
                display: block;
                margin: 10px auto;
                max-width: 250px;
                text-align: center;
            }

            .title-text {
                font-size: 24px;
            }

            .achievement-number {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 576px) {
            .header-contact {
                flex-direction: column;
                gap: 8px;
                font-size: 12px;
            }

            .hero-title {
                font-size: 1.8rem;
            }

            .title-text {
                font-size: 20px;
            }

            .btn-header {
                padding: 8px 16px;
                font-size: 12px;
            }
        }

        /* Additional dormitory and testimonial styles would continue here... */
        /* I'll include the key ones for brevity */

        .dorm-card {
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            height: 100%;
            margin-bottom: 30px;
        }

        .dorm-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-heavy);
        }

        .dorm-img {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }

        .dorm-content {
            padding: 30px;
        }

        .dorm-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .dorm-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .dorm-features li {
            padding: 8px 0;
            color: var(--text-color);
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .dorm-features li:last-child {
            border-bottom: none;
        }

        .dorm-features li i {
            color: var(--secondary-color);
            margin-right: 12px;
            width: 20px;
        }

        .dorm-btn {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            text-transform: uppercase;
            transition: var(--transition);
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
            border: 2px solid var(--primary-color);
        }

        .dorm-btn:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-color: var(--secondary-color);
            text-decoration: none;
            transform: translateY(-2px);
        }

        .dorm-btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .dorm-btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white);
        }

        /* Footer Styles */
        .footer {
            background-color: var(--dark-color);
            color: var(--text-light);
        }

        .footer-top {
            padding: 60px 0;
        }

        .footer-title {
            color: var(--white);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .footer-link {
            color: var(--text-light);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            transition: var(--transition);
            padding: 5px 0;
        }

        .footer-link:hover {
            color: var(--accent-color);
            text-decoration: none;
            padding-left: 5px;
        }

        .footer-social {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .footer-social a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255,255,255,0.1);
            color: var(--text-light);
            border-radius: 50%;
            transition: var(--transition);
            text-decoration: none;
        }

        .footer-social a:hover {
            background-color: var(--accent-color);
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        .footer-bottom {
            background-color: #001a39;
            color: #909da4;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Testimonial Styles */
        .testimonial-card {
            background-color: var(--white);
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            height: 100%;
            margin-bottom: 30px;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }

        .testimonial-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid var(--secondary-color);
        }

        .testimonial-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
            font-size: 16px;
        }

        .testimonial-position {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .testimonial-text {
            color: var(--text-color);
            font-style: italic;
            line-height: 1.6;
            font-size: 15px;
        }

        .testimonial-rating {
            color: var(--secondary-color);
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header" id="site-header">
        <!-- Top Header Bar -->
        <div class="header-top">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <ul class="header-contact">
                        <li>
                            <i class="fas fa-phone"></i>
                            <span>+62-21 8910 9763</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>info@presdorm.com</span>
                        </li>
                    </ul>
                    <div class="header-social">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.instagram.com/sbhdormitory/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Separator -->
        <hr class="header-separator">

        <!-- Main Header -->
        <div class="header-main">
            <div class="header-container">
                <!-- Logo/Branding -->
                <a href="index.php" class="site-branding">
                    <img src="https://upload.wikimedia.org/wikipedia/en/a/ae/President_University_Logo.png" alt="PresDorm Logo">
                    <span class="brand-text">PresDorm</span>
                </a>

                <!-- Main Navigation -->
                <nav class="main-navigation" id="main-navigation">
                    <ul class="nav-menu">
                        <li><a href="#welcome" class="nav-link">About</a></li>
                        <li><a href="#why-choose" class="nav-link">Features</a></li>
                        <li><a href="#dormitories" class="nav-link">Dormitories</a></li>
                        <li><a href="#testimonials" class="nav-link">Reviews</a></li>
                        <li><a href="login.php" class="nav-link">Login</a></li>
                    </ul>
                </nav>

                <!-- Header Actions -->
                <div class="header-actions">
                    <a href="login.php" class="btn-header btn-login">Login</a>
                    <a href="register.php" class="btn-header btn-register">Register Now</a>
                    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="hero-content">
                        <h1 class="hero-title">Welcome to PresDorm</h1>
                        <p class="hero-subtitle">Modern Dormitory Management System for President University Students</p>
                        <div class="hero-buttons">
                            <a href="register.php" class="hero-btn hero-btn-primary">Register Now</a>
                            <a href="login.php" class="hero-btn hero-btn-outline">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Welcome Section -->
    <section class="section section-white" id="welcome">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-home" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">WELCOME TO PRESDORM</h2>
            </div>
            <div class="container-content">
                <p>PresDorm is devoted to excellence in providing modern dormitory management services for President University students. Our comprehensive system streamlines your campus living experience, offering seamless integration of accommodation services, maintenance requests, community engagement, and student support services.</p>
                
                <p>Located within the vibrant President University campus in Jababeka, Cikarang, PresDorm serves as your gateway to comfortable and secure student housing. Our dormitories are designed to foster academic success, personal growth, and meaningful connections among students from diverse backgrounds.</p>
            </div>
        </div>
    </section>

    <!-- Why Choose PresDorm Section -->
    <section class="section section-light" id="why-choose">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-star" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">WHY CHOOSE PRESDORM?</h2>
            </div>
            <div class="container-content">
                <p>We are committed to providing an enriching living experience beyond just accommodation. Discover the benefits of choosing PresDorm for your campus housing needs.</p>
            </div>
            
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="toggle-container">
                        <div class="toggle-item">
                            <button class="toggle-title" onclick="toggleContent(this)">
                                Digital Management System
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="toggle-content">
                                <p>Access all dormitory services through our user-friendly digital platform, from registration to maintenance requests and community updates.</p>
                            </div>
                        </div>
                        
                        <div class="toggle-item">
                            <button class="toggle-title" onclick="toggleContent(this)">
                                24/7 Security & Support
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="toggle-content">
                                <p>Round-the-clock security services and dedicated support staff ensure your safety and comfort throughout your stay.</p>
                            </div>
                        </div>
                        
                        <div class="toggle-item">
                            <button class="toggle-title" onclick="toggleContent(this)">
                                Modern Facilities
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="toggle-content">
                                <p>Enjoy modern amenities including high-speed WiFi, air conditioning, study areas, and recreational facilities designed for student comfort.</p>
                            </div>
                        </div>
                        
                        <div class="toggle-item">
                            <button class="toggle-title" onclick="toggleContent(this)">
                                Community Building
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="toggle-content">
                                <p>Participate in community events, connect with fellow students, and build lasting friendships in our inclusive dormitory environment.</p>
                            </div>
                        </div>
                        
                        <div class="toggle-item">
                            <button class="toggle-title" onclick="toggleContent(this)">
                                Value for Money
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </button>
                            <div class="toggle-content">
                                <p>Competitive pricing with transparent costs and flexible payment options make quality dormitory living accessible to all students.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="images/Group-1.jpg" alt="PresDorm Community" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Dormitories Section -->
    <section class="section section-white" id="dormitories">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-building" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">OUR DORMITORIES</h2>
            </div>
            <div class="container-content">
                <p>We offer modern dormitory options designed to meet diverse student needs and preferences. Choose from our range of accommodation types.</p>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="dorm-card">
                        <img src="images/sbh-room.jpeg" alt="Student Boarding House" class="dorm-img">
                        <div class="dorm-content">
                            <h3 class="dorm-title">Student Boarding House</h3>
                            <p>Traditional dormitory with shared facilities, perfect for students seeking a community-oriented living experience.</p>
                            
                            <ul class="dorm-features">
                                <li><i class="fas fa-wifi"></i> Free High-Speed WiFi</li>
                                <li><i class="fas fa-snowflake"></i> Air Conditioning</li>
                                <li><i class="fas fa-chair"></i> Study Desk & Chair</li>
                                <li><i class="fas fa-users"></i> Shared Bathroom</li>
                                <li><i class="fas fa-utensils"></i> Common Kitchen</li>
                                <li><i class="fas fa-shield-alt"></i> 24/7 Security</li>
                            </ul>
                            
                            <div>
                                <a href="sbh-details.php" class="dorm-btn dorm-btn-outline">View Details</a>
                                <a href="register.php?dorm=1" class="dorm-btn">Register Now</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="dorm-card">
                        <img src="images/nbh-room.jpeg" alt="New Beverly Hills" class="dorm-img">
                        <div class="dorm-content">
                            <h3 class="dorm-title">New Beverly Hills</h3>
                            <p>Premium dormitory with private amenities, ideal for students seeking enhanced comfort and privacy.</p>
                            
                            <ul class="dorm-features">
                                <li><i class="fas fa-wifi"></i> Free High-Speed WiFi</li>
                                <li><i class="fas fa-bath"></i> Private Bathroom</li>
                                <li><i class="fas fa-snowflake"></i> Air Conditioning</li>
                                <li><i class="fas fa-chair"></i> Study Desk & Chair</li>
                                <li><i class="fas fa-bed"></i> Single/Twin Room Options</li>
                                <li><i class="fas fa-shield-alt"></i> 24/7 Security</li>
                            </ul>
                            
                            <div>
                                <a href="nbh-details.php" class="dorm-btn dorm-btn-outline">View Details</a>
                                <a href="register.php?dorm=2" class="dorm-btn">Register Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Achievements Section -->
    <section class="achievements-section">
        <div class="container">
            <div class="container-title text-center mb-5">
                <h2 class="title-text text-white">OUR ACHIEVEMENTS</h2>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="achievement-item">
                        <div class="achievement-number counter" data-target="500">0</div>
                        <div class="achievement-text">Happy Residents</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="achievement-item">
                        <div class="achievement-number counter" data-target="95">0</div>
                        <div class="achievement-text">% Satisfaction Rate</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="achievement-item">
                        <div class="achievement-number counter" data-target="24">0</div>
                        <div class="achievement-text">Hours Security Service</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="achievement-item">
                        <div class="achievement-number counter" data-target="2">0</div>
                        <div class="achievement-text">Modern Dormitory Buildings</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section section-white" id="testimonials">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-comments" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">STUDENT REVIEWS</h2>
            </div>
            <div class="container-content">
                <p>See what our residents have to say about their PresDorm experience and community life.</p>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" class="testimonial-img" alt="Student Review">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 class="testimonial-name">Thoriq Zul Atsari</h4>
                        <p class="testimonial-position">Computer Science Student</p>
                        <p class="testimonial-text">"PresDorm has made my college experience so much better. The digital management system is incredibly user-friendly, and the maintenance requests are handled quickly. The community events help me meet new friends from different programs."</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <img src="https://randomuser.me/api/portraits/men/46.jpg" class="testimonial-img" alt="Student Review">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <h4 class="testimonial-name">Reza Fahlevi</h4>
                        <p class="testimonial-position">Computer Science Student</p>
                        <p class="testimonial-text">"Living in New Beverly Hills has been amazing! Having a private bathroom makes all the difference, and the security features make me feel completely safe. The WiFi is super fast, which is perfect for my programming assignments."</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <img src="https://randomuser.me/api/portraits/women/65.jpg" class="testimonial-img" alt="Student Review">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <h4 class="testimonial-name">Emily Chen</h4>
                        <p class="testimonial-position">Engineering Student</p>
                        <p class="testimonial-text">"The PresDorm management system makes everything so convenient. I can submit maintenance requests, check announcements, and connect with other residents all in one place. The staff is always helpful and responsive."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-top">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <h3 class="footer-title">PresDorm</h3>
                        <p class="text-light mb-4">Modern dormitory management system for President University students, providing comfortable and secure campus housing.</p>
                        <div class="footer-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="https://www.instagram.com/sbhdormitory/"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <h3 class="footer-title">Quick Links</h3>
                        <a href="#welcome" class="footer-link">About PresDorm</a>
                        <a href="#why-choose" class="footer-link">Features</a>
                        <a href="#dormitories" class="footer-link">Dormitories</a>
                        <a href="login.php" class="footer-link">Login</a>
                        <a href="register.php" class="footer-link">Register</a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <h3 class="footer-title">Dormitories</h3>
                        <a href="register.php?dorm=1" class="footer-link">Student Boarding House</a>
                        <a href="register.php?dorm=2" class="footer-link">New Beverly Hills</a>
                        <a href="sbh-details.php" class="footer-link">SBH Details</a>
                        <a href="nbh-details.php" class="footer-link">NBH Details</a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6">
                        <h3 class="footer-title">Contact Us</h3>
                        <a href="tel:+622189109763" class="footer-link">
                            <i class="fas fa-phone"></i> +62-21 8910 9763
                        </a>
                        <a href="mailto:info@presdorm.com" class="footer-link">
                            <i class="fas fa-envelope"></i> info@presdorm.com
                        </a>
                        <p class="footer-link">
                            <i class="fas fa-map-marker-alt"></i> President University, Jababeka, Cikarang
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="container">
                <div class="text-center">
                    <p>© <?php echo $current_year; ?> PresDorm. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Enhanced toggle functionality for accordion
        function toggleContent(element) {
            const content = element.nextElementSibling;
            const icon = element.querySelector('.toggle-icon');
            const allToggles = document.querySelectorAll('.toggle-content');
            const allIcons = document.querySelectorAll('.toggle-icon');
            
            // Close all other open contents
            allToggles.forEach(toggle => {
                if (toggle !== content && toggle.classList.contains('active')) {
                    toggle.classList.remove('active');
                }
            });
            
            // Reset all icons
            allIcons.forEach(toggleIcon => {
                if (toggleIcon !== icon) {
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle current content
            content.classList.toggle('active');
            
            if (content.classList.contains('active')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Enhanced mobile menu toggle
        function toggleMobileMenu() {
            const navigation = document.getElementById('main-navigation');
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            const icon = menuToggle.querySelector('i');
            
            navigation.classList.toggle('active');
            
            if (navigation.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-times');
                document.body.style.overflow = 'hidden';
            } else {
                icon.classList.replace('fa-times', 'fa-bars');
                document.body.style.overflow = '';
            }
        }

        // Counter animation with intersection observer
        function animateCounters() {
            const counters = document.querySelectorAll('.counter');
            const speed = 50;

            counters.forEach(counter => {
                if (counter.hasAttribute('data-animated')) return;
                
                counter.setAttribute('data-animated', 'true');
                const updateCount = () => {
                    const target = +counter.getAttribute('data-target');
                    const count = +counter.innerText;
                    const increment = target / speed;

                    if (count < target) {
                        counter.innerText = Math.ceil(count + increment);
                        setTimeout(updateCount, 20);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
            });
        }

        // Enhanced smooth scroll with offset
        function initSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        const headerHeight = document.querySelector('.site-header').offsetHeight;
                        const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });

                        // Close mobile menu if open
                        const navigation = document.getElementById('main-navigation');
                        if (navigation.classList.contains('active')) {
                            toggleMobileMenu();
                        }
                    }
                });
            });
        }

        // Enhanced intersection observer
        function initIntersectionObserver() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (entry.target.classList.contains('achievements-section')) {
                            animateCounters();
                        }
                        
                        // Add animation classes to cards and other elements
                        if (entry.target.classList.contains('card-modern') || 
                            entry.target.classList.contains('testimonial-card')) {
                            entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                        }
                    }
                });
            }, { 
                threshold: 0.3,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe elements
            document.querySelectorAll('.achievements-section, .card-modern, .testimonial-card').forEach(element => {
                observer.observe(element);
            });
        }

        // Enhanced header scroll effect
        function initHeaderScrollEffect() {
            const header = document.querySelector('.site-header');
            let lastScrollTop = 0;
            
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
                
                // Hide/show header on scroll
                if (scrollTop > lastScrollTop && scrollTop > 200) {
                    header.style.transform = 'translateY(-100%)';
                } else {
                    header.style.transform = 'translateY(0)';
                }
                
                lastScrollTop = scrollTop;
            });
        }

        // Active navigation link highlighting
        function initActiveNavigation() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            
            window.addEventListener('scroll', () => {
                let current = '';
                const scrollPos = window.pageYOffset + 200;
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.offsetHeight;
                    
                    if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                        current = section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${current}`) {
                        link.classList.add('active');
                    }
                });
            });
        }

        // Close mobile menu when clicking outside
        function initClickOutside() {
            document.addEventListener('click', function(event) {
                const navigation = document.getElementById('main-navigation');
                const menuToggle = document.querySelector('.mobile-menu-toggle');
                
                if (!navigation.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    navigation.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });
        }

        // Prevent scroll when mobile menu is open
        function preventScrollOnMobileMenu() {
            const navigation = document.getElementById('main-navigation');
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        if (navigation.classList.contains('active')) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = '';
                        }
                    }
                });
            });
            
            observer.observe(navigation, { attributes: true });
        }

        // Initialize all functions when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initSmoothScroll();
            initIntersectionObserver();
            initHeaderScrollEffect();
            initActiveNavigation();
            initClickOutside();
            preventScrollOnMobileMenu();
            
            // Add loading animation
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.5s ease';
                document.body.style.opacity = '1';
            }, 100);
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const navigation = document.getElementById('main-navigation');
            if (window.innerWidth > 991 && navigation.classList.contains('active')) {
                toggleMobileMenu();
            }
        });
    </script>
</body>
</html>