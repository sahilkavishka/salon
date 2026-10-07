-- Salonora Database Schema
CREATE DATABASE IF NOT EXISTS salonora CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE salonora;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    profile_picture VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    role ENUM('user', 'owner') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Salons Table
CREATE TABLE IF NOT EXISTS salons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    lat DECIMAL(10, 8) NULL,
    lng DECIMAL(11, 8) NULL,
    image VARCHAR(255) NULL,
    opening_time TIME NOT NULL DEFAULT '09:00:00',
    closing_time TIME NOT NULL DEFAULT '18:00:00',
    slot_duration INT NOT NULL DEFAULT 30,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    description TEXT NULL,
    website VARCHAR(255) NULL,
    facebook VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    parking_available TINYINT(1) DEFAULT 0,
    wheelchair_accessible TINYINT(1) DEFAULT 0,
    wifi_available TINYINT(1) DEFAULT 0,
    air_conditioned TINYINT(1) DEFAULT 0,
    operating_days VARCHAR(100) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
    rating DECIMAL(3, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Services Table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    duration INT NOT NULL DEFAULT 30,
    category VARCHAR(100) DEFAULT 'General',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
    cancellation_reason VARCHAR(255) NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Appointment Logs (Supports both appointment_logs and appointment_log)
CREATE TABLE IF NOT EXISTS appointment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    changed_by INT NULL,
    action_type VARCHAR(50) NULL,
    action VARCHAR(50) NULL,
    changed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fallback table alias for appointment_log if used
CREATE TABLE IF NOT EXISTS appointment_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    changed_by INT NULL,
    action_type VARCHAR(50) NULL,
    action VARCHAR(50) NULL,
    changed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Reviews Table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token),
    INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Sample Data (Default password is: Password@123)
-- ==========================================

INSERT INTO users (id, username, email, password, phone, role, location) VALUES
(1, 'Salon Owner', 'owner@salonora.com', '$2y$10$6WPBzeY0jNegbsGohwOFE.n1julEFs7fcf3iQUH25U/RxrtYEQ0La', '0771234567', 'owner', 'Colombo'),
(2, 'Customer User', 'user@salonora.com', '$2y$10$6WPBzeY0jNegbsGohwOFE.n1julEFs7fcf3iQUH25U/RxrtYEQ0La', '0719876543', 'user', 'Kandy')
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO salons (id, owner_id, name, address, lat, lng, opening_time, closing_time, slot_duration, phone, email, description, parking_available, wifi_available, air_conditioned, rating) VALUES
(1, 1, 'Luxe Glow Beauty Lounge', '123 Galle Road, Colombo 03', 6.90160870, 79.85198240, '09:00:00', '19:00:00', 30, '0112345678', 'info@luxeglow.com', 'Premier beauty and hair salon offering world-class care and relaxation.', 1, 1, 1, 4.8)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO services (id, salon_id, name, description, price, duration, category, is_active) VALUES
(1, 1, 'Classic Haircut & Styling', 'Professional precision haircut tailored to your face shape.', 2500.00, 45, 'Hair', 1),
(2, 1, 'Hydra Facial & Glow Treatment', 'Deep cleansing and hydrating herbal facial therapy.', 4500.00, 60, 'Facial', 1),
(3, 1, 'Manicure & Pedicure Deluxe', 'Complete nails spa treatment with polish and massage.', 3500.00, 45, 'Nails', 1)
ON DUPLICATE KEY UPDATE id=id;

INSERT INTO reviews (salon_id, user_id, rating, comment) VALUES
(1, 2, 5, 'Outstanding service and very polite staff! Highly recommend their Hydra Facial.')
ON DUPLICATE KEY UPDATE id=id;
