-- 2026-10-22: přesnost množství na 6 desetinných míst kvůli přechodu surovin z g na kg
-- (koeficient 0,0202 kg × 1 ks se v DECIMAL(18,3) zaokrouhlil na 0,020).
-- Na produkci provedeno 2026-10-22, počty i součty ověřeny před/po, stavy skladu beze změny.
ALTER TABLE polozky_pohyby MODIFY mnozstvi DECIMAL(18,6) NOT NULL;
ALTER TABLE rezervace MODIFY mnozstvi DECIMAL(18,6) NOT NULL;
ALTER TABLE sklad_doklady_log MODIFY stare_mnozstvi DECIMAL(18,6) NULL, MODIFY nove_mnozstvi DECIMAL(18,6) NULL;
