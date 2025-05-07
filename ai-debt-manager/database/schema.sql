-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS ideamiadev_deudas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ideamiadev_deudas;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bank Connections table
CREATE TABLE IF NOT EXISTS bank_connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bank_id VARCHAR(50) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    credentials TEXT NOT NULL,
    last_sync DATETIME NULL,
    last_error TEXT NULL,
    last_error_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_bank (user_id, bank_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Accounts table
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_connection_id INT NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_type VARCHAR(50) NOT NULL,
    balance DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'MXN',
    last_sync DATETIME NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    FOREIGN KEY (bank_connection_id) REFERENCES bank_connections(id),
    UNIQUE KEY uk_connection_account (bank_connection_id, account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    category_id INT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_account_date (account_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#000000',
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uk_user_category (user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Debts table
CREATE TABLE IF NOT EXISTS debts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active', 'paid', 'defaulted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Debt Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    debt_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    details JSON,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit Logs table
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details JSON,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Webhook Logs table
CREATE TABLE webhook_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sync Logs table
CREATE TABLE sync_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    connection_id INT NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    status ENUM('success', 'error') NOT NULL,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (connection_id) REFERENCES bank_connections(id) ON DELETE CASCADE
);

-- System Settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (id, name, type, icon, color) VALUES
('food', 'Alimentación', 'expense', 'utensils', '#FF5733'),
('rent', 'Renta', 'expense', 'home', '#33FF57'),
('utilities', 'Servicios', 'expense', 'bolt', '#3357FF'),
('transportation', 'Transporte', 'expense', 'car', '#F3FF33'),
('entertainment', 'Entretenimiento', 'expense', 'film', '#FF33F3'),
('shopping', 'Compras', 'expense', 'shopping-cart', '#33FFF3'),
('health', 'Salud', 'expense', 'heartbeat', '#FF3333'),
('education', 'Educación', 'expense', 'graduation-cap', '#3333FF'),
('personal_care', 'Cuidado Personal', 'expense', 'cut', '#FF9933'),
('salary', 'Salario', 'income', 'money-bill', '#33FF99'),
('investment', 'Inversiones', 'income', 'chart-line', '#9933FF'),
('other_income', 'Otros Ingresos', 'income', 'plus-circle', '#FF3399');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'AI Debt Manager', 'string', 'Nombre de la aplicación'),
('app_description', 'Sistema inteligente de gestión de deudas', 'string', 'Descripción de la aplicación'),
('maintenance_mode', 'false', 'boolean', 'Modo mantenimiento'),
('password_min_length', '8', 'number', 'Longitud mínima de contraseña'),
('require_2fa', 'false', 'boolean', 'Requerir autenticación de dos factores'),
('session_timeout', '3600', 'number', 'Tiempo de sesión en segundos'),
('email_notifications', 'true', 'boolean', 'Habilitar notificaciones por email'),
('push_notifications', 'true', 'boolean', 'Habilitar notificaciones push'),
('notification_frequency', 'daily', 'string', 'Frecuencia de notificaciones'),
('ai_model', 'default', 'string', 'Modelo de IA a utilizar'),
('prediction_confidence', '0.8', 'number', 'Confianza mínima para predicciones'),
('auto_categorization', 'true', 'boolean', 'Categorización automática de transacciones');

-- Índices
CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_status ON users(status); 