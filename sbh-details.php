<?php
require_once 'config.php';

// Get current year for copyright
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Boarding House - Modern dormitory at President University with community environment and excellent facilities.">
    <meta name="keywords" content="student boarding house, president university, dormitory, student housing, SBH">
    <meta name="author" content="PresDorm">
    <title>Student Boarding House - Details | PresDorm</title>
    
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

        /* Page Header */
        .page-header {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/sbh-room.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--white);
            padding: 200px 0 120px;
            position: relative;
            overflow: hidden;
        }

        .page-header-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .page-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.5);
            animation: fadeInUp 1s ease-out;
        }

        .page-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .breadcrumb-nav {
            margin-top: 2rem;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .breadcrumb-nav a {
            color: var(--white);
            text-decoration: none;
            opacity: 0.8;
            transition: var(--transition);
        }

        .breadcrumb-nav a:hover {
            color: var(--accent-color);
            text-decoration: none;
        }

        .breadcrumb-nav span {
            opacity: 0.6;
            margin: 0 10px;
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

        /* Gallery Styles */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .gallery-item {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            position: relative;
        }

        .gallery-item:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-heavy);
        }

        .gallery-item img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            transition: var(--transition);
        }

        .gallery-item:hover img {
            transform: scale(1.05);
        }

        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: var(--white);
            padding: 30px 20px 20px;
            transform: translateY(100%);
            transition: var(--transition);
        }

        .gallery-item:hover .gallery-overlay {
            transform: translateY(0);
        }

        .gallery-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .gallery-description {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Detail Cards */
        .detail-card {
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
            margin-bottom: 30px;
        }

        .detail-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }

        .detail-card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 25px;
            font-weight: 600;
            font-size: 18px;
        }

        .detail-card-body {
            padding: 30px;
        }

        .detail-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .detail-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item:hover {
            background-color: #f8f9fa;
            padding-left: 10px;
        }

        .detail-item i {
            color: var(--secondary-color);
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        /* Feature Items */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            padding: 25px;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 24px;
            margin-right: 20px;
            flex-shrink: 0;
        }

        .feature-content h4 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .feature-content p {
            color: #666;
            margin-bottom: 0;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Room Options */
        .room-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .room-card {
            background-color: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            transition: var(--transition);
        }

        .room-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-heavy);
        }

        .room-card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 25px;
            text-align: center;
        }

        .room-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }

        .room-card-body {
            padding: 30px;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
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

        .cta-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .cta-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .cta-btn {
            padding: 16px 32px;
            background-color: var(--white);
            color: var(--primary-color);
            border-radius: var(--border-radius);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            border: 2px solid var(--white);
        }

        .cta-btn:hover {
            background-color: transparent;
            color: var(--white);
            text-decoration: none;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
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

            .page-title {
                font-size: 2.5rem;
            }

            .page-subtitle {
                font-size: 1.1rem;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .room-options {
                grid-template-columns: 1fr;
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

            .page-header {
                padding: 160px 0 80px;
            }

            .page-title {
                font-size: 2rem;
            }

            .title-text {
                font-size: 24px;
            }

            .gallery-item img {
                height: 220px;
            }

            .cta-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .header-contact {
                flex-direction: column;
                gap: 8px;
                font-size: 12px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .title-text {
                font-size: 20px;
            }

            .btn-header {
                padding: 8px 16px;
                font-size: 12px;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .gallery-item img {
                height: 200px;
            }
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
                        <li><a href="index.php#welcome" class="nav-link">About</a></li>
                        <li><a href="index.php#why-choose" class="nav-link">Features</a></li>
                        <li><a href="index.php#dormitories" class="nav-link">Dormitories</a></li>
                        <li><a href="index.php#testimonials" class="nav-link">Reviews</a></li>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="page-header-content">
                        <h1 class="page-title">Student Boarding House</h1>
                        <p class="page-subtitle">Traditional dormitory with shared facilities, perfect for students seeking a community-oriented living experience</p>
                        <div class="breadcrumb-nav">
                            <a href="index.php">Home</a>
                            <span>/</span>
                            <a href="index.php#dormitories">Dormitories</a>
                            <span>/</span>
                            <span>Student Boarding House</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="section section-white">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-images" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">PHOTO GALLERY</h2>
            </div>
            <div class="container-content">
                <p>Explore our Student Boarding House facilities and discover what makes it the perfect choice for your campus living experience.</p>
            </div>
            
            <div class="gallery-grid">
                <div class="gallery-item">
                    <img src="images/sbh-room.jpeg" alt="Student Boarding House Room">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Student Room</div>
                        <div class="gallery-description">Comfortable rooms with modern amenities</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <img src="images/basketball.jpg" alt="Basketball Court">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Basketball Court</div>
                        <div class="gallery-description">Recreational sports facilities</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <img src="images/sbh-mushola.jpg" alt="Prayer Room">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Prayer Room (Mushola)</div>
                        <div class="gallery-description">Peaceful place for worship</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <img src="images/sbh-pavilion.jpg" alt="Pavilion">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Pavilion</div>
                        <div class="gallery-description">Community gathering space</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <img src="images/sbh-gate.jpg" alt="Entrance Gate">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Main Gate</div>
                        <div class="gallery-description">Secure entrance with 24/7 security</div>
                    </div>
                </div>
                <div class="gallery-item">
                    <img src="images/sbh-canteen.jpg" alt="Canteen">
                    <div class="gallery-overlay">
                        <div class="gallery-title">Canteen</div>
                        <div class="gallery-description">Dining and food court area</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="section section-light">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-home" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">ABOUT STUDENT BOARDING HOUSE</h2>
            </div>
            <div class="container-content">
                <p>The Student Boarding House offers a traditional dormitory experience with a modern twist. Located at the heart of the campus, this dormitory provides students with a vibrant community environment where they can build lifelong friendships and create memorable college experiences.</p>
                
                <p>Our Student Boarding House features shared rooms designed for students, with comfortable beds, study areas, and ample storage space. The dormitory also includes common areas for socializing, studying, and relaxing, fostering a sense of community among residents.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Community Environment</h4>
                        <p>Connect with other residents and build lasting friendships in our vibrant community atmosphere.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h4>Prime Location</h4>
                        <p>Situated at the heart of the campus, just minutes away from academic buildings and facilities.</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h4>24/7 Security</h4>
                        <p>Round-the-clock security personnel and CCTV surveillance for your safety and peace of mind.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Room Options Section -->
    <section class="section section-white">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-bed" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">ROOM OPTIONS</h2>
            </div>
            <div class="container-content">
                <p>Choose from our flexible room arrangements designed to meet different student preferences and budget considerations.</p>
            </div>
            
            <div class="room-options">
                <div class="room-card">
                    <div class="room-card-header">
                        <h3 class="room-title">Sharing Room</h3>
                    </div>
                    <div class="room-card-body">
                        <ul class="detail-list">
                            <li class="detail-item"><i class="fas fa-ruler-combined"></i> <strong>Room Size:</strong> 20 sqm</li>
                            <li class="detail-item"><i class="fas fa-user-friends"></i> <strong>Occupancy:</strong> 2 Students</li>
                            <li class="detail-item"><i class="fas fa-bed"></i> <strong>Bed Type:</strong> Single Bed</li>
                            <li class="detail-item"><i class="fas fa-money-bill-wave"></i> <strong>Monthly Fee:</strong> Rp 1,450,000</li>
                        </ul>
                    </div>
                </div>
                
                <div class="room-card">
                    <div class="room-card-header">
                        <h3 class="room-title">Single Room</h3>
                    </div>
                    <div class="room-card-body">
                        <ul class="detail-list">
                            <li class="detail-item"><i class="fas fa-ruler-combined"></i> <strong>Room Size:</strong> 15 sqm</li>
                            <li class="detail-item"><i class="fas fa-user"></i> <strong>Occupancy:</strong> 1 Student</li>
                            <li class="detail-item"><i class="fas fa-bed"></i> <strong>Bed Type:</strong> Single Bed</li>
                            <li class="detail-item"><i class="fas fa-money-bill-wave"></i> <strong>Monthly Fee:</strong> Rp 1,800,000</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Facilities Section -->
    <section class="section section-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <h3>Room Facilities</h3>
                        </div>
                        <div class="detail-card-body">
                            <ul class="detail-list">
                                <li class="detail-item"><i class="fas fa-bed"></i> Comfortable Single Beds with Spring Mattress</li>
                                <li class="detail-item"><i class="fas fa-desk"></i> Study Desk for Each Resident</li>
                                <li class="detail-item"><i class="fas fa-tshirt"></i> Wardrobe/Storage Space</li>
                                <li class="detail-item"><i class="fas fa-snowflake"></i> Full Air Conditioning</li>
                                <li class="detail-item"><i class="fas fa-wifi"></i> Free High-Speed WiFi</li>
                                <li class="detail-item"><i class="fas fa-chair"></i> 1 Chair per Student</li>
                                <li class="detail-item"><i class="fas fa-lightbulb"></i> Good Lighting</li>
                                <li class="detail-item"><i class="fas fa-plug"></i> Multiple Power Outlets</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <h3>Shared Facilities</h3>
                        </div>
                        <div class="detail-card-body">
                            <ul class="detail-list">
                                <li class="detail-item"><i class="fas fa-bath"></i> Shared Bathroom Facilities</li>
                                <li class="detail-item"><i class="fas fa-utensils"></i> Shared Kitchen Facilities</li>
                                <li class="detail-item"><i class="fas fa-tshirt"></i> Laundry Room</li>
                                <li class="detail-item"><i class="fas fa-book"></i> Study Rooms</li>
                                <li class="detail-item"><i class="fas fa-users"></i> Discussion Spaces</li>
                                <li class="detail-item"><i class="fas fa-couch"></i> Common Lounge Areas</li>
                                <li class="detail-item"><i class="fas fa-store"></i> Mini Market Access</li>
                                <li class="detail-item"><i class="fas fa-bus"></i> Free Shuttle Bus Service</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-lg-12">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <h3>Sports & Recreation Facilities</h3>
                        </div>
                        <div class="detail-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="detail-list">
                                        <li class="detail-item"><i class="fas fa-swimmer"></i> Swimming Pool</li>
                                        <li class="detail-item"><i class="fas fa-table-tennis"></i> Tennis Courts</li>
                                        <li class="detail-item"><i class="fas fa-basketball-ball"></i> Basketball Court</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="detail-list">
                                        <li class="detail-item"><i class="fas fa-volleyball-ball"></i> Volleyball Court</li>
                                        <li class="detail-item"><i class="fas fa-golf-ball"></i> Golf Course</li>
                                        <li class="detail-item"><i class="fas fa-dumbbell"></i> Fitness Center</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Payment Information Section -->
    <section class="section section-white">
        <div class="container">
            <div class="container-title">
                <div class="title-icon">
                    <i class="fas fa-credit-card" style="color: var(--primary-color);"></i>
                </div>
                <h2 class="title-text">PAYMENT INFORMATION</h2>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <h3>Payment Details</h3>
                        </div>
                        <div class="detail-card-body">
                            <h4><i class="fas fa-shield-alt"></i> Deposit</h4>
                            <p>A refundable deposit of <strong>Rp 1,250,000</strong> is required, to be paid once before moving in. This deposit will be returned after completing your stay of 1 year.</p>
                            
                            <h4 class="mt-4"><i class="fas fa-calendar-alt"></i> Payment Schedule</h4>
                            <p>Payment can be made either per semester or for a full year, starting at the beginning of the academic year (August 25).</p>
                            
                            <h4 class="mt-4"><i class="fas fa-university"></i> Payment Method</h4>
                            <p>Dormitory fees and deposits can be transferred via <strong>Bank Mandiri Virtual Account (VA)</strong>, using your Registration Number and full name as reference, as stated in your Letter of Acceptance (LoA).</p>
                            
                            <div class="alert alert-info mt-4" style="background-color: #e8f4fd; border: 1px solid #bee5eb; padding: 15px; border-radius: 8px;">
                                <i class="fas fa-info-circle" style="color: var(--primary-color);"></i>
                                <strong>Important Note:</strong> Electricity usage for computers, laptops, printers, room lamps, and study-related equipment is the resident's responsibility.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <h3>Contact Information</h3>
                        </div>
                        <div class="detail-card-body">
                            <ul class="detail-list">
                                <li class="detail-item"><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> President University Campus, Jababeka, Cikarang</li>
                                <li class="detail-item"><i class="fas fa-phone"></i> <strong>Phone:</strong> +62-21 8910 9763</li>
                                <li class="detail-item"><i class="fas fa-envelope"></i> <strong>Email:</strong> sbh@presdorm.com</li>
                                <li class="detail-item"><i class="fas fa-clock"></i> <strong>Office Hours:</strong> Monday-Friday, 8am-5pm</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="cta-content">
                        <h2 class="cta-title">Ready to Join Our Community?</h2>
                        <p class="cta-text">Register now to secure your spot in our Student Boarding House and experience the PresDorm difference.</p>
                        <a href="register.php?dorm=1" class="cta-btn">Register Now</a>
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
                        <a href="index.php#welcome" class="footer-link">About PresDorm</a>
                        <a href="index.php#why-choose" class="footer-link">Features</a>
                        <a href="index.php#dormitories" class="footer-link">Dormitories</a>
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

        // Smooth scroll for anchor links
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

        // Animation on scroll
        function initScrollAnimations() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    }
                });
            }, { 
                threshold: 0.3,
                rootMargin: '0px 0px -50px 0px'
            });

            // Observe elements
            document.querySelectorAll('.detail-card, .gallery-item, .feature-item, .room-card').forEach(element => {
                observer.observe(element);
            });
        }

        // Initialize all functions when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initHeaderScrollEffect();
            initClickOutside();
            initSmoothScroll();
            initScrollAnimations();
            
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