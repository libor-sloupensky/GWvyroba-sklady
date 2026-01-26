-- Migrace: Přidání sloupce cilovy_stav_calc pro uložení vypočteného cílového stavu
-- Cílový stav = totalDemand z BOM kaskády (před odečtením dostupných zásob)
-- Vztah: dovyrobit = cilovy_stav_calc + rezervace - dostupne

ALTER TABLE produkty
ADD COLUMN cilovy_stav_calc DECIMAL(18,3) NOT NULL DEFAULT 0 AFTER dovyrobit;
