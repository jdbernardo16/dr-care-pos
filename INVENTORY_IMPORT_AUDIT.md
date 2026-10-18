# Inventory Import Audit Report

**Generated:** 2026-07-17  
**Source:** INVENTORY COST UPDATED.xlsx (1,935 items)  
**Output:** inventory_for_import.csv  
**Currency:** Philippine Peso (₱)

---

## Summary

| Metric | Count |
|---|---|
| Total items | 1,935 |
| Categories assigned | 17 (1 remains "General" — crocheted table cloth) |
| Items needing manual cost review | ~22 |
| Items needing form/unit review | ~200 (liquids w/o keyword) |
| Zero stock (flagged) | 497 |
| Negative stock (fixed to 0) | 476 |
| Missing costs (set to 0) | 17 |
| Missing prices (calculated at cost×1.3) | 6 |

---

## Category Distribution

| Category | Items | Examples |
|---|---|---|
| Personal Care | 390 | Soaps, deodorants, colognes, toothpaste, napkins, condoms, contraceptives, mouthwash, skin care, alcohol |
| Vitamins & Supplements | 238 | Ascorbic acid, multivitamins, B-complex, fish oil, zinc, probiotics, colostrum |
| Food & Beverages | 228 | Milk, noodles, biscuits, chips, chocolate, coffee, water, juice, candy |
| Heart Health | 172 | Amlodipine, Losartan, Atorvastatin, Metoprolol, Clopidogrel, Aspirin |
| Cough & Cold | 140 | Carbocisteine, Ambroxol, Salbutamol, Dextromethorphan, combination syrups |
| Pain Relief | 109 | Paracetamol, Ibuprofen, Mefenamic acid, Celecoxib, Tramadol, Eperisone |
| Skin Care | 98 | Mupirocin, Clotrimazole, Hydrocortisone, Benzoyl Peroxide, Tretinoin |
| Digestive Health | 92 | Omeprazole, Domperidone, Hyoscine, Loperamide, Antacids, Lactulose |
| Baby & Child Care | 91 | Diapers, baby baths, powders, milks, pediatric syrups |
| Antibiotics | 87 | Amoxicillin, Cefalexin, Azithromycin, Cloxacillin, Erythromycin |
| First Aid | 82 | Betadine, Gauze, Bandage, Micropore tape, Alcohol pads, Cotton |
| Allergy & Sinus | 74 | Cetirizine, Loratadine, Montelukast, Prednisone, Dexamethasone |
| Medical Devices & Consumables | 56 | Syringes, BP monitor, Nebulizer, Thermometer, Urine bag, Hot water bag |
| Diabetes Care | 44 | Metformin, Gliclazide, Glimepiride, Sitagliptin, Glucometer |
| General (Stationery) | 19 | Ballpen, Notebook, Bondpaper, Crayola, Envelope |
| Eye & Ear Care | 15 | Tobramycin, Ofloxacin, Timolol, Artificial Tears |
| General | 1 | Gantsilyo (Table Cloth) — genuine miscellaneous |

---

## Critical: Price Below Cost (Possible Data Error)

These items have a selling price **lower** than cost, resulting in negative margin. The source Excel likely has incorrect values.

| Item | Cost (₱) | Price (₱) | Margin | Likely Issue |
|---|---|---|---|---|
| ASPILET 80mg (Aspirin) tablet | 39.88 | 5.00 | -87% | Cost decimal error (should be ~₱0.40) |
| MYRA HYDRA. PLUS 100ML LOTION | 878.30 | 115.00 | -87% | Cost entered incorrectly |
| CALPOL 250mg/60ml (Paracetamol) | 197.55 | 159.00 | -20% | Cost may be wrong |
| ANMUM CHOCO 375g | 391.02 | 391.00 | -0% | Rounding difference (₱0.02) |
| FAKTU SUPPOSITORY | 77.41 | 77.00 | -1% | Rounding difference (₱0.41) |

**Action:** Verify costs for ASPILET 80mg, MYRA HYDRA LOTION, and CALPOL against supplier invoices.

---

## Missing Cost (Price Exists, Cost is ₱0)

These 17 items have a selling price set but no cost. Import will set COGS to ₱0 until updated.

