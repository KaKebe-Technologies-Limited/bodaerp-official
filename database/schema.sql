-- ============================================================
-- BodaERP — PostgreSQL schema (minimal for Settings system)
-- Run via: psql -U postgres -d bodaerp -f database/schema.sql
-- ============================================================

-- ── platform_settings (single-row global config) ──────────────
DROP TABLE IF EXISTS platform_settings;
CREATE TABLE platform_settings (
  id                          SERIAL PRIMARY KEY,
  platform_name               VARCHAR(100) NOT NULL DEFAULT 'BodaERP',
  operator_name                VARCHAR(150) NOT NULL DEFAULT 'Kakebe Technologies Limited',
  support_email                VARCHAR(150),
  support_phone                VARCHAR(30),
  default_split_city           SMALLINT NOT NULL DEFAULT 60,
  default_split_association    SMALLINT NOT NULL DEFAULT 26,
  default_split_platform       SMALLINT NOT NULL DEFAULT 14,
  require_2fa                  BOOLEAN NOT NULL DEFAULT true,
  maintenance_mode             BOOLEAN NOT NULL DEFAULT false,
  updated_at                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO platform_settings (platform_name, operator_name, support_email, support_phone, default_split_city, default_split_association, default_split_platform, require_2fa)
VALUES ('BodaERP', 'Kakebe Technologies Limited', 'support@kakebe.tech', '+256 700 000 000', 60, 26, 14, true);

-- ── settings (key-value store for ALL configurable content) ─────
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  id          SERIAL PRIMARY KEY,
  "key"       VARCHAR(100) NOT NULL UNIQUE,         -- e.g., site_name, footer_contact, nav_home, etc.
  value       TEXT NOT NULL,
  value_type  VARCHAR(20) NOT NULL DEFAULT 'text',   -- text, textarea, select, boolean, json
  category    VARCHAR(50) NOT NULL DEFAULT 'general', -- general, navigation, labels, features, appearance
  description VARCHAR(255),                         -- admin help text
  is_feature_flag BOolean NOT NULL DEFAULT false,       -- when true, controls feature enabled/disabled
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Default configurable content (populate from what was previously hardcoded)
INSERT INTO settings ("key", value, value_type, category, description, is_feature_flag, sort_order) VALUES
  -- General site content
  ('site_name', 'BodaERP', 'text', 'general', 'Site name / title', false, 1),
  ('operator_name', 'Kakebe Technologies Limited', 'text', 'general', 'Operator/company name', false, 2),
  ('support_email', 'support@kakebe.tech', 'text', 'general', 'Support email address', false, 3),
  ('support_phone', '+256 700 000 000', 'text', 'general', 'Support phone number', false, 4),
  ('footer_copyright', '&copy; 2026 BodaERP. All rights reserved.', 'text', 'general', 'Footer copyright text', false, 5),
  ('footer_contact', 'support@kakebe.tech', 'text', 'general', 'Footer contact email', false, 6),

  -- Navigation labels
  ('nav_home', 'Home', 'text', 'navigation', 'Home menu label', false, 1),
  ('nav_dashboard', 'Dashboard', 'text', 'navigation', 'Dashboard menu label', false, 2),
  ('nav_riders', 'Riders', 'text', 'navigation', 'Riders menu label', false, 3),
  ('nav_stages', 'Stages', 'text', 'navigation', 'Stages menu label', false, 4),
  ('nav_payments', 'Payments', 'text', 'navigation', 'Payments menu label', false, 5),
  ('nav_settings', 'Settings', 'text', 'navigation', 'Settings menu label', false, 6),
  ('nav_login', 'Login', 'text', 'navigation', 'Login menu label', false, 7),
  ('nav_logout', 'Logout', 'text', 'navigation', 'Logout menu label', false, 8),

  -- Page titles and labels (chairperson)
  ('chairperson_dashboard_title', 'Stage Dashboard', 'text', 'labels', 'Chairperson dashboard page title', false, 1),
  ('chairperson_welcome_text', 'Welcome to your stage dashboard', 'textarea', 'labels', 'Chairperson dashboard welcome message', false, 2),
  ('chairperson_payment_initiated', 'Payment initiated successfully', 'text', 'labels', 'Payment initiation success message', false, 3),

  -- Page titles and labels (city council)
  ('citycouncil_dashboard_title', 'City Council Dashboard', 'text', 'labels', 'City council dashboard page title', false, 1),
  ('citycouncil_welcome_text', 'Welcome to your city dashboard', 'textarea', 'labels', 'City council dashboard welcome message', false, 2),

  -- Page titles and labels (rider)
  ('rider_dashboard_title', 'Rider Dashboard', 'text', 'labels', 'Rider dashboard page title', false, 1),
  ('rider_welcome_text', 'Welcome to your rider dashboard', 'textarea', 'labels', 'Rider dashboard welcome message', false, 2),
  ('rider_payment_history', 'Payment History', 'text', 'labels', 'Payment history section label', false, 3),
  ('rider_id_card', 'ID Card', 'text', 'labels', 'ID Card section label', false, 4),

  -- Page titles and labels (superadmin)
  ('superadmin_dashboard_title', 'Super Admin Dashboard', 'text', 'labels', 'Super admin dashboard page title', false, 1),
  ('superadmin_users_title', 'User Management', 'text', 'labels', 'Users page title', false, 2),
  ('superadmin_cities_title', 'City Management', 'text', 'labels', 'Cities page title', false, 3),

  -- Feature flags
  ('feature_ckeditor5', 'true', 'boolean', 'features', 'Enable CKEditor 5 rich text editor', true, 1),
  ('feature_online_payments', 'true', 'boolean', 'features', 'Enable online payment initiation', true, 2),
  ('feature_ipn_notifications', 'true', 'boolean', 'features', 'Enable ioTec IPN notifications', true, 3),

  -- Appearance
  ('appearance_primary_color', '#0066CC', 'text', 'appearance', 'Primary color for UI', false, 1),
  ('appearance_logo_url', '', 'text', 'appearance', 'Logo URL for header', false, 2);