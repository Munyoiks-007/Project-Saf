-- =====================================================
-- MOJO_DB Database Schema
-- Database: mojo_db
-- Created for: Project-Saf Invoice & Quotation System
-- =====================================================

-- Drop existing database if needed (BACKUP YOUR DATA FIRST!)
-- DROP DATABASE IF EXISTS mojo_db;

-- Create database
CREATE DATABASE IF NOT EXISTS mojo_db 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE mojo_db;

-- =====================================================
-- Table: users
-- Purpose: Authentication and user management
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: clients
-- Purpose: Store client/company information
-- =====================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    tax_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_company (user_id, company_name),
    INDEX idx_user_id (user_id),
    INDEX idx_company_name (company_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: invoices
-- Purpose: Store invoice headers
-- =====================================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    invoice_no VARCHAR(50) NOT NULL UNIQUE,
    client_name VARCHAR(150) NOT NULL,
    client_email VARCHAR(100),
    client_phone VARCHAR(20),
    client_address TEXT,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(12, 2) DEFAULT 0.00,
    tax_rate DECIMAL(5, 2) DEFAULT 16.00,
    tax_amount DECIMAL(12, 2) DEFAULT 0.00,
    total DECIMAL(12, 2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_invoice_no (invoice_no),
    INDEX idx_user_id (user_id),
    INDEX idx_client_name (client_name),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: invoice_items
-- Purpose: Store individual line items for invoices
-- =====================================================
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    item VARCHAR(150),
    description TEXT,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) DEFAULT 'pcs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: quotations
-- Purpose: Store quotations (basic version)
-- =====================================================
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    client_id INT,
    quotation_no VARCHAR(50) NOT NULL UNIQUE,
    valid_until DATE,
    subtotal DECIMAL(12, 2) DEFAULT 0.00,
    tax DECIMAL(12, 2) DEFAULT 0.00,
    total DECIMAL(12, 2) DEFAULT 0.00,
    status VARCHAR(20) DEFAULT 'draft',
    notes TEXT,
    invoice_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    UNIQUE KEY unique_quotation_no (quotation_no),
    INDEX idx_user_id (user_id),
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_valid_until (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: quotation_items
-- Purpose: Store line items for quotations (basic version)
-- =====================================================
CREATE TABLE IF NOT EXISTS quotation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NOT NULL,
    item VARCHAR(150),
    description TEXT,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    INDEX idx_quotation_id (quotation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: quotes
-- Purpose: Store detailed quotes (enhanced version)
-- =====================================================
CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_no VARCHAR(50) NOT NULL UNIQUE,
    quote_date DATE NOT NULL,
    client_ref VARCHAR(100),
    client_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    client_email VARCHAR(100),
    client_phone VARCHAR(20) NOT NULL,
    project_address TEXT NOT NULL,
    valid_until DATE,
    project_start_date DATE,
    project_duration INT,
    quote_status VARCHAR(20) DEFAULT 'draft',
    priority_level VARCHAR(20) DEFAULT 'medium',
    subtotal DECIMAL(12, 2) DEFAULT 0.00,
    discount DECIMAL(12, 2) DEFAULT 0.00,
    tax_amount DECIMAL(12, 2) DEFAULT 0.00,
    total DECIMAL(12, 2) DEFAULT 0.00,
    vat_percentage DECIMAL(5, 2) DEFAULT 0.00,
    apply_tax TINYINT(1) DEFAULT 0,
    deposit_percentage DECIMAL(5, 2) DEFAULT 0.00,
    pricing_notes TEXT,
    payment_terms VARCHAR(50) DEFAULT '30_70',
    warranty_months INT DEFAULT 0,
    special_terms TEXT,
    scope_of_work TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_quote_no (quote_no),
    INDEX idx_client_name (client_name),
    INDEX idx_quote_status (quote_status),
    INDEX idx_quote_date (quote_date),
    INDEX idx_valid_until (valid_until),
    INDEX idx_created_at (created_at),
    INDEX idx_priority_level (priority_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: quotation_items (for quotes table)
-- Purpose: Store line items for detailed quotes
-- =====================================================
CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    item_name VARCHAR(150),
    description TEXT,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Optional: Add this table for better quote items organization
-- Purpose: Alternative naming convention for quote items
-- =====================================================
CREATE TABLE IF NOT EXISTS quotation_items_extended (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    item_name VARCHAR(150),
    description TEXT,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    specifications TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Create Indexes for Better Performance
-- =====================================================

ALTER TABLE invoices ADD INDEX idx_invoice_total (total);
ALTER TABLE invoices ADD INDEX idx_invoice_date_range (invoice_date, due_date);
ALTER TABLE invoice_items ADD INDEX idx_item_total (total);
ALTER TABLE quotations ADD INDEX idx_quotation_total (total);
ALTER TABLE quotation_items ADD INDEX idx_item_quantity (quantity);
ALTER TABLE quotes ADD INDEX idx_quote_total (total);
ALTER TABLE clients ADD INDEX idx_client_email (email);

-- =====================================================
-- Sample Data (Optional - Remove if not needed)
-- =====================================================

-- Insert a test user
INSERT INTO users (username, password_hash, email, full_name) 
VALUES (
    'admin',
    '$2y$10$YIjlrBAs.Mk4HnGakqx8/.RGmBEGhJmvJ7r/W2g4rMOp0UQRFc6Jq',
    'admin@safinance.com',
    'Administrator'
) ON DUPLICATE KEY UPDATE username=username;

-- =====================================================
-- End of Schema
-- =====================================================
