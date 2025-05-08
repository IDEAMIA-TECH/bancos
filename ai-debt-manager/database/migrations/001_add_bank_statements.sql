-- Crear tabla de estados de cuenta
CREATE TABLE IF NOT EXISTS bank_statements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    statement_date DATE NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'processed', 'error') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_bank (user_id, bank_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar columnas a la tabla de transacciones
ALTER TABLE transactions
ADD COLUMN IF NOT EXISTS user_id INT NOT NULL AFTER id,
ADD COLUMN IF NOT EXISTS bank_statement_id INT NOT NULL AFTER user_id,
ADD COLUMN IF NOT EXISTS is_income BOOLEAN DEFAULT FALSE AFTER category_id;

-- Agregar foreign keys a la tabla de transacciones
ALTER TABLE transactions
ADD CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_transactions_statement FOREIGN KEY (bank_statement_id) REFERENCES bank_statements(id) ON DELETE CASCADE;

-- Agregar columna de patrones a la tabla de categorías
ALTER TABLE categories
ADD COLUMN IF NOT EXISTS patterns JSON NULL COMMENT 'Patrones de texto para reconocimiento automático' AFTER color;

-- Actualizar categorías existentes con patrones
UPDATE categories SET patterns = '["SUPER", "WALMART", "SORIANA", "COMERCIAL", "RESTAURANT", "CAFE"]' WHERE name = 'Alimentación';
UPDATE categories SET patterns = '["UBER", "DIDI", "TAXI", "GASOLINA", "PEMEX", "METRO"]' WHERE name = 'Transporte';
UPDATE categories SET patterns = '["CFE", "TELMEX", "INTERNET", "AGUA", "GAS"]' WHERE name = 'Servicios';
UPDATE categories SET patterns = '["NETFLIX", "SPOTIFY", "CINE", "TEATRO", "CONCIERTO"]' WHERE name = 'Entretenimiento';
UPDATE categories SET patterns = '["FARMACIA", "DOCTOR", "HOSPITAL", "MEDICINA"]' WHERE name = 'Salud';
UPDATE categories SET patterns = '["ESCUELA", "UNIVERSIDAD", "CURSO", "LIBROS"]' WHERE name = 'Educación';
UPDATE categories SET patterns = '["NOMINA", "SALARIO", "QUINCENA"]' WHERE name = 'Salario';
UPDATE categories SET patterns = '["DIVIDENDOS", "INTERESES", "RENDIMIENTO"]' WHERE name = 'Inversiones';
UPDATE categories SET patterns = '["TRANSFERENCIA", "DEPOSITO"]' WHERE name = 'Otros Ingresos'; 