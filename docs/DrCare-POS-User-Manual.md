# DrCare POS — User Manual

**Version:** 1.0  
**Last Updated:** May 2026  
**System:** NexoPOS Point of Sale

---

## Welcome to DrCare POS

DrCare POS is a complete Point of Sale and business management system designed for retail operations. It helps you manage sales, inventory, customers, suppliers, and financial reporting — all from one centralized dashboard.

This manual will guide you through every feature of the system, from logging in for the first time to generating detailed business reports.

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Point of Sale (POS)](#2-point-of-sale-pos)
3. [Orders Management](#3-orders-management)
4. [Products Management](#4-products-management)
5. [Categories](#5-categories)
6. [Customers Management](#6-customers-management)
7. [Rewards System](#7-rewards-system)
8. [Cash Registers](#8-cash-registers)
9. [Procurements (Purchasing/Stock Intake)](#9-procurements-purchasingstock-intake)
10. [Providers (Suppliers)](#10-providers-suppliers)
11. [Transactions & Accounting](#11-transactions--accounting)
12. [Taxes](#12-taxes)
13. [Units of Measure](#13-units-of-measure)
14. [Reports](#14-reports)
15. [Media Library](#15-media-library)
16. [Modules (Extensions)](#16-modules-extensions)
17. [Users & Roles](#17-users--roles)
18. [Settings](#18-settings)
19. [Notifications](#19-notifications)
20. [System Maintenance](#20-system-maintenance)

---

## Quick Start Guide

If you are new to DrCare POS, here are the most common tasks you will perform:

| Task                  | Where to Go                   |
| --------------------- | ----------------------------- |
| Log in                | `/sign-in`                    |
| Open the POS register | `/dashboard/pos`              |
| View today's sales    | `/dashboard`                  |
| Add a new product     | `/dashboard/products/create`  |
| Add a new customer    | `/dashboard/customers/create` |
| View all orders       | `/dashboard/orders`           |
| Open a cash register  | `/dashboard/cash-registers`   |
| View reports          | `/dashboard/reports/sales`    |

**Your first sale in 5 steps:**

1. Log in at `/sign-in`
2. Navigate to the POS at `/dashboard/pos`
3. Search for or scan a product barcode
4. Select a payment method and complete the sale
5. Print or email the receipt to the customer

---

## 1. Getting Started

### Overview of the DrCare POS System

DrCare POS is a web-based system that runs in your browser. You do not need to install any software on your computer. All you need is an internet connection and a supported browser (Chrome, Firefox, Edge, or Safari).

The system is organized into modules accessible from the left-hand navigation menu once you are logged in. Each module handles a specific area of your business — from selling products at the register to managing supplier purchases and generating financial reports.

### Logging In

1. Open your browser and go to the login page: `/sign-in`
2. Enter your **username** or **email address**
3. Enter your **password**
4. Click **Sign In**

> **Note:** Your login credentials are provided by your system administrator. If you do not have them, contact your manager.

### Logging Out

1. Click your **avatar/profile icon** in the top-right corner of the screen
2. Select **Logout** from the dropdown menu

> **Tip:** Always log out when you are finished, especially on shared computers.

### Password Recovery

If you have forgotten your password:

1. Go to `/password-lost`
2. Enter the **email address** associated with your account
3. Click **Submit**
4. Check your email for a password reset link
5. Follow the link to create a new password

> **Note:** If you do not receive the email within a few minutes, check your spam/junk folder.

### Dashboard Overview

After logging in, you will land on the main dashboard (`/dashboard`). The dashboard gives you a snapshot of your business performance at a glance.

#### Dashboard Elements

| Element                 | Description                                                          |
| ----------------------- | -------------------------------------------------------------------- |
| **Daily Summary Cards** | Show today's total sales, number of orders, expenses, and net income |
| **Top Customers**       | List of customers who have made the most purchases                   |
| **Best Cashiers**       | Cashiers with the highest sales volume                               |
| **Recent Orders**       | A quick list of the most recent transactions                         |
| **Weekly Trends**       | A chart showing sales trends over the past week                      |

> **Tip:** Click on any summary card to drill down into more detailed information.

---

## 2. Point of Sale (POS)

The POS is where you process customer sales. It is the main screen cashiers will use throughout the day.

### Accessing the POS Interface

Navigate to `/dashboard/pos` from the main menu or click the **POS** link in the navigation bar.

### Adding Products to Cart

There are several ways to add products to the current sale:

| Method              | How To                                                                                                                                      |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| **Search**          | Type the product name in the search bar at the top of the POS screen. Matching products will appear as you type. Click a product to add it. |
| **Barcode Scan**    | Use a barcode scanner to scan a product's barcode. The product will be added automatically.                                                 |
| **Category Browse** | Click a category tab to browse products within that category. Click any product to add it.                                                  |
| **Quick Code**      | Enter the product's SKU or quick code in the search field.                                                                                  |

> **Tip:** You can search products by barcode on the POS screen. Simply type or scan the barcode number into the search field.

### Managing the Cart

Once products are in the cart, you can:

| Action              | How To                                                                |
| ------------------- | --------------------------------------------------------------------- |
| **Change Quantity** | Click the quantity field next to the product and enter the new number |
| **Remove Item**     | Click the **X** or trash icon next to the product                     |
| **Apply Discount**  | Click the discount button and enter a percentage or fixed amount      |
| **Add Note**        | Click the note icon to attach a comment to a specific item            |

### Applying Discounts and Coupons

1. Click the **Discount** button in the cart panel
2. Choose between **Percentage** or **Fixed Amount**
3. Enter the discount value
4. Click **Apply**

To apply a coupon:

1. Click the **Coupon** button
2. Enter the coupon code
3. Click **Apply**

> **Note:** Coupons must be created in advance in the Customers module. See [Section 6](#6-customers-management) for details.

### Selecting Payment Methods

1. Click the **Pay** button to open the payment screen
2. Select the **payment method** (Cash, Card, Mobile Payment, Customer Credit, etc.)
3. Enter the **amount tendered** by the customer
4. The system will calculate and display the **change due**
5. Click **Confirm** to complete the sale

> **Tip:** You can split payment across multiple payment types for a single order.

### Completing a Transaction

After confirming payment:

1. The system processes the sale and updates inventory
2. A receipt is generated
3. Choose to **print**, **email**, or **display** the receipt
4. The cart clears and is ready for the next customer

### Holding and Recalling Orders

**To hold an order:**

1. Click the **Hold** button in the POS
2. Optionally assign a customer name or note
3. The order is saved and removed from the active cart

**To recall a held order:**

1. Click the **Pending Orders** button
2. Select the order you want to resume
3. The order loads back into the cart for editing or checkout

### Customer Assignment to Orders

1. Click the **Customer** button in the POS header
2. Search for an existing customer by name, email, or phone
3. Select the customer to attach them to the current order
4. If the customer is not found, click **Create Customer** to add them on the spot

> **Tip:** Assigning customers to orders helps track purchase history and enables loyalty rewards.

---

## 3. Orders Management

### Viewing Orders List

Navigate to `/dashboard/orders` to see all orders. The orders list shows:

| Column         | Description                                   |
| -------------- | --------------------------------------------- |
| **Order Code** | Unique identifier for each order              |
| **Customer**   | Name of the customer (if assigned)            |
| **Date**       | Date and time the order was created           |
| **Total**      | Total amount of the order                     |
| **Status**     | Current status (Paid, Unpaid, Refunded, etc.) |
| **Cashier**    | User who processed the order                  |

Use the filters at the top to narrow orders by date range, status, customer, or cashier.

### Order Details, Invoices, and Receipts

**View Order Details:**

1. Click on any order in the list to open its detail view
2. Review items, payments, customer information, and notes

**View Invoice:**

- Navigate to `/dashboard/orders/invoice/{order}` (replace `{order}` with the order ID)
- Or click the **Invoice** button from the order detail page
- The invoice can be printed or downloaded as PDF

**View Receipt:**

- Navigate to `/dashboard/orders/receipt/{order}`
- Or click the **Receipt** button from the order detail page
- Receipts are formatted for thermal printers

### Payment and Refund Receipts

| Document            | URL                                                | Description                                     |
| ------------------- | -------------------------------------------------- | ----------------------------------------------- |
| **Payment Receipt** | `/dashboard/orders/payment-receipt/{orderPayment}` | Receipt for a specific payment made on an order |
| **Refund Receipt**  | `/dashboard/orders/refund-receipt/{refund}`        | Receipt documenting a refund transaction        |

### Processing Refunds

1. Open the order you want to refund from `/dashboard/orders`
2. Click the **Refund** button
3. Select the items to refund and enter quantities
4. Choose the **refund reason**
5. Select the **refund payment method** (cash back, credit, etc.)
6. Confirm the refund

> **Note:** Refunded items are automatically returned to stock unless configured otherwise.

### Voiding Orders

1. Open the order from `/dashboard/orders`
2. Click the **Void** or **Delete** button
3. Confirm the action

> **Warning:** Voiding an order is irreversible. Make sure you want to permanently remove the order before confirming.

### Instalment/Layaway Plans

Navigate to `/dashboard/orders/instalments` to manage instalment plans.

**To set up an instalment plan:**

1. Create an order as usual
2. Instead of full payment, select **Instalment** as the payment type
3. Define the payment schedule (number of instalments, frequency)
4. The customer can make partial payments over time

**To view instalment progress:**

1. Open the order
2. View the instalment tab to see paid and remaining amounts
3. Record additional payments as the customer pays

### Payment Types Management

Navigate to `/dashboard/orders/payments-types` to manage payment methods.

| Action                  | How To                                                                               |
| ----------------------- | ------------------------------------------------------------------------------------ |
| **Create Payment Type** | Click **Add New**, enter the name (e.g., "Mobile Wallet"), set the account, and save |
| **Edit Payment Type**   | Click the edit icon next to the payment type, modify details, and save               |
| **Delete Payment Type** | Click the delete icon (only available if the type has no associated transactions)    |

> **Tip:** Common payment types include Cash, Credit Card, Debit Card, Mobile Payment, and Customer Credit.

---

## 4. Products Management

### Products List

Navigate to `/dashboard/products` to view all products. The list displays:

| Column           | Description                       |
| ---------------- | --------------------------------- |
| **Product Name** | Name of the product               |
| **SKU/Barcode**  | Product identifier                |
| **Category**     | Product category                  |
| **Price**        | Selling price                     |
| **Stock**        | Current quantity in stock         |
| **Status**       | Active, Inactive, or Out of Stock |

Use the search bar and filters to find specific products.

### Creating a Product

Navigate to `/dashboard/products/create` and fill in the following:

| Field                | Description                              |
| -------------------- | ---------------------------------------- |
| **Name**             | Product name (required)                  |
| **SKU**              | Stock Keeping Unit — a unique identifier |
| **Barcode**          | Barcode value (can be auto-generated)    |
| **Category**         | Select an existing category              |
| **Price**            | Selling price                            |
| **Cost Price**       | Purchase cost (for profit calculation)   |
| **Tax**              | Select applicable tax group              |
| **Unit**             | Unit of measure (e.g., piece, kg, liter) |
| **Images**           | Upload product images                    |
| **Description**      | Optional product description             |
| **Stock Management** | Enable/disable stock tracking            |
| **Initial Stock**    | Starting quantity                        |

Click **Save** to create the product.

### Editing a Product

Navigate to `/dashboard/products/edit/{product}` (replace `{product}` with the product ID) or click the **Edit** icon next to any product in the list.

You can modify any field just like when creating a product. Changes are saved when you click **Update**.

### Product Variations

Products can have variations such as size, color, or flavor.

**To add variations:**

1. Open the product edit page
2. Go to the **Variations** tab
3. Click **Add Variation**
4. Define the variation attributes (e.g., Size: Small, Medium, Large)
5. Set individual prices and stock for each variation
6. Save

> **Tip:** Each variation acts as a sub-product with its own SKU and stock level.

### Product Units Management

Navigate to `/dashboard/products/{product}/units` to manage units for a specific product.

| Action             | Description                                          |
| ------------------ | ---------------------------------------------------- |
| **Add Unit**       | Link a unit (e.g., box of 12) to the product         |
| **Set Conversion** | Define how many base units equal one of the new unit |
| **Set Price**      | Define the selling price for this unit               |

### Stock Adjustments

Navigate to `/dashboard/products/stock-adjustment` to manually adjust stock levels.

1. Select the **product** to adjust
2. Choose the **adjustment type**:
    - **Add** — increase stock
    - **Remove** — decrease stock
    - **Set** — set to a specific quantity
3. Enter the **quantity**
4. Add a **reason** (e.g., "Damaged goods," "Found in storage")
5. Click **Save**

> **Note:** All stock adjustments are logged for audit purposes.

### Stock History

Navigate to `/dashboard/products/{product}/history` to view the complete stock history for a product.

The history shows every stock movement including:

- Sales (stock out)
- Procurements (stock in)
- Adjustments
- Returns
- Refunds

### Printing Product Labels

Navigate to `/dashboard/products/print-labels` to print barcode labels.

1. Select the products to print labels for
2. Choose the **label size** and **format**
3. Set the **number of copies** per product
4. Click **Print**

> **Tip:** Labels include the product name, price, and barcode for easy scanning.

### Barcode Search

On the POS screen and product list, you can search by barcode:

1. Focus the search field
2. Scan or type the barcode number
3. The matching product appears instantly

### Scale Ranges for Weighted Products

Navigate to `/dashboard/products/scale-range` to configure scale ranges for products sold by weight.

| Setting     | Description                                   |
| ----------- | --------------------------------------------- |
| **Product** | Select the weighted product                   |
| **Range**   | Define weight ranges (e.g., 0–500g, 500g–1kg) |
| **Price**   | Set the price for each range                  |

This is useful for products like fruits, vegetables, or meats sold by weight.

### Stock Flow Records

Navigate to `/dashboard/products/stock-flow-records` to view a comprehensive log of all stock movements across all products.

Use filters to narrow by:

- Date range
- Product
- Movement type (in, out, adjustment)
- User who made the change

### Reordering and Pinning Products

**To pin a product:**

1. Go to the product list
2. Click the **pin** icon next to the product
3. Pinned products appear at the top of the list

**To reorder products:**

1. Enable reorder mode (drag handle icon)
2. Drag products to your preferred order
3. Save the arrangement

> **Tip:** Pin frequently sold products for quick access on the POS screen.

---

## 5. Categories

### Categories List

Navigate to `/dashboard/products/categories` to view all product categories. Categories help organize products and make them easier to find on the POS screen.

### Creating Categories

Navigate to `/dashboard/products/categories/create`:

1. Enter the **category name**
2. Add a **description** (optional)
3. Upload a **category image** (optional)
4. Set the **parent category** if creating a sub-category
5. Click **Save**

### Editing Categories

Navigate to `/dashboard/products/categories/edit/{category}`:

1. Modify any field as needed
2. Click **Save** to update

### Reordering Categories

1. Go to the categories list
2. Use the **drag handles** to rearrange categories
3. The order on the POS screen reflects this arrangement

### Computing Category Product Counts

The categories list automatically shows the number of products in each category. This count updates when products are added, removed, or reassigned.

> **Tip:** Use categories to group related products (e.g., "Medicines," "Supplements," "Personal Care") for faster POS navigation.

---

## 6. Customers Management

### Customers List

Navigate to `/dashboard/customers` to view all registered customers. The list shows:

| Column             | Description                    |
| ------------------ | ------------------------------ |
| **Name**           | Customer's full name           |
| **Email**          | Email address                  |
| **Phone**          | Phone number                   |
| **Group**          | Customer group membership      |
| **Credit Balance** | Account credit (if applicable) |
| **Total Orders**   | Number of orders placed        |

### Creating a Customer

Navigate to `/dashboard/customers/create`:

| Field          | Description                      |
| -------------- | -------------------------------- |
| **First Name** | Customer's first name (required) |
| **Last Name**  | Customer's last name (required)  |
| **Email**      | Email address                    |
| **Phone**      | Phone number                     |
| **Address**    | Street address                   |
| **City**       | City                             |
| **Group**      | Assign to a customer group       |
| **Notes**      | Any additional notes             |

Click **Save** to create the customer.

### Editing Customer Details

Navigate to `/dashboard/customers/edit/{customer}` or click the **Edit** icon next to any customer.

Update any field and click **Save**.

### Customer Orders

Navigate to `/dashboard/customers/{customer}/orders` to view all orders placed by a specific customer.

This is useful for:

- Checking purchase history
- Processing returns for a specific customer
- Understanding buying patterns

### Customer Coupons

Navigate to `/dashboard/customers/{customer}/coupons` to view and manage coupons assigned to a customer.

| Action             | Description                                              |
| ------------------ | -------------------------------------------------------- |
| **View Coupons**   | See all active and expired coupons                       |
| **Create Coupon**  | Generate a new coupon for the customer                   |
| **Set Conditions** | Define minimum purchase, expiry date, and discount value |

### Customer Account History

Navigate to `/dashboard/customers/{customer}/account-history` to view the customer's billing account.

This shows:

- Credit purchases
- Payments made
- Outstanding balance
- Account transactions

> **Tip:** The account history is essential when customers buy on credit or have a billing account.

### Customer Rewards

Navigate to `/dashboard/customers/{customer}/rewards` to view the customer's reward points.

| Information           | Description                    |
| --------------------- | ------------------------------ |
| **Points Balance**    | Current reward points          |
| **Points Earned**     | Total points accumulated       |
| **Points Redeemed**   | Points used for rewards        |
| **Available Rewards** | Rewards the customer can claim |

### Customer Groups

Navigate to `/dashboard/customers/groups` to manage customer groups.

**Creating a Group:**

1. Click **Add New**
2. Enter the **group name**
3. Set **pricing rules** (e.g., group-specific discounts)
4. Click **Save**

**Editing a Group:**

1. Click the **Edit** icon next to the group
2. Modify settings as needed
3. Click **Save**

**Transferring Customers Between Groups:**

1. Open the customer's edit page
2. Change the **Group** dropdown to the new group
3. Click **Save**

> **Tip:** Use customer groups to offer different pricing tiers (e.g., Wholesale, Retail, VIP).

---

## 7. Rewards System

### Rewards System List

Navigate to `/dashboard/customers/rewards-system` to view all active reward programs.

### Creating a Reward Program

Navigate to `/dashboard/customers/rewards-system/create`:

| Field                   | Description                                       |
| ----------------------- | ------------------------------------------------- |
| **Name**                | Program name (e.g., "Loyalty Points")             |
| **Points per Currency** | How many points earned per unit of currency spent |
| **Threshold**           | Minimum points needed to redeem a reward          |
| **Reward Type**         | Coupon, discount, or free product                 |
| **Coupon Value**        | Value of the reward coupon                        |
| **Active**              | Enable or disable the program                     |

Click **Save** to create the program.

### Editing Reward Programs

Navigate to `/dashboard/customers/rewards-system/edit/{reward}`:

1. Modify any setting
2. Click **Save**

> **Warning:** Changing reward thresholds or values affects all future redemptions but does not retroactively change existing customer points.

### How Rewards Work

1. **Earning Points:** Customers earn points automatically when they make purchases. The rate is defined in the reward program (e.g., 1 point per $10 spent).
2. **Accumulating:** Points accumulate in the customer's account.
3. **Redeeming:** When a customer reaches the threshold, they can redeem points for a coupon or discount.
4. **Applying:** The coupon is applied at the POS during checkout.

> **Tip:** Promote your rewards program to encourage repeat business.

---

## 8. Cash Registers

### Registers List

Navigate to `/dashboard/cash-registers` to view all cash registers. Each register represents a physical checkout point.

### Creating a Register

Navigate to `/dashboard/cash-registers/create`:

1. Enter the **register name** (e.g., "Register 1," "Counter A")
2. Assign a **user** (cashier) to the register
3. Set the **initial cash amount** (float)
4. Click **Save**

### Editing a Register

Navigate to `/dashboard/cash-registers/edit/{register}`:

1. Modify the register details
2. Click **Save**

### Opening/Closing a Register

**Opening a Register:**

1. Go to `/dashboard/cash-registers`
2. Click **Open** next to the register
3. Enter the **starting cash amount**
4. Confirm to begin the session

**During the Session:**

- **Cash In:** Record additional cash added to the register
- **Cash Out:** Record cash removed from the register (e.g., for deposits)

**Closing a Register:**

1. Click **Close** next to the register
2. Enter the **ending cash count** (actual cash in the drawer)
3. The system calculates the **expected cash** and shows any **discrepancy**
4. Confirm to close the session

> **Tip:** Always count the cash carefully before closing. Discrepancies should be investigated.

### Register Session History

Navigate to `/dashboard/cash-registers/history/{register}` to view all past sessions for a register.

Each session record shows:

- Opening and closing times
- Starting and ending cash
- Total sales during the session
- Cash in/out transactions
- Discrepancies

### Z-Report

Navigate to `/dashboard/cash-registers/z-report/{register}` to generate a Z-Report (end-of-day report).

The Z-Report includes:

- Total sales by payment type
- Number of transactions
- Tax collected
- Discounts given
- Refunds processed
- Net total

> **Note:** The Z-Report is typically generated at the end of each business day for accounting purposes.

---

## 9. Procurements (Purchasing/Stock Intake)

### Procurements List

Navigate to `/dashboard/procurements` to view all purchase orders from suppliers.

| Column        | Description                     |
| ------------- | ------------------------------- |
| **Reference** | Procurement reference number    |
| **Provider**  | Supplier name                   |
| **Date**      | Date of procurement             |
| **Total**     | Total cost                      |
| **Status**    | Paid, Unpaid, or Partially Paid |

### Creating a Procurement

Navigate to `/dashboard/procurements/create`:

1. Select the **provider** (supplier)
2. Add **products** to the procurement:
    - Search for products
    - Enter quantities
    - Set unit costs
3. Review the **total cost**
4. Click **Save**

### Adding Products to a Procurement

1. On the procurement creation/edit page, use the **product search** to find items
2. Enter the **quantity** to order
3. Set the **purchase price** per unit
4. The line total calculates automatically
5. Repeat for all products

### Editing Procurements

Navigate to `/dashboard/procurements/edit/{procurement}`:

1. Modify products, quantities, or prices
2. Click **Save**

> **Note:** Editing a procurement after stock has been received may require a stock adjustment.

### Procurement Invoice

Navigate to `/dashboard/procurements/edit/{procurement}/invoice` to view or print the procurement invoice.

The invoice can be used for:

- Sending to the supplier for confirmation
- Internal record-keeping
- Matching against delivery

### Marking Procurements as Paid

1. Open the procurement
2. Click **Mark as Paid** or record a payment
3. Select the **payment account**
4. Enter the **amount paid**
5. Confirm

### Low Stock Suggestions

The system can suggest products to procure based on low stock levels.

1. Navigate to the procurements section
2. Look for the **Low Stock Suggestions** area
3. Select products to add to a new procurement
4. Proceed with creating the procurement

### Editing Procurement Products

Within a procurement, you can edit individual product entries:

1. Click the **edit** icon next to the product line
2. Adjust quantity or price
3. Save

> **Tip:** Keep procurement records accurate for correct cost-of-goods calculations.

---

## 10. Providers (Suppliers)

### Providers List

Navigate to `/dashboard/providers` to view all suppliers.

| Column                 | Description               |
| ---------------------- | ------------------------- |
| **Name**               | Supplier/company name     |
| **Contact**            | Contact person            |
| **Phone**              | Phone number              |
| **Email**              | Email address             |
| **Total Procurements** | Number of purchase orders |

### Creating a Provider

Navigate to `/dashboard/providers/create`:

| Field              | Description                      |
| ------------------ | -------------------------------- |
| **Name**           | Provider/company name (required) |
| **Contact Person** | Name of the contact              |
| **Phone**          | Phone number                     |
| **Email**          | Email address                    |
| **Address**        | Physical address                 |
| **Notes**          | Additional notes                 |

Click **Save** to create the provider.

### Editing a Provider

Navigate to `/dashboard/providers/edit/{provider}`:

1. Update any field
2. Click **Save**

### Viewing Provider's Procurements

Navigate to `/dashboard/providers/{provider}/procurements` to see all purchase orders from a specific supplier.

This helps you:

- Track order history with a supplier
- Compare pricing over time
- Identify reliable suppliers

### Viewing Provider's Products

Navigate to `/dashboard/providers/{provider}/products` to see all products supplied by a specific provider.

> **Tip:** Use this to quickly find which supplier provides which products when restocking.

---

## 11. Transactions & Accounting

### Transactions List

Navigate to `/dashboard/accounting/transactions` to view all financial transactions (income and expenses).

| Column          | Description             |
| --------------- | ----------------------- |
| **Date**        | Transaction date        |
| **Description** | Transaction description |
| **Account**     | Account involved        |
| **Amount**      | Transaction amount      |
| **Type**        | Income or Expense       |

### Creating a Transaction

Navigate to `/dashboard/accounting/transactions/create`:

1. Select the **transaction type** (Income or Expense)
2. Choose the **account** (e.g., Rent, Utilities, Sales Revenue)
3. Enter the **amount**
4. Add a **description**
5. Set the **date**
6. Click **Save**

### Editing Transactions

Navigate to `/dashboard/accounting/transactions/edit/{transaction}`:

1. Modify any field
2. Click **Save**

> **Note:** Editing past transactions affects financial reports. Do so carefully.

### Transaction History / Cash Flow

Navigate to `/dashboard/accounting/transactions/history` to view a chronological cash flow report.

This shows:

- All income and expenses over time
- Running balance
- Net cash flow

### Transaction Accounts (Chart of Accounts)

Navigate to `/dashboard/accounting/accounts` to manage your chart of accounts.

**Creating an Account:**

1. Click **Add New**
2. Enter the **account name** (e.g., "Office Supplies")
3. Select the **parent account** if creating a sub-account
4. Set the **account type** (Asset, Liability, Income, Expense)
5. Click **Save**

**Editing an Account:**

1. Click the **Edit** icon next to the account
2. Modify details
3. Click **Save**

> **Tip:** Organize accounts hierarchically (e.g., Expenses > Utilities > Electricity) for better reporting.

### Transaction Rules / Automation

Navigate to `/dashboard/accounting/rules` to set up automated transaction rules.

**Creating a Rule:**

1. Click **Add New**
2. Define the **trigger condition** (e.g., "When a procurement is created")
3. Set the **action** (e.g., "Create an expense transaction")
4. Select the **account** to use
5. Click **Save**

Rules automate bookkeeping by creating transactions automatically when certain events occur.

### Recurring Transactions

Set up recurring transactions for regular income or expenses:

1. Create a transaction as usual
2. Enable the **Recurring** option
3. Set the **frequency** (daily, weekly, monthly)
4. Set the **start and end dates**
5. Save

The system will automatically create the transaction at each interval.

---

## 12. Taxes

### Taxes List

Navigate to `/dashboard/taxes` to view all configured taxes.

| Column   | Description                         |
| -------- | ----------------------------------- |
| **Name** | Tax name (e.g., "VAT," "Sales Tax") |
| **Rate** | Tax percentage                      |
| **Type** | Inclusive or Exclusive              |

### Creating a Tax

Navigate to `/dashboard/taxes/create`:

1. Enter the **tax name**
2. Set the **rate** (percentage)
3. Choose the **type**:
    - **Inclusive** — tax is included in the product price
    - **Exclusive** — tax is added on top of the product price
4. Click **Save**

### Editing Taxes

Navigate to `/dashboard/taxes/edit/{tax}`:

1. Modify the name, rate, or type
2. Click **Save**

> **Warning:** Changing tax rates affects future sales only. Past orders retain the tax rate at the time of sale.

### Tax Groups

Navigate to `/dashboard/taxes/groups` to manage tax groups. Tax groups allow you to combine multiple taxes on a single product.

**Creating a Tax Group:**

1. Click **Add New**
2. Enter the **group name**
3. Add **taxes** to the group (select from existing taxes)
4. Click **Save**

**Editing a Tax Group:**

1. Click the **Edit** icon next to the group
2. Add or remove taxes
3. Click **Save**

> **Tip:** Use tax groups when a product is subject to multiple taxes (e.g., VAT + Local Tax).

---

## 13. Units of Measure

### Units List

Navigate to `/dashboard/units` to view all units of measure.

| Column           | Description                           |
| ---------------- | ------------------------------------- |
| **Name**         | Unit name (e.g., "Piece," "Kilogram") |
| **Abbreviation** | Short form (e.g., "pc," "kg")         |
| **Group**        | Unit group membership                 |

### Creating Units

Navigate to `/dashboard/units/create`:

1. Enter the **unit name**
2. Set the **abbreviation**
3. Assign to a **unit group**
4. Click **Save**

### Editing Units

Navigate to `/dashboard/units/edit/{unit}`:

1. Modify any field
2. Click **Save**

### Unit Groups

Navigate to `/dashboard/units/groups` to manage unit groups.

**Creating a Unit Group:**

1. Click **Add New**
2. Enter the **group name** (e.g., "Weight," "Volume")
3. Click **Save**

**Editing a Unit Group:**

1. Click the **Edit** icon
2. Modify the group details
3. Click **Save**

### Unit Conversions

Within a unit group, you can define conversions between units:

1. Open the unit group
2. Add units with their **conversion factor** relative to the base unit
3. Example: In a "Weight" group, 1 kg = 1000 g

> **Tip:** Unit groups and conversions are essential for products sold in different quantities (e.g., buying in boxes and selling in pieces).

---

## 14. Reports

The Reports section provides detailed analytics and summaries of your business operations.

### Sales Report

Navigate to `/dashboard/reports/sales` for a comprehensive sales report.

| Data                    | Description                      |
| ----------------------- | -------------------------------- |
| **Total Sales**         | Revenue over the selected period |
| **Number of Orders**    | Total orders processed           |
| **Average Order Value** | Mean value per order             |
| **Sales by Category**   | Breakdown by product category    |
| **Sales by Cashier**    | Performance per cashier          |

Filter by date range, cashier, or customer.

### Sales Progress

Navigate to `/dashboard/reports/sales-progress` to track sales trends over time.

This report shows:

- Daily/weekly/monthly sales trends
- Comparison with previous periods
- Growth or decline indicators

### Low Stock Report

Navigate to `/dashboard/reports/low-stock` to identify products that need restocking.

| Column              | Description                    |
| ------------------- | ------------------------------ |
| **Product**         | Product name                   |
| **Current Stock**   | Available quantity             |
| **Alert Threshold** | Minimum stock level            |
| **Status**          | Low, Critical, or Out of Stock |

> **Tip:** Use this report to create procurement orders before products run out.

### Sold Stock Report

Navigate to `/dashboard/reports/sold-stock` to see how much stock has been sold over a period.

This helps you understand:

- Fast-moving products
- Slow-moving products
- Seasonal demand patterns

### Stock History Report

Navigate to `/dashboard/reports/stock-history` for a detailed log of all stock movements.

Filter by:

- Date range
- Product
- Movement type
- User

### Profit Report

Navigate to `/dashboard/reports/profit` to view your profit margins.

| Data              | Description                       |
| ----------------- | --------------------------------- |
| **Revenue**       | Total sales income                |
| **Cost of Goods** | Total purchase cost of sold items |
| **Gross Profit**  | Revenue minus cost of goods       |
| **Expenses**      | Operating expenses                |
| **Net Profit**    | Gross profit minus expenses       |

### Transactions Report

Navigate to `/dashboard/reports/transactions` for a summary of all accounting transactions.

Filter by account, type (income/expense), or date range.

### Annual/Yearly Report

Navigate to `/dashboard/reports/annual-report` for a year-over-year business performance overview.

This report includes:

- Annual revenue trends
- Expense breakdowns
- Profit margins
- Key performance indicators

### Payment Types Report

Navigate to `/dashboard/reports/payment-types` to see sales broken down by payment method.

| Payment Type | Total Sales | Percentage |
| ------------ | ----------- | ---------- |
| Cash         | $X,XXX      | XX%        |
| Card         | $X,XXX      | XX%        |
| Other        | $X,XXX      | XX%        |

### Customer Statement

Navigate to `/dashboard/reports/customers-statement` to generate account statements for customers.

1. Select a **customer**
2. Set the **date range**
3. View all transactions, payments, and outstanding balances
4. Print or export the statement

> **Tip:** Customer statements are useful for billing accounts and credit customers.

---

## 15. Media Library

### Media Manager

Navigate to `/dashboard/medias` to access the media library. This is where you manage all images and files used in the system (product images, category images, logos, etc.).

### Uploading Images/Files

1. Click the **Upload** button
2. Select files from your computer
3. Wait for the upload to complete
4. Files appear in the media library

> **Tip:** Supported formats include JPG, PNG, GIF, SVG, and WebP.

### Updating Media Metadata

1. Click on a media item to open its details
2. Edit the **title**, **description**, or **alt text**
3. Click **Save**

### Deleting Media

**Delete Single Item:**

1. Click the **Delete** icon on the media item
2. Confirm the deletion

**Bulk Delete:**

1. Select multiple items using the checkboxes
2. Click the **Bulk Delete** button
3. Confirm

> **Warning:** Deleting a media item that is in use (e.g., a product image) will remove the image from that product.

---

## 16. Modules (Extensions)

### Modules List

Navigate to `/dashboard/modules` to view all installed modules. Modules extend the system's functionality.

| Column          | Description          |
| --------------- | -------------------- |
| **Name**        | Module name          |
| **Version**     | Module version       |
| **Status**      | Enabled or Disabled  |
| **Description** | What the module does |

### Installing/Enabling/Disabling Modules

**Enable a Module:**

1. Find the module in the list
2. Click the **Enable** toggle or button
3. The module becomes active

**Disable a Module:**

1. Find the module
2. Click the **Disable** toggle or button
3. The module's features are hidden

### Uploading Modules

Navigate to `/dashboard/modules/upload` to install a new module:

1. Click **Choose File**
2. Select the module package (ZIP file)
3. Click **Upload**
4. The system installs the module
5. Enable it from the modules list

> **Note:** Only upload modules from trusted sources.

### Running Module Migrations

Navigate to `/dashboard/modules/migrate/{namespace}` to run database migrations for a specific module.

This is typically needed after installing or updating a module to set up its database tables.

1. Select the module
2. Click **Migrate**
3. Confirm

### Fixing Permissions

If a module is not working correctly, you may need to fix file permissions:

1. Navigate to the modules section
2. Click **Fix Permissions**
3. The system corrects any permission issues

> **Tip:** After installing or updating modules, always refresh the page to see new menu items.

---

## 17. Users & Roles

### Users List

Navigate to `/dashboard/users` to view all system users.

| Column         | Description        |
| -------------- | ------------------ |
| **Name**       | User's full name   |
| **Email**      | Email address      |
| **Role**       | Assigned role      |
| **Status**     | Active or Inactive |
| **Last Login** | Last login date    |

### Creating Users

Navigate to `/dashboard/users/create`:

| Field          | Description                            |
| -------------- | -------------------------------------- |
| **Username**   | Login username (required)              |
| **Email**      | Email address (required)               |
| **Password**   | Initial password                       |
| **First Name** | User's first name                      |
| **Last Name**  | User's last name                       |
| **Role**       | Assign a role (determines permissions) |
| **Language**   | Preferred interface language           |

Click **Save** to create the user.

### Editing Users

Navigate to `/dashboard/users/edit/{user}`:

1. Modify any field
2. To change the password, enter a new one
3. Click **Save**

### User Profile

Navigate to `/dashboard/users/profile` to view and edit your own profile.

You can update:

- Your name
- Email address
- Password
- Language preference
- Avatar photo

### Roles

Navigate to `/dashboard/users/roles` to manage roles. Roles define what actions a user can perform.

**Creating a Role:**

1. Click **Add New**
2. Enter the **role name** (e.g., "Cashier," "Store Manager")
3. Set **permissions** (see Permissions Manager below)
4. Click **Save**

**Editing a Role:**

1. Click the **Edit** icon next to the role
2. Modify the name or permissions
3. Click **Save**

**Cloning a Role:**

1. Click the **Clone** icon next to the role
2. A copy is created with the same permissions
3. Edit the clone to customize it

### Permissions Manager

Navigate to `/dashboard/users/roles/permissions-manager` for a detailed view of all permissions.

Permissions are organized by module:

| Module    | Example Permissions                  |
| --------- | ------------------------------------ |
| Products  | Create, Read, Update, Delete         |
| Orders    | Create, Read, Update, Delete, Refund |
| Customers | Create, Read, Update, Delete         |
| Reports   | View Sales, View Profit, Export      |
| Settings  | Read, Update                         |
| Users     | Create, Read, Update, Delete         |

> **Tip:** Follow the principle of least privilege — give users only the permissions they need.

### API Tokens

API tokens allow external applications to interact with the system.

**Creating a Token:**

1. Go to your user profile or the API section
2. Click **Create Token**
3. Enter a **name** for the token
4. Set the **permissions** (scopes)
5. Click **Generate**
6. **Copy the token immediately** — it will not be shown again

**Managing Tokens:**

- View all active tokens
- Revoke (delete) tokens that are no longer needed

> **Warning:** Treat API tokens like passwords. Do not share them publicly.

---

## 18. Settings

### Accessing Settings

Navigate to `/dashboard/settings/{identifier}` to access system settings. The settings are organized into categories.

### General Settings

| Setting           | Description                |
| ----------------- | -------------------------- |
| **Store Name**    | Name of your business      |
| **Store Address** | Physical address           |
| **Phone**         | Contact phone number       |
| **Email**         | Contact email              |
| **Currency**      | Default currency           |
| **Language**      | Default interface language |
| **Date Format**   | How dates are displayed    |
| **Timezone**      | System timezone            |

### Email Settings

| Setting           | Description                         |
| ----------------- | ----------------------------------- |
| **Mail Driver**   | Email service (SMTP, Mailgun, etc.) |
| **SMTP Host**     | Mail server address                 |
| **SMTP Port**     | Server port                         |
| **SMTP Username** | Email account username              |
| **SMTP Password** | Email account password              |
| **From Address**  | Sender email address                |
| **From Name**     | Sender name                         |

### Invoice Settings

| Setting                   | Description                           |
| ------------------------- | ------------------------------------- |
| **Invoice Header**        | Custom text/logo at the top           |
| **Invoice Footer**        | Custom text at the bottom             |
| **Invoice Number Format** | Prefix, suffix, and numbering pattern |
| **Tax Display**           | Show tax inclusive or exclusive       |

### Payment Settings

| Setting                    | Description                      |
| -------------------------- | -------------------------------- |
| **Default Payment Type**   | Default payment method at POS    |
| **Enable Customer Credit** | Allow customers to buy on credit |
| **Enable Coupons**         | Allow coupon discounts           |

### Tax Settings

| Setting                   | Description                    |
| ------------------------- | ------------------------------ |
| **Default Tax**           | Tax applied by default         |
| **Tax Inclusive Pricing** | Whether prices include tax     |
| **Tax Display on POS**    | Show tax breakdown at checkout |

> **Tip:** After changing settings, click **Save** and verify the changes by checking the affected area.

---

## 19. Notifications

### Viewing Notifications

Notifications appear as a bell icon in the top-right corner of the screen.

1. Click the **bell icon**
2. A dropdown shows recent notifications
3. Click **View All** to see the full notifications page

Notifications may include:

- Low stock alerts
- System updates
- Register session reminders
- Module installation confirmations

### Dismissing Notifications

**Dismiss Single Notification:**

1. Open the notifications dropdown
2. Click the **X** on the notification

**Dismiss All:**

1. Open the notifications page
2. Click **Dismiss All** or **Mark All as Read**

> **Tip:** Do not ignore low stock or system update notifications — they may require action.

---

## 20. System Maintenance

### Database Updates

Navigate to `/database-update` to check for and apply database updates.

1. The system checks for pending migrations
2. Click **Update** to apply them
3. Wait for the process to complete

> **Note:** Database updates are usually automatic, but you may be prompted to run them manually after a system update.

### Resetting with Demo Data

For testing or training purposes, you can reset the system with demo data:

1. Navigate to the reset section (usually under Settings or System)
2. Click **Reset with Demo Data**
3. Confirm the action

> **Warning:** This deletes all existing data and replaces it with sample data. Do NOT use on a production system.

### Fixing Symbolic Links

If images or files are not displaying correctly, you may need to fix symbolic links:

1. Navigate to the system maintenance section
2. Click **Fix Symlinks**
3. The system recreates the storage links

> **Tip:** This is commonly needed after a fresh installation or server migration.

---

## Appendix A: Keyboard Shortcuts (POS)

| Shortcut | Action                      |
| -------- | --------------------------- |
| `F2`     | Focus search bar            |
| `F4`     | Hold current order          |
| `F5`     | Open pending orders         |
| `F8`     | Open payment screen         |
| `F9`     | Apply discount              |
| `F10`    | Open customer selector      |
| `Escape` | Cancel/close current dialog |

> **Note:** Keyboard shortcuts may vary based on system configuration.

## Appendix B: Common Troubleshooting

| Problem                         | Solution                                                              |
| ------------------------------- | --------------------------------------------------------------------- |
| Cannot log in                   | Verify username and password. Use password recovery if needed.        |
| Product not found in POS search | Check that the product is active and has stock.                       |
| Barcode scanner not working     | Ensure the scanner is configured to send an Enter key after scanning. |
| Receipt not printing            | Check printer connection and configuration in Settings.               |
| Stock not updating after sale   | Verify stock management is enabled for the product.                   |
| Page loading slowly             | Clear browser cache or try a different browser.                       |
| Image not displaying            | Fix symbolic links in System Maintenance.                             |

## Appendix C: Glossary

| Term               | Definition                                              |
| ------------------ | ------------------------------------------------------- |
| **POS**            | Point of Sale — the interface for processing sales      |
| **Procurement**    | A purchase order from a supplier                        |
| **Provider**       | A supplier or vendor                                    |
| **SKU**            | Stock Keeping Unit — a unique product identifier        |
| **Z-Report**       | End-of-day register summary report                      |
| **Tax Inclusive**  | Price includes tax (customer pays the displayed price)  |
| **Tax Exclusive**  | Tax is added on top of the displayed price              |
| **Unit Group**     | A collection of related units (e.g., Weight: kg, g, lb) |
| **Customer Group** | A category of customers with shared pricing rules       |
| **Role**           | A set of permissions assigned to users                  |
| **Module**         | An extension that adds features to the system           |
| **Instalment**     | A payment plan allowing partial payments over time      |
| **COGS**           | Cost of Goods Sold — the purchase cost of sold items    |

---

_End of Manual_

For additional support, contact your system administrator or refer to the NexoPOS documentation.
