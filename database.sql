-- FleetFlow Database Schema
-- MySQL 5.7+ / MariaDB 10.2+

CREATE DATABASE IF NOT EXISTS fleetflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fleetflow;

-- Users / Auth
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Manager','Dispatcher','Safety Officer','Finance') NOT NULL DEFAULT 'Dispatcher',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vehicles
CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    make VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year YEAR NOT NULL,
    type ENUM('Truck','Van','Car','Motorcycle','Bus','Other') NOT NULL DEFAULT 'Truck',
    max_capacity DECIMAL(10,2) NOT NULL COMMENT 'in kg',
    status ENUM('Available','On Trip','In Shop','Suspended') NOT NULL DEFAULT 'Available',
    odometer DECIMAL(10,2) DEFAULT 0 COMMENT 'in km',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Drivers
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    license_number VARCHAR(50) NOT NULL UNIQUE,
    license_expiry DATE NOT NULL,
    license_class VARCHAR(20),
    status ENUM('On Duty','Off Duty','Suspended') NOT NULL DEFAULT 'Off Duty',
    date_of_birth DATE,
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(30),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Trips
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_number VARCHAR(30) NOT NULL UNIQUE,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    origin VARCHAR(200) NOT NULL,
    destination VARCHAR(200) NOT NULL,
    cargo_description VARCHAR(255),
    cargo_weight DECIMAL(10,2) DEFAULT 0 COMMENT 'in kg',
    distance_km DECIMAL(10,2) DEFAULT 0,
    scheduled_departure DATETIME,
    actual_departure DATETIME,
    actual_arrival DATETIME,
    status ENUM('Draft','Dispatched','Completed','Cancelled') NOT NULL DEFAULT 'Draft',
    revenue DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    cancelled_reason TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Maintenance
CREATE TABLE maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    maintenance_type ENUM('Routine','Repair','Inspection','Tire','Oil Change','Brake','Engine','Other') NOT NULL DEFAULT 'Routine',
    description TEXT,
    scheduled_date DATE NOT NULL,
    completed_date DATE,
    cost DECIMAL(12,2) DEFAULT 0,
    vendor VARCHAR(100),
    status ENUM('Scheduled','In Progress','Completed') NOT NULL DEFAULT 'Scheduled',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Fuel Logs
CREATE TABLE fuel_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    trip_id INT,
    fuel_date DATE NOT NULL,
    liters DECIMAL(10,2) NOT NULL,
    price_per_liter DECIMAL(10,4) NOT NULL,
    total_cost DECIMAL(12,2) GENERATED ALWAYS AS (liters * price_per_liter) STORED,
    odometer_reading DECIMAL(10,2),
    fuel_type ENUM('Diesel','Petrol','CNG','Electric','Other') DEFAULT 'Diesel',
    station_name VARCHAR(100),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Vehicle Costs / Expenses
CREATE TABLE vehicle_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    trip_id INT,
    cost_type ENUM('Fuel','Maintenance','Insurance','Registration','Toll','Driver Pay','Misc') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    cost_date DATE NOT NULL,
    description VARCHAR(255),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes
CREATE INDEX idx_trips_vehicle ON trips(vehicle_id);
CREATE INDEX idx_trips_driver ON trips(driver_id);
CREATE INDEX idx_trips_status ON trips(status);
CREATE INDEX idx_maintenance_vehicle ON maintenance(vehicle_id);
CREATE INDEX idx_fuel_vehicle ON fuel_logs(vehicle_id);
CREATE INDEX idx_costs_vehicle ON vehicle_costs(vehicle_id);

-- Default Admin User (password: Admin@1234)
INSERT INTO users (name, email, password, role) VALUES 
('System Admin', 'admin@fleetflow.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager'),
('Fleet Dispatcher', 'dispatcher@fleetflow.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dispatcher'),
('Safety Officer', 'safety@fleetflow.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Safety Officer'),
('Finance Manager', 'finance@fleetflow.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Finance');

-- Sample Vehicles
INSERT INTO vehicles (license_plate, make, model, year, type, max_capacity, status, odometer) VALUES
('FF-001-A', 'Tata', 'Prima 4038.S', 2021, 'Truck', 25000, 'Available', 45230.5),
('FF-002-B', 'Ashok Leyland', 'Boss 1415', 2020, 'Truck', 15000, 'Available', 78450.0),
('FF-003-C', 'Mahindra', 'Supro', 2022, 'Van', 1500, 'On Trip', 22100.0),
('FF-004-D', 'Toyota', 'HiAce', 2019, 'Van', 1200, 'In Shop', 110200.0),
('FF-005-E', 'Eicher', 'Pro 3015', 2021, 'Truck', 10000, 'Available', 55600.0);

