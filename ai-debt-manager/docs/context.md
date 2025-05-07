# 💸 AI Debt Manager – Aplicación de Reducción Inteligente de Deudas

## 📘 Descripción General

**AI Debt Manager** es una aplicación web diseñada para ayudar a los usuarios a **reducir sus deudas financieras** de forma eficiente y estratégica. Mediante la conexión a sus cuentas bancarias (a través de Belvo) y el uso de algoritmos de inteligencia artificial, la plataforma analiza ingresos, gastos y deudas para generar **un plan de pagos personalizado**. El objetivo es ofrecer opciones claras y efectivas como el método 50/30/20 o el método "snowball" para lograr libertad financiera.

## 🎯 Objetivo Principal

Permitir que los usuarios **consoliden y analicen todas sus deudas en un solo lugar**, y que puedan tomar decisiones informadas basadas en su situación económica real para **liquidar sus compromisos en el menor tiempo posible**.

## 🧠 Características Clave

- 🔗 Conexión directa a bancos mexicanos (vía Belvo)
- 📊 Análisis financiero automatizado (ingresos, gastos fijos/variables, imprevistos)
- 🧮 Planificador de pagos inteligente usando IA
- 🧩 Elección del método de pago:
  - **50/30/20** (necesidades, deseos, ahorro/deuda)
  - **Snowball** (de menor a mayor deuda)
- 🗓️ Proyección de pagos por plazo deseado
- 📈 Dashboard financiero con KPIs y recomendaciones
- 🔔 Alertas y recordatorios automáticos

## 🏗️ Tecnologías Utilizadas

| Componente | Tecnología |
|------------|------------|
| Backend | PHP, JavaScript, AJAX, JSON |
| Frontend | HTML, CSS, JavaScript |
| Base de datos | MySQL |
| Integración Bancaria | [Belvo API](https://belvo.com) |
| Reglas de negocio (50/30/20, snowball) | PHP puro |
| Clasificación básica de gastos | php-ai/php-ml o lógica manual |
| Visualización de proyecciones | JavaScript en frontend (Recharts, Chart.js) |
| IA más avanzada (opcional) | API externa (OpenAI) |

## 📁 Estructura de Carpetas

```
ai-debt-manager/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── dashboard.css
│   ├── js/
│   │   ├── main.js
│   │   ├── charts.js
│   │   └── belvo.js
│   └── img/
│       └── icons/
├── config/
│   ├── database.php
│   ├── belvo.php
│   └── config.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── nav.php
│   └── functions.php
├── modules/
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   ├── dashboard/
│   │   ├── index.php
│   │   └── summary.php
│   ├── debts/
│   │   ├── list.php
│   │   └── plan.php
│   ├── bank/
│   │   ├── connect.php
│   │   └── accounts.php
│   └── reports/
│       ├── generate.php
│       └── export.php
├── api/
│   ├── belvo/
│   │   ├── connect.php
│   │   └── webhook.php
│   └── debt/
│       ├── calculate.php
│       └── plan.php
├── vendor/
│   └── [dependencias]
├── .htaccess
├── index.php
└── README.md
```

### Descripción de Carpetas

- **assets/**: Archivos estáticos (CSS, JavaScript, imágenes)
- **config/**: Archivos de configuración
- **includes/**: Componentes reutilizables
- **modules/**: Módulos principales de la aplicación
- **api/**: Endpoints para integraciones
- **vendor/**: Dependencias de terceros

### Características de la Estructura

- **Simple**: Organización clara y directa
- **Modular**: Separación por funcionalidad
- **Mantenible**: Fácil de entender y modificar
- **Escalable**: Permite crecimiento ordenado
- **Segura**: Separación de archivos sensibles

## 💾 Estructura de la Base de Datos

### Tablas Principales

#### users
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);
```

#### bank_connections
```sql
CREATE TABLE bank_connections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    institution_id VARCHAR(100) NOT NULL,
    belvo_link_id VARCHAR(255) NOT NULL,
    status ENUM('active', 'expired', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_sync TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### accounts
```sql
CREATE TABLE accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bank_connection_id INT NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_type ENUM('checking', 'savings', 'credit', 'loan') NOT NULL,
    balance DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'MXN',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_connection_id) REFERENCES bank_connections(id) ON DELETE CASCADE
);
```

#### transactions
```sql
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    category_id INT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

#### categories
```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    parent_id INT NULL,
    is_system BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (parent_id) REFERENCES categories(id)
);
```

#### debts
```sql
CREATE TABLE debts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    original_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    start_date DATE NOT NULL,
    due_date DATE,
    status ENUM('active', 'paid', 'defaulted') DEFAULT 'active',
    payment_method ENUM('50_30_20', 'snowball') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
);
```

#### payment_plans
```sql
CREATE TABLE payment_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    debt_id INT NOT NULL,
    monthly_payment DECIMAL(15,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE
);
```

#### notifications
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('payment_due', 'low_balance', 'plan_update', 'system') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Índices y Relaciones

- Índices en campos de búsqueda frecuente (email, account_number)
- Índices en campos de ordenamiento (transaction_date, created_at)
- Relaciones con eliminación en cascada donde es apropiado
- Restricciones de integridad referencial

### Consideraciones de Seguridad

- Encriptación de datos sensibles (password_hash)
- Registro de auditoría para cambios críticos
- Separación de datos por usuario
- Validación de datos a nivel de base de datos

## 🔐 Seguridad y Privacidad

- Toda la comunicación es encriptada (HTTPS, TLS)
- Los datos sensibles se almacenan cifrados (AES-256)
- Cumplimiento con regulaciones locales de protección de datos
- Consentimiento explícito del usuario para conectar sus bancos

## 👥 Tipos de Usuario

- **Usuario final**: Conecta sus cuentas bancarias, visualiza sus deudas, define su método de pago preferido y sigue el plan generado por el sistema.
- **Administrador**: Gestiona usuarios, monitorea conexiones y puede ajustar parámetros de cálculo o estrategias IA.

## 📌 Módulos del Sistema

1. **Autenticación de Usuario**
2. **Conexión Bancaria (Belvo Connect)**
3. **Módulo de Consolidación de Deudas**
4. **Análisis de Ingresos y Gastos**
5. **Selector de Estrategia (50/30/20 o Snowball)**
6. **Planificador de Pagos IA**
7. **Proyección Visual de Pagos**
8. **Notificaciones y Alertas**
9. **Dashboard General de Finanzas**
10. **Historial y Reportes PDF/Excel**

## 📈 Algoritmos y Modelos de IA

- Clasificación automática de gastos (alimentación, renta, entretenimiento, etc.)
- Modelo de predicción de liquidez mensual
- Generador de escenarios de pagos basado en inputs:
  - Monto total de deuda
  - Tasas de interés
  - Plazo deseado para pago
  - Método seleccionado por el usuario

## 🚀 Fases de Desarrollo

| Fase | Entregable |
|------|------------|
| 1 | Conexión con Belvo + Login básico |
| 2 | Visualización de transacciones y deudas |
| 3 | Implementación del motor de planificación IA |
| 4 | UI de selección de estrategia y plazo |
| 5 | Generación de reportes y alertas |
| 6 | Publicación beta y retroalimentación de usuarios |

## 📬 Contacto del Proyecto

- **Empresa**: IDEAMIA TECH
- **Responsable**: Ing. Jorge Plascencia
- **Correo**: soporte@ideamia.com.mx
- **Sitio web**: [www.ideamia.com.mx](https://www.ideamia.com.mx)