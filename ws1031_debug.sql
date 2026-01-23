-- DIAGNOSTICKÉ DOTAZY PRO WS1031

-- 1. Informace o produktu WS1031 a jeho typu
SELECT p.sku, p.typ, pt.is_nonstock, p.merna_jednotka, pt.nazev as typ_nazev
FROM produkty p
LEFT JOIN product_types pt ON pt.code = p.typ
WHERE p.sku = 'WS1031';

-- 2. BOM potomci pro WS1031
SELECT b.rodic_sku, b.potomek_sku, b.koeficient,
       COALESCE(NULLIF(b.merna_jednotka_potomka, ''), NULL) AS edge_unit,
       p.merna_jednotka
FROM bom b
LEFT JOIN produkty p ON p.sku = b.potomek_sku
WHERE b.rodic_sku = 'WS1031';

-- 3. PRO SROVNÁNÍ: Informace o produktu WS0232 (který funguje správně)
SELECT p.sku, p.typ, pt.is_nonstock, p.merna_jednotka, pt.nazev as typ_nazev
FROM produkty p
LEFT JOIN product_types pt ON pt.code = p.typ
WHERE p.sku = 'WS0232';

-- 4. BOM potomci pro WS0232
SELECT b.rodic_sku, b.potomek_sku, b.koeficient,
       COALESCE(NULLIF(b.merna_jednotka_potomka, ''), NULL) AS edge_unit,
       p.merna_jednotka
FROM bom b
LEFT JOIN produkty p ON p.sku = b.potomek_sku
WHERE b.rodic_sku = 'WS0232';

-- 5. Co bylo skutečně naimportováno pro WS1031 na dokladu 2026900018
SELECT * FROM polozky_eshop
WHERE eshop_source = 'gogrig.com' AND cislo_dokladu = '2026900018' AND sku = 'WS1031';

-- 6. Jaké pohyby byly vytvořeny pro doklad 2026900018
SELECT * FROM polozky_pohyby
WHERE ref_id LIKE 'doc:gogrig.com:2026900018:%'
ORDER BY sku;
