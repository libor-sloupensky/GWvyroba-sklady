-- Migrace: Přidání sloupce castka_celkem do doklady_eshop
-- Spusťte tento script v phpMyAdmin

-- 1. Přidat sloupec (pokud ještě neexistuje)
ALTER TABLE doklady_eshop
ADD COLUMN IF NOT EXISTS castka_celkem DECIMAL(18,2) NULL
COMMENT 'Celková částka faktury bez DPH v CZK'
AFTER kurz_na_czk;

-- 2. Doplnit celkovou částku do všech existujících faktur
-- (součet cena_jedn_czk * mnozstvi pro všechny položky faktury)
UPDATE doklady_eshop de
SET castka_celkem = (
    SELECT ROUND(SUM(COALESCE(pe.cena_jedn_czk, 0) * COALESCE(pe.mnozstvi, 0)), 2)
    FROM polozky_eshop pe
    WHERE pe.eshop_source = de.eshop_source
      AND pe.cislo_dokladu = de.cislo_dokladu
)
WHERE castka_celkem IS NULL;

-- 3. Ověření - počet faktur s vyplněnou částkou
SELECT
    COUNT(*) as celkem_faktur,
    SUM(CASE WHEN castka_celkem IS NOT NULL THEN 1 ELSE 0 END) as s_castkou,
    SUM(CASE WHEN castka_celkem IS NULL THEN 1 ELSE 0 END) as bez_castky
FROM doklady_eshop;
