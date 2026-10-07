# Salonora - Salon Booking & Management System

Salonora is a modern web-based Salon Appointment Booking and Management System developed using PHP, MySQL, Bootstrap, and JavaScript.

## 🌟 Key Features

- **User / Customer Features:**
  - Browse salons with location search and ratings.
  - Interactive OpenStreetMap (Leaflet.js) integration (no paid Google Maps API required).
  - Categorized service selection (Hair, Skin, Facial, Nails, etc.).
  - Real-time time-slot booking with automatic collision prevention.
  - Appointment tracking with status tabs (Upcoming, Pending, Confirmed, Completed, Cancelled/Declined).
  - Cancel pending appointments anytime or confirmed appointments prior to 24 hours.
  - Rate and review completed appointments.
  - Secure profile management with profile photo uploads.

- **Salon Owner Features:**
  - Owner dashboard for managing bookings and schedules.
  - Accept, decline, or mark appointments as completed.
  - Add, edit, and manage salon services by categories with duration and pricing.
  - Operating hours and working days configuration.

- **Admin Features:**
  - System-wide administrative controls for salons, users, and overall analytics.

- **Security & Stability:**
  - CSRF protection across all forms and AJAX requests.
  - Prepared statements (PDO) preventing SQL injection.
  - Time-limited, single-use token-based password reset mechanism.
  - Race condition prevention (`FOR UPDATE` transaction locks) on double bookings.

---

## 🚀 Quick Setup Guide

### Prerequisites
- PHP 8.0 or higher
- MySQL / MariaDB (e.g. XAMPP)
- Web Browser

### 1. Database Setup
1. Start MySQL in XAMPP (default port 3306 or configured port).
2. Import the database schema:
   ```bash
   mysql -u root salonora < sql/salonora_schema.sql
   ```

### 2. Configuration
Check `config.php` and verify your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306); // or 3307 if changed
define('DB_NAME', 'salonora');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Run the Application
Run via PHP built-in server:
```bash
php -S localhost:8000 router.php
```
Open `http://localhost:8000` in your web browser.

---

## 👥 Demo User Accounts
- **Customer:**
  - Email: `user@example.com`
  - Password: `Password123`
- **Salon Owner:**
  - Email: `owner@example.com`
  - Password: `Password123`
- **Admin:**
  - Email: `admin@example.com`
  - Password: `Password123`
