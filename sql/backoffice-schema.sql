-- Wujud ERP — Backoffice Svashta Home — Fase 2 (backend real)
-- Database: svashtahome_svashta_procurement
-- Model: User (global identity) x Organization (tenant terisolasi) x Role (per-org)
-- lewat UserOrganizationRole. Lihat wujud-erp/DOKUMENTASI_ARSITEKTUR.md bagian 3.

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  photo VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE organizations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legal_name VARCHAR(200) NOT NULL,
  npwp VARCHAR(30) NULL,
  address TEXT NULL,
  logo VARCHAR(255) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'IDR',
  document_prefix VARCHAR(20) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Role bawaan sistem (organization_id NULL) ATAU custom per organisasi.
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  is_owner_role TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Matrix hak akses granular per role, per modul (module_key lihat backoffice-shared/modules.php).
CREATE TABLE role_module_access (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_create TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  can_print TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_role_module (role_id, module_key)
) ENGINE=InnoDB;

-- Simpul keanggotaan: 1 baris = 1 user jadi anggota 1 organisasi dengan 1 role.
CREATE TABLE user_organization_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  organization_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_membership (user_id, organization_id)
) ENGINE=InnoDB;