-- Sample Drivers
INSERT INTO drivers (name, email, phone, license_number, license_expiry, license_class, status) VALUES
('Rajesh Kumar', 'rajesh@example.com', '+91-9876543210', 'DL-2021-001234', '2026-06-30', 'HTV', 'On Duty'),
('Suresh Patel', 'suresh@example.com', '+91-9876543211', 'DL-2019-005678', '2024-01-01', 'LTV', 'Off Duty'),
('Amit Singh', 'amit@example.com', '+91-9876543212', 'DL-2020-009012', '2025-12-15', 'HTV', 'On Duty'),
('Deepak Verma', 'deepak@example.com', '+91-9876543213', 'DL-2022-003456', '2027-03-20', 'HTV', 'Off Duty'),
('Manoj Sharma', 'manoj@example.com', '+91-9876543214', 'DL-2018-007890', '2023-08-10', 'LTV', 'Suspended');


INSERT INTO drivers (name, email, phone, license_number, license_expiry, license_class, status) VALUES
('Vikram Joshi', 'vikram@example.com', '+91-9876543215', 'DL-2022-011223', '2026-09-30', 'HTV', 'On Duty'),
('Rahul Mehta', 'rahul@example.com', '+91-9876543216', 'DL-2021-015678', '2025-07-15', 'LTV', 'Off Duty'),
('Nikhil Shah', 'nikhil@example.com', '+91-9876543217', 'DL-2020-021234', '2024-12-31', 'HTV', 'On Duty'),
('Kiran Desai', 'kiran@example.com', '+91-9876543218', 'DL-2023-003789', '2027-05-20', 'LTV', 'Available'),
('Ankit Verma', 'ankit@example.com', '+91-9876543219', 'DL-2021-009876', '2026-11-10', 'HTV', 'Off Duty'),
('Ramesh Thakur', 'ramesh@example.com', '+91-9876543220', 'DL-2022-007654', '2026-03-30', 'LTV', 'Suspended'),
('Manish Patel', 'manish@example.com', '+91-9876543221', 'DL-2023-002345', '2027-01-15', 'HTV', 'On Duty'),
('Sanjay Kumar', 'sanjay@example.com', '+91-9876543222', 'DL-2020-011111', '2025-06-30', 'LTV', 'Available'),
('Pratik Shah', 'pratik@example.com', '+91-9876543223', 'DL-2022-014567', '2026-08-20', 'HTV', 'On Duty'),
('Harsh Mehta', 'harsh@example.com', '+91-9876543224', 'DL-2021-018901', '2025-12-31', 'LTV', 'Off Duty');


INSERT INTO vehicles (license_plate, make, model, year, type, max_capacity, status, odometer) VALUES
('FF-016-P', 'Tata', 'Signa 4018', 2022, 'Truck', 18000, 'Available', 32000.0),
('FF-017-Q', 'Ashok Leyland', 'Captain 1215', 2021, 'Truck', 15000, 'Available', 45000.0),
('FF-018-R', 'Mahindra', 'Jeeto', 2023, 'Van', 1200, 'Available', 15000.0),
('FF-019-S', 'Toyota', 'HiAce', 2022, 'Van', 1400, 'In Shop', 23000.0),
('FF-020-T', 'Eicher', 'Pro 3020', 2023, 'Truck', 10000, 'Available', 12000.0),
('FF-021-U', 'Tata', 'Prima 4039', 2021, 'Truck', 25000, 'On Trip', 56000.0),
('FF-022-V', 'Ashok Leyland', 'Boss 1420', 2022, 'Truck', 16000, 'Available', 33000.0),
('FF-023-W', 'Mahindra', 'Supro Max', 2023, 'Van', 1500, 'Available', 11000.0),
('FF-024-X', 'Toyota', 'HiAce Deluxe', 2021, 'Van', 1300, 'Available', 27000.0),
('FF-025-Y', 'Eicher', 'Pro 3030', 2022, 'Truck', 12000, 'In Shop', 44000.0);