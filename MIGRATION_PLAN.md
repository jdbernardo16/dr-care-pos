# Inventory Migration Plan: Excel → Database

## Overview

Replace the placeholder seeder data with real inventory from `INVENTORY COST UPDATED.xlsx` (1,935 items). The migration must work within NexoPOS's data model: categories → unit groups/units → providers → procurements → products → stock.

---

## Phase 0: Data Cleansing (Pre-Migration)

### 0.1 Fix Negative Stock (476 items)

Items with negative `In stock [Dr. Care]` indicate overselling or unrecorded returns.

**Rule:** Treat negative as 0, unless the `ACTUAL QTY` is available and positive — then use ACTUAL QTY.

| Dr. Care Stock | ACTUAL QTY | Resolution |
|---|---|---|
| Negative | Has value | Use ACTUAL QTY |
| Negative | Null/Empty | Set stock = 0, flag for manual review |

### 0.2 Fill Missing Actual Quantities (668 items)

For items where `ACTUAL QTY` is null:

| Scenario | Rule |
|---|---|
| `In stock [Dr. Care]` is positive and valid | Copy Dr. Care stock as actual |
| Both are null/missing | Set stock = 0, flag for physical count |

### 0.3 Fill Missing Cost (23 items)

Set to 0 (will be filled later via procurement).

### 0.4 Fill Missing Selling Price (5 items)

Calculate as `Cost × 1.3` (30% margin) or flag for manual input.

### 0.5 Handle Duplicates

Check for items with the same name (case-insensitive). Merge or flag.

---

## Phase 1: Reference Data Setup

These are small, one-time seeders that replace the old Pharmacy seeders.

### 1.1 Categories

The Excel has **no category column**. Categories must be inferred from product names using keyword rules:

| Category | Keywords |
|---|---|
| Pain Relief | PARACETAMOL, IBUPROFEN, MEFENAMIC, NAPROXEN, CELECOXIB, DICLOFENAC, TRAMADOL, ETORICOXIB |
| Antibiotics | AMOXICILLIN, CEFALEXIN, AZITHROMYCIN, CIPROFLOXACIN, METRONIDAZOLE, CO-AMOXICLAV, DOXYCYCLINE, CLARITHROMYCIN, ERYTHROMYCIN, LEVOFLOXACIN, CEFUROXIME, CLINDAMYCIN, CEFIXIME, SULFAMETHOXAZOLE, TRIMETHOPRIM |
| Cough & Cold | CARBOCISTEINE, AMBROXOL, COUGH, DEXTROMETHORPHAN, GUAIFENESIN, SALBUTAMOL, LAGUNDI, MUCOSOLVAN |
| Allergy & Sinus | CETIRIZINE, LORATADINE, CHLORPHENAMINE, MONTELUKAST, LEVOCETIRIZINE, ALLERGY, ALLER |
| Digestive Health | DOMPERIDONE, OMEPRAZOLE, LOPERAMIDE, LACTULOSE, MEFENAMIC (for ACIFLAM), ANTACID, HYOSCINE, SIMETHICONE, RANITIDINE, FAMOTIDINE, PANTOPRAZOLE, ESOMEPRAZOLE |
| Heart Health & Diabetes | AMLODIPINE, LOSARTAN, METOPROLOL, SIMVASTATIN, ATORVASTATIN, ASPIRIN (low-dose), CLOPIDOGREL, FUROSEMIDE, TELMISARTAN, METFORMIN, GLICLAZIDE, GLIMEPIRIDE, SITAGLIPTIN, FEBUXOSTAT, BETAHISTINE, CARVEDILOL, NEBIVOLOL, SPIRONOLACTONE, VALSARTAN |
| Skin Care | MUPIROCIN, CLOTRIMAZOLE, HYDROCORTISONE, SILVER SULFADIAZINE, BENZOYL PEROXIDE, TRETINOIN, NEOSPORIN, CALAMINE, ZINC OXIDE, SULFUR SOAP, CLINDAMYCIN (topical), ACICLOVIR |
| Eye & Ear Care | OPHT., EAR DROP, EYE DROP, TOBRAMYCIN, DEXAMETHASONE, TIMOLOL, TRAVOPROST |
| Vitamins & Supplements | ASCORBIC ACID, VITAMIN, MULTIVITAMIN, FOLIC ACID, CALCIUM, IRON, ZINC, FISH OIL, OMEGA, ENERVON, CENTRUM, NEUROBION, MYRA, CONZACE, GINSENG, GARLIC |
| First Aid / Wound Care | BETADINE, BANDAGE, GAUZE, TAPE, COTTON, WOUND, ANTISEPTIC, HYDROGEN PEROXIDE, ALCOHOL, POVIDONE-IODINE |
| Baby & Child Care | BABY, PEDIATRIC, INFANT, PEDIALYTE, DIAPER, TEETHING, KID SYRUP |
| Personal Care | SOAP, SHAMPOO, CONDITIONER, DEODORANT, MOUTHWASH, TOOTHPASTE, DENTAL, TISSUE, WET WIPE, FACE MASK |
| Medical Devices / Consumables | SYRINGE, NEEDLE, THERMOMETER, BP MONITOR, NEBULIZER, STETHOSCOPE, CANE, WHEELCHAIR, OXYMETER |
| Food & Beverages | MILK, COFFEE, JUICE, WATER, NOODLES, BISCUITS, SNACKS, CANDR, NISSIN, LUCKY ME |
| Others / Uncategorized | No keyword match |

