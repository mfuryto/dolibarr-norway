CREATE TABLE IF NOT EXISTS llx_norway_schema (
  version INT(11) NOT NULL,
  checksum CHAR(64) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS llx_norway_tax_evidence (
  rowid BIGINT(20) NOT NULL AUTO_INCREMENT,
  entity INT(11) NOT NULL,
  document_type VARCHAR(32) NOT NULL,
  fk_document INT(11) NOT NULL,
  revision INT(11) NOT NULL,
  captured_at DATETIME NOT NULL,
  fk_user INT(11) NOT NULL,
  mapping_revision INT(11) NOT NULL,
  payload LONGTEXT NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,
  PRIMARY KEY (rowid),
  UNIQUE KEY uk_norway_evidence_revision (entity,document_type,fk_document,revision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
