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