**Recommendation:** Start with a catch-all "General" category, auto-classify what you can using rules, then manually review ~200-300 unclassified items.

### 1.2 Unit Groups & Units

Replace the 3 unit groups with a broader set:

| Unit Group | Base Unit | Derived Units | Product Forms |
|---|---|---|---|
| **Pieces** | Piece (1) | Box (10), Strip (5), Blister (4) | Tablets, capsules, sachets, vials |
| **Liquid** | Bottle (1) | Pack of 6 (6) | Syrups, suspensions, drops, solutions |
| **Topical** | Tube (1) | Jar (1) | Creams, ointments, gels, lotions |
| **Weight** | Gram (1) | Kilogram (1000) | Powders, bulk items |
| **Length** | Roll (1) | — | Tapes, bandages, rolls |
| **Piece (Singles)** | Piece (1) | — | Syringes, devices, individual items |

Most items will use **Pieces** (tablets/capsules) or **Liquid** (syrups/drops).

### 1.3 Default Provider

Keep a single `Default Provider` or create a real one (e.g., "DR. CARE MAIN SUPPLIER"). The provider is required for the procurement flow.

### 1.4 Tax Configuration

Keep existing tax setup. All products default to `tax_type: inclusive`, `tax_value: 0`.

---

## Phase 2: Migration Strategy

### Approach: Custom Artisan Command

Create `php artisan migrate:real-inventory` that:

1. Reads the Excel file using a PHP library (e.g., `openspout/openspout`)
2. Applies cleansing rules from Phase 0
3. Infers category per product (keyword matching)
4. Infers unit group/form per product
5. Creates each product via **NexoPOS service classes** (not raw DB insert) to trigger proper stock/product lifecycle hooks
6. Creates a "bulk procurement" to set initial stock levels

### Why Service Classes?

NexoPOS expects products to go through `ProductService`, `StockService`, etc. Direct DB inserts skip:
- COGS calculations
- Stock history entries
- Category total_item counters
- Audit trails

### Bulk Procurement Flow

1. Create 1 `procurement` record (provider = "DR. CARE", delivery_status = "stocked", payment_status = "paid")
2. For each product, create 1 `procurements_products` record with:
   - `purchase_price` = Cost from Excel
   - `quantity` = ACTUAL QTY (cleansed)
   - `available_quantity` = ACTUAL QTY (cleansed)
   - `expiration_date` = null (not available in Excel)
3. Mark the procurement as "stocked" — this triggers the stock increase

### Product Creation Per Item

| DB Field | Source |
|---|---|
| `name` | Excel column A (Name) |
| `barcode` | Generated (random 12-digit or sequential) |
| `barcode_type` | Hard-coded: `code128` |
| `sku` | Generated from name prefix + random suffix |
| `category_id` | Inferred from name keywords |
| `unit_group` | Inferred from name/form |
| `tax_type` | `inclusive` |
| `tax_value` | 0 |
| `accurate_tracking` | false |
| `auto_cogs` | true |
| `status` | `available` |
| `stock_management` | `enabled` |
| `description` | Empty or extract parenthetical generic name |
| `expires` | false (Excel has no expiry data) |
| `on_expiration` | `prevent_sales` |

Then create `product_unit_quantity` record:

