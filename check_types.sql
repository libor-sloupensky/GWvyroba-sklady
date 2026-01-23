-- Zjistit všechny používané typy produktů
SELECT DISTINCT typ FROM produkty WHERE typ IS NOT NULL AND typ != '' ORDER BY typ;

-- Zjistit, co je v tabulce product_types
SELECT * FROM product_types;

-- Zjistit produkty typu karton (nonstock produkty s BOM)
SELECT p.sku, p.typ, p.nazev,
       (SELECT COUNT(*) FROM bom WHERE rodic_sku = p.sku) as pocet_potomku
FROM produkty p
WHERE p.typ = 'karton'
LIMIT 20;
