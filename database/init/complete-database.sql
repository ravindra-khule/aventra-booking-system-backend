-- Initialize Aventra Database
-- This script runs automatically when MySQL container starts

CREATE DATABASE IF NOT EXISTS aventra_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aventra_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    role VARCHAR(100) DEFAULT 'CUSTOMER',
    status VARCHAR(20) DEFAULT 'ACTIVE',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tours table
CREATE TABLE IF NOT EXISTS tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    short_description TEXT NOT NULL,
    description LONGTEXT NOT NULL,
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    image_url VARCHAR(512),
    price DECIMAL(10, 2) NOT NULL,
    deposit_price DECIMAL(10, 2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    duration_days INT NOT NULL,
    difficulty ENUM('easy', 'moderate', 'hard') DEFAULT 'moderate',
    location VARCHAR(255) NOT NULL,
    country VARCHAR(255) NOT NULL,
    region VARCHAR(255),
    max_capacity INT NOT NULL,
    available_spots INT NOT NULL,
    next_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- RBAC Tables Setup
USE aventra_db;

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_default TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    resource VARCHAR(50),
    action VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create role_permissions table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_roles table
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Initial Data Setup
USE aventra_db;

-- Insert default roles
INSERT INTO roles (name, description, is_default) VALUES 
('SUPER_ADMIN', 'Super Administrator with full access', 0),
('ADMIN', 'Administrator with most privileges', 0),
('MANAGER', 'Manager with business operations access', 0),
('SUPPORT', 'Support staff with customer service access', 0),
('ACCOUNTANT', 'Accountant with financial access', 0),
('DEVELOPER', 'Developer with technical access', 0),
('CUSTOMER', 'Regular customer user', 1)
ON DUPLICATE KEY UPDATE name=name;

-- Insert default permissions
INSERT INTO permissions (name, description, resource, action) VALUES 
('users.create', 'Create new users', 'users', 'create'),
('users.read', 'View users', 'users', 'read'),
('users.update', 'Update users', 'users', 'update'),
('users.delete', 'Delete users', 'users', 'delete'),
('tours.create', 'Create tours', 'tours', 'create'),
('tours.read', 'View tours', 'tours', 'read'),
('tours.update', 'Update tours', 'tours', 'update'),
('tours.delete', 'Delete tours', 'tours', 'delete'),
('bookings.create', 'Create bookings', 'bookings', 'create'),
('bookings.read', 'View bookings', 'bookings', 'read'),
('bookings.update', 'Update bookings', 'bookings', 'update'),
('bookings.delete', 'Delete bookings', 'bookings', 'delete'),
('settings.read', 'View settings', 'settings', 'read'),
('settings.update', 'Update settings', 'settings', 'update')
ON DUPLICATE KEY UPDATE name=name;

-- Insert demo users with hashed passwords (passwords: Swett2025!{Role})
INSERT INTO users (email, password, name, role, status) VALUES 
('superadmin@swett.com', '$2y$10$N9m5wohth7RehSdlT4U4x.0EvcPB34V7O4nMymaJlfde2IRQSdGiC', 'Super Admin', 'SUPER_ADMIN', 'ACTIVE'),
('admin@swett.com', '$2y$10$sYJEBTcQLJgkmdI7QjYLoe/Y21c2/kJpaubp0yZJwlkNNWE8hTBmq', 'Admin User', 'ADMIN', 'ACTIVE'),
('manager@swett.com', '$2y$10$sYJEBTcQLJgkmdI7QjYLoe/Y21c2/kJpaubp0yZJwlkNNWE8hTBmq', 'Manager', 'MANAGER', 'ACTIVE'),
('support@swett.com', '$2y$10$1YQJJSCoU25/Jx6u24dunO2iedgvQxEEQ6W8xmSU0mS2wCX9smlrS', 'Support Team', 'SUPPORT', 'ACTIVE'),
('accountant@swett.com', '$2y$10$SsL4NESv186ZQj7J20nJ9.nVr2a7JGit0SSkyCsFEsPlRypSMORGS', 'Accountant', 'ACCOUNTANT', 'ACTIVE'),
('developer@swett.com', '$2y$10$lvzqvWIRwL6I1tlaTcLnVunGUz8TdS27658Frm9FYekY0WY4fKYnW', 'Developer', 'DEVELOPER', 'ACTIVE'),
('customer@swett.com', '$2y$10$GQzb/Dviiej42vF.nbFuY.X/9p1WOO/hOgm4BmSgJTggH6PaxQMqq', 'Customer User', 'CUSTOMER', 'ACTIVE'),
('guest@swett.com', '$2y$10$GQzb/Dviiej42vF.nbFuY.X/9p1WOO/hOgm4BmSgJTggH6PaxQMqq', 'Guest User', 'CUSTOMER', 'ACTIVE')
ON DUPLICATE KEY UPDATE email=email;

-- Assign roles to users
INSERT INTO user_roles (user_id, role_id) 
SELECT u.id, r.id FROM users u, roles r 
WHERE u.email IN ('superadmin@swett.com', 'admin@swett.com', 'manager@swett.com', 'support@swett.com', 'accountant@swett.com', 'developer@swett.com', 'customer@swett.com', 'guest@swett.com')
AND u.role = r.name
ON DUPLICATE KEY UPDATE user_id=user_id;

-- Insert sample tours
INSERT INTO tours (title, slug, short_description, description, status, image_url, price, deposit_price, currency, duration_days, difficulty, location, country, max_capacity, available_spots, next_date) VALUES 
('Mountain Adventure', 'mountain-adventure', 'Exciting mountain trek', 'Experience the beautiful Swiss Alps with professional guides. This adventure includes hiking, camping, and breathtaking views.', 'active', 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?q=80&w=800', 1500.00, 300.00, 'USD', 7, 'hard', 'Swiss Alps', 'Switzerland', 10, 5, '2026-05-15'),
('Tropical Beach Tour', 'tropical-beach', 'Relax on beautiful beaches', 'Paradise awaits you in the Maldives with crystal clear waters, white sand beaches, and luxury accommodations.', 'active', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=800', 899.00, 200.00, 'USD', 5, 'easy', 'Maldives', 'Maldives', 15, 8, '2026-05-20'),
('Desert Safari', 'desert-safari', 'Adventure in the sand dunes', 'Explore the vast desert landscape with camel rides, camping under stars, and traditional Berber hospitality.', 'active', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?q=80&w=800', 599.00, 150.00, 'USD', 3, 'moderate', 'Sahara', 'Morocco', 12, 3, '2026-05-10')
ON DUPLICATE KEY UPDATE slug=slug;
