# 💇 Salonora — Salon Appointment Booking & Management Platform

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Google Gemini](https://img.shields.io/badge/Google%20Gemini-AI-8E75B2?style=for-the-badge&logo=googlegemini&logoColor=white)](https://ai.google.dev/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.x-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Leaflet.js](https://img.shields.io/badge/Leaflet-OpenStreetMap-199900?style=for-the-badge&logo=leaflet&logoColor=white)](https://leafletjs.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](LICENSE)

An enterprise-ready, role-based **Salon Appointment Booking, AI Style Consultation, and Salon Business Management Web Application**. Engineered with PHP PDO security architecture, Google Gemini AI integration, digital QR reception check-in, Leaflet.js open-source map integration, and automated double-booking collision prevention.

---

## 🌟 Key Capabilities & Features

### 🤖 AI Style & Beauty Studio
- **Personalized Consultations**: Powered by Google Gemini AI to analyze face shapes, hair textures, skin undertones, and suggest tailored styles.
- **Voice Narration (TTS)**: Built-in speech synthesis providing audible style recommendations.
- **Interactive Quick Inquiries**: One-click prompt chips for instant hair care, beard grooming, and facial advice.

### 👤 Customer Portal
- **Interactive Salon Discovery**: Search salons by location, rating, and district using free, zero-cost OpenStreetMap (Leaflet.js) tiles.
- **Categorized Service Selection**: Seamlessly browse services across Hair, Skin, Facials, Nails, and Grooming with explicit durations and pricing.
- **Real-Time Slot Scheduling**: Dynamic calendar time-slot booking that prevents overlapping reservations across barbers and stylists.
- **Digital QR Check-In Pass**: Instant QR code generation on confirmed bookings for frictionless, contact-free salon reception check-in.
- **Appointment Lifecycle Dashboard**: Track bookings across granular states (`Upcoming`, `Pending Confirmation`, `Confirmed`, `Completed`, `Cancelled`).
- **Cancellation Grace Period**: Cancel pending appointments anytime or confirmed bookings with 24-hour notice.
- **Verified Reviews & Ratings**: Submit post-appointment feedback and star ratings visible on salon profiles.
- **User Profile & Avatars**: Upload profile images, live previews, and security management.
- **Dark & Light Mode**: Modern glassmorphism UI with persistent theme switching.

### 💼 Salon Owner / Stylist Dashboard
- **Comprehensive Booking Management**: Review upcoming client queues with instant Accept, Decline, or Mark as Completed actions.
- **Service & Menu Builder**: Add, edit, and organize services with custom category tags, duration estimates, and rates.
- **Operational Schedule Tuning**: Define weekly business days, opening/closing hours, and break periods.

### 🛡️ System Administration
- **Global Management Console**: Monitor salon registrations, verify vendor credentials, audit active users, and evaluate booking volumes.

### 🔒 Security & Concurrency Architecture
- **CSRF Defense**: Cryptographic CSRF token generation and verification enforced across all form submissions and AJAX requests.
- **SQL Injection Prevention**: 100% database communication utilizes PDO prepared statements with parameter binding.
- **Race Condition Prevention**: Database transactions employ pessimistic row locking (`SELECT ... FOR UPDATE`) to eliminate double-booking edge cases.
- **Secure Password Resets**: Cryptographically generated, single-use, time-limited reset tokens.

---

## 🛠️ Tech Stack & Architecture

| Layer | Technologies |
|---|---|
| **Backend** | PHP 8.0+, Native Routing (`router.php`), PDO Abstraction Layer |
| **Database** | MySQL / MariaDB (Relational schema with foreign key constraints) |
| **Frontend UI** | HTML5, CSS3, Bootstrap 5, JavaScript (ES6+), Font Awesome |
| **Maps & Geospatial** | Leaflet.js & OpenStreetMap Tiles (No paid Google Maps API key required) |
| **Package Management**| Composer |

---

## 📁 Repository Structure

```plaintext
salon-main/
├── public/                  # Public web root & AJAX endpoints
│   ├── index.php            # Main homepage & salon search
│   ├── get_all_salons.php   # GeoJSON / JSON endpoint for Leaflet map markers
│   ├── book.php             # Slot booking controller
│   └── dashboard.php        # Client/Owner dashboard
├── sql/
│   └── salonora_schema.sql  # Complete database schema, seed data, and tables
├── uploads/                 # Uploaded salon banners and user profile pictures
├── config.php               # Database connection settings & environment constants
├── router.php               # PHP built-in server router
└── composer.json            # Project dependencies
```

---

## 🚀 Quick Setup Guide

### 1. Prerequisites
- **PHP 8.0+**
- **MySQL / MariaDB** (e.g. XAMPP, WAMP, or standalone MySQL)
- Web Browser

### 2. Database Setup
1. Start MySQL service.
2. Create and import the database schema:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS salonora;"
mysql -u root -p salonora < sql/salonora_schema.sql
```

### 3. Database Credentials Configuration
Open `config.php` and verify your credentials:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'salonora');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Run the Application
Launch via PHP's built-in development server:
```bash
php -S localhost:8000 router.php
```
Visit `http://localhost:8000` in your web browser.

---

## 👥 Default Demo Credentials

| Role | Email | Password |
|---|---|---|
| **Customer** | `user@example.com` | `Password123` |
| **Salon Owner** | `owner@example.com` | `Password123` |
| **Administrator**| `admin@example.com` | `Password123` |

---

## 👥 Authors & Collaborators

- **Sahil Kavishka** (Lead Developer)
  - Undergrad in Data Science | Sabaragamuwa University of Sri Lanka
  - GitHub: [@sahilkavishka](https://github.com/sahilkavishka)
  - LinkedIn: [linkedin.com/in/sahilkavishka](https://www.linkedin.com/in/sahilkavishka)
- **Chamudi** (Collaborator)
- **Uvindu** (Collaborator)

---

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).  
