CREATE TABLE IF NOT EXISTS llx_norway_manual_vat (
  entity INT(11) NOT NULL,
  fk_bookkeeping INT(11) NOT NULL,
  tax_code VARCHAR(2) NOT NULL,
  tax_base DECIMAL(24,8) NOT NULL,
  source_hash CHAR(64) NOT NULL,
  fk_user INT(11) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (entity,fk_bookkeeping)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