| Field | Source |
|---|---|
| `quantity` | `In stock [Dr. Care]` (cleansed) or `ACTUAL QTY` |
| `sale_price` | Excel column C (Price [Dr. Care]) |
| `sale_price_edit` | Same as sale_price |
| `sale_price_net` | Same as sale_price |
| `sale_price_gross` | Same as sale_price |
| `wholesale_price` | sale_price × 0.85 (85%) |
| `cogs` | Excel column B (Cost) |
| `expiration_date` | null |
| `stock_alert_enabled` | false (set true only for low-stock items) |
| `low_quantity` | 10 (default) |

---

## Phase 3: Barcode & SKU Generation

### Barcode Strategy

Option A: **Use existing barcode if available**
- The Excel has no barcode column
- We generate new ones
- Use sequential 12-digit format: `100000000001` through `100001000000`
- Or use random 13-digit (EAN-13 format)

Option B: **Skip barcode for now**
- Let the system generate on first use
- Set barcode to SKU as fallback

**Recommendation:** Option A — sequential assignment. Store the mapping in a CSV for printing later.

### SKU Strategy

Format: `DC-{CATEGORY_CODE}-{SEQUENCE}`
- `DC` = Dr. Care prefix
- `CATEGORY_CODE` = 3-letter abbreviation (PRL, ANT, VIT, COU, ALR, DIG, SKN, EYE, FIR, DIA, HRT, BAB, PER, MED, FOD, GEN)
- `SEQUENCE` = 4-digit zero-padded number

Example: `DC-PRL-0001`, `DC-PRL-0002`, `DC-ANT-0001`

---

## Phase 4: Verification

### Pre-Seed Checklist

- [x] Excel data extracted into clean CSV/array
- [ ] All costs present (23 items = 0 cost => flag)
- [ ] All prices present (5 items = 0 price => calculate)
- [ ] Stock quantities cleansed (476 negatives handled, 668 nulls filled)
- [ ] Category mapping rules defined and tested
- [ ] Unit mapping rules defined and tested

### Post-Seed Verification

| Check | Expected | How |
|---|---|---|
| Product count matches Excel | ~1,935 | DB query |
| Stock totals | Sum of ACTUAL QTY column | System stock report |
| Category distribution | Realistic | Category counts |
| No negative stock | 0 | Stock report |
| All products have category | 0 uncategorized | DB query with `category_id = null` |
| All products have price | 0 | DB query with `sale_price = 0` |
| Procurement matches stock | — | Procurement totals vs stock totals |

---

## Phase 5: What We Lose / Manual Gaps

### Not Imported (No Source Data)

| Data Point | Impact | Workaround |
|---|---|---|
| Expiration dates | Can't set per-product expiry | Default: no expiry; add manually for perishables |
| Barcodes | Manually scan to assign | Generate sequentially; reprint labels |
| Supplier/provider info | Missing per-item origin | Single default provider for all items |
| Category assignments | Auto-categorized — may need manual correction | Review flag report after import |
| Unit of measure per item | Inferred from name — not 100% accurate | Review edge cases (tubes vs bottles) |
| Product images | Not in Excel | Skip; add later |
| Price history | Starting fresh | First procurement price = baseline |
| Daily sales data (columns G-P) | These appear to be recent scratchpad entries | Skip — not historical sales |

### Items Out of Scope of This Plan

- **Soaps & cosmetics** branded "YOUNGS" — could be Personal Care or a separate line
- **Food items** (YAKULT, ALASKA MILK, noodles) — food category or separate treatment
- **Pregnancy tests & diagnostics** — fits Diabetes Care or new "Diagnostics" category
- **Veterinary products** — need separate category if present

---

## Rollback Plan

If the migration fails mid-way:

1. Keep a backup of the old database state
2. Run `php artisan db:seed --class=OriginalSeeder` (if preserved)
3. Or truncate these tables and re-run original seeders:
   - `nexopos_products`
   - `nexopos_products_unit_quantities`
   - `nexopos_procurements`
   - `nexopos_procurements_products`
   - `nexopos_products_categories` (only non-default)
   - `nexopos_units_groups` (only pharmacy-specific)
   - `nexopos_units` (only pharmacy-specific)

---

## Effort Estimate

| Step | Est. Time | Who |
|---|---|---|
| Data cleansing (manual review of edge cases) | 2-4 hours | Admin/pharmacist |
| Category mapping rules refinement | 1 hour | Developer |
| Unit mapping rules | 30 min | Developer |
| Build import command | 4-6 hours | Developer |
| Test run on staging | 1 hour | Developer |
| Production run | 30 min | Developer |
| Post-import review & fixes | 2-3 hours | Admin/pharmacist |
| **Total** | **~11-16 hours** | |
