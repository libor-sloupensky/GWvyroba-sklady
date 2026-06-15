-- Migrace: castka_celkem na tržby BEZ DPH (po slevě)
-- ====================================================
-- Důvod: import dříve ukládal do castka_celkem hodnotu z priceLowSum, což je částka
-- VČETNĚ DPH (a jen za sníženou sazbu). Měsíční tržby (SUM(castka_celkem)) tím byly
-- nadhodnocené o DPH. Nově se castka_celkem = součet základů položek bez DPH, po slevě.
--
-- POZOR – MĚNA (Fáze 2): u EUR dokladů (grig.sk, případně EUR objednávky na gogrig.com)
-- je cena_jedn_czk v původní měně, takže výsledek bude v EUR, NE v CZK. Převod EUR→CZK
-- (přes uložený kurz nebo ČNB kurz dle DUZP) řeší samostatný skript Fáze 2. Tato migrace
-- opravuje pouze DPH + slevu; EUR řádky zůstanou dočasně v původní měně.
--
-- Spustit v phpMyAdmin. NEJDŘÍV si pusť krok 1 (kontrola), pak krok 2 (UPDATE), pak krok 3.

-- 1) KONTROLA PŘED migrací: kde se castka_celkem nejvíc liší od součtu položek bez DPH
--    (rozdíl ~= DPH; u EUR dokladů uvidíš i měnový nesoulad)
SELECT
    de.eshop_source,
    de.cislo_dokladu,
    de.mena_puvodni,
    de.castka_celkem AS castka_celkem_pred,
    ROUND(SUM(COALESCE(pe.cena_jedn_czk,0) * COALESCE(pe.mnozstvi,0)
              * (1 - COALESCE(pe.sleva_procento,0)/100)), 2) AS bez_dph_po_sleve,
    ROUND(de.castka_celkem
          - SUM(COALESCE(pe.cena_jedn_czk,0) * COALESCE(pe.mnozstvi,0)
                * (1 - COALESCE(pe.sleva_procento,0)/100)), 2) AS rozdil
FROM doklady_eshop de
JOIN polozky_eshop pe
  ON pe.eshop_source = de.eshop_source AND pe.cislo_dokladu = de.cislo_dokladu
GROUP BY de.eshop_source, de.cislo_dokladu, de.mena_puvodni, de.castka_celkem
ORDER BY ABS(rozdil) DESC
LIMIT 50;

-- 2) MIGRACE: castka_celkem = součet položek bez DPH, po slevě
UPDATE doklady_eshop de
SET de.castka_celkem = (
    SELECT ROUND(SUM(COALESCE(pe.cena_jedn_czk,0) * COALESCE(pe.mnozstvi,0)
                     * (1 - COALESCE(pe.sleva_procento,0)/100)), 2)
    FROM polozky_eshop pe
    WHERE pe.eshop_source = de.eshop_source
      AND pe.cislo_dokladu = de.cislo_dokladu
);

-- 3) OVĚŘENÍ PO migraci na vzorových dokladech (mají vyjít „Cena bez DPH" z faktury):
--    2026900227 → 18755 | 2026900009 → 8115 | 2026000044 → 338.39
--    2026000046 → ~961.03 (sleva 5 %) | 3260146 → 1521.43 | 7770000302 → 1730.66
--    1126000008 → 24.41 (EUR! převede Fáze 2) | 2026900108 → 4880.20 nebo 118613.26 (dle uložené měny)
SELECT eshop_source, cislo_dokladu, mena_puvodni, castka_celkem
FROM doklady_eshop
WHERE cislo_dokladu IN ('2026900227','2026900009','2026000044','2026000046',
                        '3260146','7770000302','1126000008','2026900108')
ORDER BY cislo_dokladu;