| Item | Price (₱) | Notes |
|---|---|---|
| AMBROXYL 30MG | 7.00 | Pharmacy item — needs cost lookup |
| ANMUM PLAIN 180G | 251.00 | Food item |
| BABY BOTTLE 5'OZ(BPA) | 75.00 | Baby product |
| Blouse (Gantsilyo ni owa) | 750.00 | Non-pharmacy (handicraft) |
| CHEESE RING 25G | 11.00 | Food item |
| Coffee | 10.00 | Food item |
| FOLIUM FE | 15.00 | Supplement |
| FOLIUM OB | 15.00 | Supplement |
| FRESCO UNDERPADS | 20.00 | Medical consumable |
| Gantsilyo (Table Cloth) | 1,300.00 | Non-pharmacy (handicraft) |
| GLUTABERRY SOAP 50G | 55.00 | Soap — needs cost lookup |
| Headband for women | 50.00 | Non-pharmacy (accessory) |
| KURT 15G CREAM (HYDROCORTISONE) | 205.00 | Pharmacy item — needs cost lookup |
| NIVEA MEN 25ML INVISIBLE ORIGINAL | 95.00 | Branded personal care |
| NOVAKAST 10MG | 6.00 | Pharmacy item — needs cost lookup |
| PIOMED 30MG | 13.00 | Pharmacy item — needs cost lookup |
| PONDS TONER 100ML | 142.00 | Branded personal care |

**Action:** Look up supplier costs for the pharmacy items (AMBROXYL, KURT CREAM, NOVAKAST, PIOMED). Non-pharmacy items can use estimated costs or mark as ₱0.

---

## High Margin Items (>500%)

These have high markups which is normal for generic medicines. Not necessarily errors, but reviewed for completeness.

| Item | Cost (₱) | Price (₱) | Margin |
|---|---|---|---|
| MEDIPLAST WATER RESISTANT PLASTERS | 0.06 | 2.00 | 3,233% |
| DUO-GESIC TAB. (PARA+TRAMADOL) | 1.20 | 12.00 | 900% |
| VENTOMAX 2mg (Salbutamol) | 0.28 | 2.50 | 793% |
| AMZIPRO 500MG (AZITHROMYCIN) | 9.33 | 75.00 | 704% |
| OMEBLOC 20MG (OMEPRAZOLE) | 0.90 | 6.00 | 567% |
| CLOPIDOGREL 75MG variants | ~1.00 | 6-8.00 | 500-600% |
| CETIRIZINE 10MG variants | 0.35 | 2.00 | 471% |
| LOPERAMIDE 2MG variants | 0.46-0.59 | 3.00 | 409-552% |

**Verdict:** Acceptable for retail pharmacy generic pricing.

---

## Form / Unit Group Issues

### Liquids classified as "Piece" (~200 items)

Many liquid products (syrups, suspensions, solutions, lotions, colognes) are classified as form="Piece" because their names don't contain a form keyword like "SYRUP", "DROPS", etc.

**Examples:**
- `BENADRYL 60ML` — no "SYRUP" in name, but is a syrup → should be Liquid
- `BETADINE 60ml` — antiseptic solution → should be Liquid
- `CASINO 500ml` — alcohol → should be Liquid
- `CEELIN PLUS 120ML` — vitamin C syrup → should be Liquid
- `BIOGESIC 250mg/60ml orange` — paracetamol syrup → should be Liquid

**Impact:** These products will have the wrong unit group assigned. During POS selling, the system will expect "Pieces" instead of "Bottle" units.

**Action:** Post-import, review products with `ML` in name but `Pieces` unit group and correct to `Liquid`.

### Syringes fixed (8 items)

All syringes were incorrectly mapped to form="Syrup" → unit_group="Liquid". Corrected to form="Syringe" → unit_group="Pieces".

---

## Stock Issues

| Condition | Count | Action Taken |
|---|---|---|
| Negative stock (Dr. Care) | 476 | Set to 0; flagged `was_negative_stock` |
| No stock in either column | 497 | Set to 0; flagged `no_stock` |
| Used ACTUAL QTY | ~700 | Preferred over Dr. Care stock |
| Missing ACTUAL QTY, used Dr. Care stock | ~230 | Fallback value |

**Note:** The 497 zero-stock items need a physical count for accurate inventory.

---

## Date Columns (Columns G-P)

Columns G-P contain scratchpad daily sales tracking for July 15-19, 2026. These were **not imported** — they appear to be recent manual entries, not historical system data. The "Summary Cost" column (Q) is a formula referencing these daily amounts.

---

## File Reference

- **CSV:** `/Users/jdbernardo/Sites/dr-care-pos/inventory_for_import.csv`
- **Migration Plan:** `/Users/jdbernardo/Sites/dr-care-pos/MIGRATION_PLAN.md`
