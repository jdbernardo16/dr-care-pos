# Creating a Product

## Getting Started

At the top of the page, type the product name into the text field. Once done, click **Save** to submit the form. Use the **Return** link to go back to the products list without saving.

---

## Tabs

The form is divided into tabs. Which tabs you see depends on the **Product Type** you select:

| Product Type | Tabs Available |
|---|---|
| Materialized / Dematerialized | Identification, Units, Expiry, Taxes, Images |
| Grouped (bundle) | Identification, Groups |

---

## Identification Tab

- **Category** — Start typing to search for a category, or click the **+** button to create one on the spot.
- **Barcode** — Enter the barcode value, or use a scanner to populate this field automatically.
- **SKU** — Enter a unique SKU for the product (optional).
- **Barcode Type** — Choose the type of barcode being used. Options include EAN-8, EAN-13, Code 128, Code 39, UPC-A, UPC-E, and more.
- **Product Type** — Defines the nature of the product:
  - **Materialized Product** — Physical items with stock.
  - **Dematerialized Product** — Non-physical items like services.
  - **Grouped Product** — A bundle made up of other products.
- **Status** — Set to **On Sale** to show in the POS, or **Hidden** to hide it from sale.
- **Stock Management** — Toggle stock tracking on or off (disable for services).
- **Pin Product** — Toggle to pin this product to the top of the POS grid.
- **Description** — Use the rich text editor to write a product description.

---

## Groups Tab (for bundled products only)

Search for products to include in the bundle. For each item added:

- Set the quantity.
- Choose the unit.
- Set the sale price.

The total price of all items is calculated automatically. Remove any item using the delete button.

---

## Units Tab

Start by selecting a **Unit Group** (e.g., "Countable", "Weight", "Volume"). The system will load the available units from that group.

Additional options:

- **Accurate Tracking** — Enable to hide the product from the POS grid. It will only be accessible via barcode scan.
- **Auto COGS** — Enable to have the Cost of Goods Sold computed automatically from procurement data.

### Adding Selling Units

Click **New Group** to add a unit for sale (e.g., "Piece", "Box", "Pack of 6"). For each unit:

- **Assigned Unit** — Choose the unit from the selected group.
- **Convert Unit** — Optionally set a conversion unit. It must be different from the assigned unit.
- **Sale Price** — Regular selling price.
- **Wholesale Price** — Bulk/wholesale price.
- **COGS** — Cost of Goods Sold (optional).
- **Weighable** — Enable if this product is sold by weight using a scale barcode.
- **PLU Code** — Price Look-Up code for scale barcodes. Leave blank to auto-generate.
- **Stock Alert** — Turn on low-stock notifications and set the threshold in **Low Quantity**.
- **Visible** — Whether this unit is available for sale in the POS.
- **Preview Url** — Add an image for this unit using the media library.

> Once stock has been procured for a unit, the assigned unit cannot be changed. Delete the unit group to remove it, but note this will also remove any procured stock.

---

## Expiry Tab

- **Product Expires** — Toggle to enable expiration tracking.
- **On Expiration** — Choose what happens when the product expires: **Prevent Sales** (block selling) or **Allow Sales**.

---

## Taxes Tab

- **Tax Group** — Select the applicable tax rate group.
- **Tax Type** — Choose **Inclusive** (tax included in price) or **Exclusive** (tax added on top).

---

## Images Tab

Click **Add Images** to open a media entry. For each image:

- Pick the image from the media library.
- Toggle **Is Primary** to mark it as the main product image.

Click **Remove Image** to delete an entry. Only one image can be set as primary.

---

## Saving the Product

Before saving, the system checks:

- The product name is filled in.
- All required fields are complete.
- At least one selling unit is defined.
- Only one image is marked as primary.

If everything is valid, the product is created and you will be redirected to the product's detail page. If there are errors, they will be shown on the relevant fields so you can correct them.
