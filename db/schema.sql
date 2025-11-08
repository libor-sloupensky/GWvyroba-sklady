-- MySQL schema (utf8mb4, czech collation)
SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  active TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS kontakty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NULL,
  telefon VARCHAR(50) NULL,
  firma VARCHAR(255) NULL,
  jmeno VARCHAR(255) NULL,
  ulice VARCHAR(255) NULL,
  mesto VARCHAR(255) NULL,
  psc VARCHAR(32) NULL,
  zeme VARCHAR(64) NULL,
  ic VARCHAR(32) NULL,
  dic VARCHAR(32) NULL,
  KEY idx_kontakty_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS doklady_eshop (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eshop_source VARCHAR(64) NOT NULL,
  cislo_dokladu VARCHAR(128) NOT NULL,
  typ_dokladu VARCHAR(64) NULL,
  platba_typ VARCHAR(64) NULL,
  dopravce_ids VARCHAR(255) NULL,
  cislo_objednavky VARCHAR(128) NULL,
  sym_var VARCHAR(128) NULL,
  datum_vystaveni DATE NULL,
  duzp DATE NULL,
  splatnost DATE NULL,
  mena_puvodni VARCHAR(16) NULL,
  kurz_na_czk DECIMAL(18,6) NULL,
  kontakt_id INT NULL,
  import_batch_id VARCHAR(32) NOT NULL,
  import_ts DATETIME NOT NULL,
  UNIQUE KEY uniq_doc (eshop_source, cislo_dokladu),
  KEY idx_duzp (duzp),
  KEY idx_order (cislo_objednavky),
  KEY idx_sym (sym_var),
  KEY idx_kid (kontakt_id),
  CONSTRAINT fk_doc_kontakt FOREIGN KEY (kontakt_id) REFERENCES kontakty(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS polozky_eshop (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eshop_source VARCHAR(64) NOT NULL,
  cislo_dokladu VARCHAR(128) NOT NULL,
  code_raw VARCHAR(128) NULL,
  stock_ids_raw VARCHAR(128) NULL,
  sku VARCHAR(128) NULL,
  ean VARCHAR(32) NULL,
  nazev VARCHAR(512) NULL,
  mnozstvi DECIMAL(18,3) NOT NULL,
  merna_jednotka VARCHAR(16) NULL,
  cena_jedn_mena DECIMAL(18,4) NULL,
  cena_jedn_czk DECIMAL(18,4) NULL,
  mena_puvodni VARCHAR(16) NULL,
  sazba_dph_hint VARCHAR(32) NULL,
  plati_dph TINYINT(1) NULL,
  sleva_procento DECIMAL(9,4) NULL,
  duzp DATE NOT NULL,
  import_batch_id VARCHAR(32) NOT NULL,
  KEY idx_polozky_doc (eshop_source, cislo_dokladu),
  KEY idx_polozky_sku (sku),
  KEY idx_polozky_duzp (duzp),
  CONSTRAINT fk_pol_doc FOREIGN KEY (eshop_source, cislo_dokladu) REFERENCES doklady_eshop(eshop_source, cislo_dokladu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS produkty_znacky (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nazev VARCHAR(128) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS produkty_skupiny (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nazev VARCHAR(128) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS produkty_merne_jednotky (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kod VARCHAR(16) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS produkty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(128) NOT NULL UNIQUE,
  alt_sku VARCHAR(128) NULL UNIQUE,
  ean VARCHAR(32) NULL UNIQUE,
  nazev VARCHAR(255) NOT NULL,
  typ ENUM('produkt','obal','etiketa','surovina','baleni','karton') NOT NULL,
  merna_jednotka VARCHAR(16) NOT NULL,
  min_zasoba DECIMAL(18,3) NOT NULL DEFAULT 0,
  min_davka DECIMAL(18,3) NOT NULL DEFAULT 0,
  krok_vyroby DECIMAL(18,3) NOT NULL DEFAULT 0,
  vyrobni_doba_dni INT NOT NULL DEFAULT 0,
  aktivni TINYINT(1) NOT NULL DEFAULT 1,
  znacka_id INT NULL,
  skupina_id INT NULL,
  poznamka VARCHAR(1024) NULL,
  KEY idx_produkty_typ (typ),
  KEY idx_produkty_znacka (znacka_id),
  KEY idx_produkty_skupina (skupina_id),
  CONSTRAINT fk_produkty_znacka FOREIGN KEY (znacka_id) REFERENCES produkty_znacky(id) ON DELETE SET NULL,
  CONSTRAINT fk_produkty_skupina FOREIGN KEY (skupina_id) REFERENCES produkty_skupiny(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS bom (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rodic_sku VARCHAR(128) NOT NULL,
  potomek_sku VARCHAR(128) NOT NULL,
  koeficient DECIMAL(18,6) NOT NULL,
  merna_jednotka_potomka VARCHAR(16) NULL,
  druh_vazby ENUM('karton','sada') NOT NULL,
  KEY idx_bom_rodic (rodic_sku),
  KEY idx_bom_potomek (potomek_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS polozky_pohyby (
  id INT AUTO_INCREMENT PRIMARY KEY,
  datum DATETIME NOT NULL,
  sku VARCHAR(128) NOT NULL,
  mnozstvi DECIMAL(18,3) NOT NULL,
  merna_jednotka VARCHAR(16) NULL,
  typ_pohybu ENUM('inventura','vyroba','korekce','odpis') NOT NULL,
  poznamka VARCHAR(1024) NULL,
  ref_id VARCHAR(64) NULL,
  KEY idx_pohyby_sku (sku),
  KEY idx_pohyby_datum (datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS rezervace (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(128) NOT NULL,
  mnozstvi DECIMAL(18,3) NOT NULL,
  platna_do DATE NOT NULL,
  poznamka VARCHAR(512) NULL,
  KEY idx_rez_sku (sku),
  KEY idx_rez_platnost (platna_do)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS nastaveni_rady (
  id INT AUTO_INCREMENT PRIMARY KEY,
  eshop_source VARCHAR(64) NOT NULL,
  prefix VARCHAR(32) NOT NULL,
  cislo_od VARCHAR(32) NOT NULL,
  cislo_do VARCHAR(32) NOT NULL,
  UNIQUE KEY uniq_rady_eshop (eshop_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS nastaveni_ignorovane_polozky (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vzor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS nastaveni_global (
  id TINYINT PRIMARY KEY,
  okno_pro_prumer_dni INT NOT NULL DEFAULT 30,
  mena_zakladni VARCHAR(8) NOT NULL DEFAULT 'CZK',
  zaokrouhleni VARCHAR(16) NOT NULL DEFAULT 'half_up',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT IGNORE INTO nastaveni_global (id) VALUES (1);
