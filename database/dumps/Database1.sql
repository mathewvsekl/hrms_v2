-- HRMS V2 GLOBAL SCHEMA
-- Optimized for Multi-Country, Multi-Currency, and Multi-Timezone

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. LEGAL ENTITIES & OFFICES
CREATE TABLE legal_entities (
    entity_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    legal_name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(100),
    base_currency CHAR(3) DEFAULT 'USD',
    fiscal_year_start DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE offices (
    office_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    entity_id UUID REFERENCES legal_entities(entity_id),
    office_name VARCHAR(100) NOT NULL,
    country_code CHAR(2) NOT NULL, -- ISO 3166-1 (e.g., 'AE', 'KE', 'IN')
    timezone VARCHAR(50) NOT NULL, -- (e.g., 'Asia/Dubai', 'Africa/Nairobi')
    address_json JSONB,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. DYNAMIC CONFIGURATION (The "Sofia" Logic)
CREATE TABLE custom_field_definitions (
    field_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    office_id UUID REFERENCES offices(office_id),
    module_context VARCHAR(50), -- 'ONBOARDING', 'PAYROLL', 'LEAVE'
    field_name VARCHAR(100) NOT NULL, -- e.g., 'KRA_PIN', 'Emirates_ID'
    field_type VARCHAR(20) DEFAULT 'text', -- 'text', 'number', 'date', 'file'
    is_required BOOLEAN DEFAULT FALSE,
    validation_regex TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. EMPLOYEES & ASSIGNMENTS
CREATE TABLE employees (
    employee_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    system_role VARCHAR(50) DEFAULT 'EMPLOYEE', -- RBAC defined
    joined_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'ACTIVE', -- 'ACTIVE', 'ONBOARDING', 'EXITED'
    -- The "Secret Sauce": Stores office-specific IDs like PAN, TIN, etc.
    custom_values JSONB DEFAULT '{}', 
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employee_office_assignment (
    assignment_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    employee_id UUID REFERENCES employees(employee_id),
    office_id UUID REFERENCES offices(office_id),
    reporting_manager_id UUID REFERENCES employees(employee_id), -- Matrix Reporting
    job_title VARCHAR(100),
    is_primary BOOLEAN DEFAULT TRUE,
    start_date DATE NOT NULL,
    end_date DATE,
    payroll_currency CHAR(3) NOT NULL,
    is_attendance_required BOOLEAN DEFAULT TRUE
);

-- 4. ATTENDANCE & LOGS (The "Liam" Logic)
CREATE TABLE attendance_logs (
    log_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    employee_id UUID REFERENCES employees(employee_id),
    office_id UUID REFERENCES offices(office_id),
    check_in TIMESTAMP WITH TIME ZONE NOT NULL, -- Normalized to UTC
    check_out TIMESTAMP WITH TIME ZONE,
    source VARCHAR(50), -- 'MOBILE', 'BIOMETRIC', 'MANUAL'
    metadata JSONB -- Geolocation or IP
);

-- 5. AUDIT & TRACEABILITY (The "Oscar" Logic)
CREATE TABLE audit_logs (
    audit_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_agent_id VARCHAR(100), -- The agent name (e.g., 'eva_payroll')
    action_type VARCHAR(50), -- 'INSERT', 'UPDATE', 'DELETE'
    table_name VARCHAR(50),
    record_id UUID,
    old_value JSONB,
    new_value JSONB,
    timestamp TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES FOR PERFORMANCE
CREATE INDEX idx_employee_custom_values ON employees USING GIN (custom_values);
CREATE INDEX idx_attendance_utc ON attendance_logs (check_in);