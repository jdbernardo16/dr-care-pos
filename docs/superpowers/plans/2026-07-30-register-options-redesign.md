# Register Options Modal Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the register options popup to show a detailed cash summary and sales summary (Loyverse-inspired), retaining the 4 action buttons.

**Architecture:** New backend endpoint computes both summaries server-side reusing existing Z-report patterns. Frontend popup calls new endpoint and renders a two-column layout.

**Tech Stack:** Laravel PHP backend, Vue 2 + Tailwind CSS v4 frontend, MySQL

---

### Task 1: Backend — Add `getRegisterSessionSummary()` to CashRegistersService

**Files:**
- Modify: `app/Services/CashRegistersService.php` (after `getRegisterDetails()`)

- [ ] **Step 1: Add the new service method**

Add this method to `CashRegistersService.php` after line 448 (after `getRegisterDetails()`):

```php
public function getRegisterSessionSummary(Register $register): array
{
    if ($register->status !== Register::STATUS_OPENED) {
        throw new NotAllowedException(__('Unable to get session summary for a closed register.'));
    }

    $opening = $register->history()
        ->where('action', RegisterHistory::ACTION_OPENING)
        ->orderBy('id', 'desc')
        ->first();

    if (!$opening instanceof RegisterHistory) {
        throw new NotAllowedException(__('The register doesn\'t have an opening history.'));
    }

    $openingDate = $opening->created_at;
    $startingCash = (float) $opening->value;

    // Paid orders since opening
    $orders = Order::paid()
        ->where('register_id', $register->id)
        ->where('created_at', '>=', $openingDate)
        ->get();

    $orderIds = $orders->pluck('id');

    // Gross/Net sales from paid orders
    $grossSales = (float) $orders->sum('subtotal');
    $discounts = (float) $orders->sum('discount');
    $netSales = (float) $orders->sum('total');

    // Cash payment identifiers
    $cashPaymentIdentifiers = PaymentType::where('is_cash', true)
        ->get()
        ->pluck('identifier')
        ->toArray();

    // Cash payments from paid orders
    $cashPayments = (float) OrderPayment::whereIn('order_id', $orderIds)
        ->whereIn('identifier', $cashPaymentIdentifiers)
        ->sum('value');

    // Payment breakdown from paid orders (all types)
    $paymentBreakdownRaw = OrderPayment::whereIn('order_id', $orderIds)
        ->select('identifier', DB::raw('SUM(value) as total_amount'))
        ->groupBy('identifier')
        ->get();

    $paymentBreakdown = $paymentBreakdownRaw->map(function ($payment) {
        $paymentType = PaymentType::where('identifier', $payment->identifier)->first();
        return [
            'label' => $paymentType ? $paymentType->label : $payment->identifier,
            'value' => (float) $payment->total_amount,
        ];
    })->values()->toArray();

    // Register history since opening
    $histories = RegisterHistory::where('register_id', $register->id)
        ->where('created_at', '>=', $openingDate)
        ->get();

    $paidIn = (float) $histories->where('action', RegisterHistory::ACTION_CASHING)->sum('value');
    $paidOut = (float) $histories->where('action', RegisterHistory::ACTION_CASHOUT)->sum('value');

    // Cash refunds from register history filtered by cash payment type
    $refundHistories = $histories->where('action', RegisterHistory::ACTION_REFUND);
    $cashRefunds = 0;
    foreach ($refundHistories as $history) {
        if ($history->payment_type_id) {
            $paymentType = PaymentType::find($history->payment_type_id);
            if ($paymentType && $paymentType->is_cash) {
                $cashRefunds += (float) $history->value;
            }
        }
    }

    // Sales refunds total (all refunded orders since opening)
    $refundedOrdersTotal = (float) Order::whereIn('payment_status', [
            Order::PAYMENT_REFUNDED,
            Order::PAYMENT_PARTIALLY_REFUNDED,
        ])
        ->where('register_id', $register->id)
        ->where('created_at', '>=', $openingDate)
        ->sum('total');

    $expectedCash = $startingCash + $cashPayments + $paidIn - $cashRefunds - $paidOut;

    return [
        'cash_summary' => [
            'starting_cash' => $startingCash,
            'cash_payments' => $cashPayments,
            'cash_refunds' => $cashRefunds,
            'paid_in' => $paidIn,
            'paid_out' => $paidOut,
            'expected_cash' => max(0, $expectedCash),
        ],
        'sales_summary' => [
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'net_sales' => $netSales,
            'refunds' => $refundedOrdersTotal,
            'payment_breakdown' => $paymentBreakdown,
        ],
    ];
}
```

- [ ] **Step 2: Add missing import for PaymentType**

Ensure `use App\Models\PaymentType;` is already imported at the top of `CashRegistersService.php` (line 9). If not, add it.

---

### Task 2: Backend — Add controller method and route

**Files:**
- Modify: `app/Http/Controllers/Dashboard/CashRegistersController.php`
- Modify: `routes/api/registers.php`

- [ ] **Step 1: Add `getSessionSummary()` to CashRegistersController**

Add this method to the controller (after `getRegisters()`, around line 70):

```php
public function getSessionSummary(Register $register)
{
    return $this->registersService->getRegisterSessionSummary($register);
}
```

- [ ] **Step 2: Add the route**

Add this line to `routes/api/registers.php` inside the `nexopos.read.registers` middleware group (after the session-history route, line 10):

```php
Route::get( 'cash-registers/{register}/session-summary', [ CashRegistersController::class, 'getSessionSummary' ] );
```

---

### Task 3: Frontend — Redesign register options popup

**Files:**
- Modify: `resources/ts/popups/ns-pos-cash-registers-options-popup.vue`

- [ ] **Step 1: Update the script — replace `loadRegisterSummary()`**

In the script section, replace the `loadRegisterSummary()` method and `data()` to call the new session-summary endpoint:

```js
data() {
    return {
        settings: null,
        settingsSubscriber: null,
        register: {},
        sessionSummary: null,
        loadingSummary: false,
    }
},
```

Replace the existing `loadRegisterSummary()` method:

```js
loadRegisterSummary() {
    if ( this.settings.register === undefined ) {
        setTimeout( () => {
            this.popup.close();
        }, 500 );
        
        return nsSnackBar.error( __( 'The register is not yet loaded.' ) );
    }

    this.loadingSummary  =   true;

    nsHttpClient.get( `/api/cash-registers/${this.settings.register.id}` )
        .subscribe( result => {
            this.register   =   result;
        });

    nsHttpClient.get( `/api/cash-registers/${this.settings.register.id}/session-summary` )
        .subscribe( result => {
            this.sessionSummary  =   result;
            this.loadingSummary  =   false;
        });
},
```

- [ ] **Step 2: Replace the template summary section**

Replace the section between the header and the buttons grid (lines 159-173) with the new two-column layout:

```vue
<div v-if="sessionSummary && !loadingSummary">
    <div class="flex">
        <!-- Cash Summary -->
        <div class="w-1/2 border-r border-box-edge">
            <div class="p-2 bg-success-primary success border-b border-box-edge">
                <h3 class="font-bold text-sm uppercase tracking-wide">{{ __( 'Cash Summary' ) }}</h3>
            </div>
            <div class="text-sm">
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Starting Cash' ) }}</span>
                    <span class="font-semibold">{{ nsCurrency( sessionSummary.cash_summary.starting_cash ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Cash Payments' ) }}</span>
                    <span class="font-semibold">{{ nsCurrency( sessionSummary.cash_summary.cash_payments ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Cash Refunds' ) }}</span>
                    <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.cash_summary.cash_refunds ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Paid In' ) }}</span>
                    <span class="font-semibold text-success-primary">{{ nsCurrency( sessionSummary.cash_summary.paid_in ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Paid Out' ) }}</span>
                    <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.cash_summary.paid_out ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 bg-success-secondary success font-bold">
                    <span>{{ __( 'Expected Cash' ) }}</span>
                    <span>{{ nsCurrency( sessionSummary.cash_summary.expected_cash ) }}</span>
                </div>
            </div>
        </div>
        <!-- Sales Summary -->
        <div class="w-1/2">
            <div class="p-2 bg-info-primary info border-b border-box-edge">
                <h3 class="font-bold text-sm uppercase tracking-wide">{{ __( 'Sales Summary' ) }}</h3>
            </div>
            <div class="text-sm">
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Gross Sales' ) }}</span>
                    <span class="font-semibold">{{ nsCurrency( sessionSummary.sales_summary.gross_sales ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Discounts' ) }}</span>
                    <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.sales_summary.discounts ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                    <span>{{ __( 'Refunds' ) }}</span>
                    <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.sales_summary.refunds ) }}</span>
                </div>
                <div class="flex justify-between px-3 py-2 bg-info-primary info font-bold border-b border-box-edge">
                    <span>{{ __( 'Net Sales' ) }}</span>
                    <span>{{ nsCurrency( sessionSummary.sales_summary.net_sales ) }}</span>
                </div>
                <div v-for="payment in sessionSummary.sales_summary.payment_breakdown" :key="payment.label" class="flex justify-between px-3 py-1.5 border-b border-box-edge pl-6">
                    <span>{{ payment.label }}</span>
                    <span class="font-semibold">{{ nsCurrency( payment.value ) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="h-32 ns-box-body border-b py-1 flex items-center justify-center" v-if="loadingSummary">
    <div>
        <ns-spinner border="4" size="16"></ns-spinner>
    </div>
</div>
```

- [ ] **Step 3: Keep the buttons grid exactly as-is**

The bottom section (lines 174-191) with Close, Cash In, Cash Out, History buttons stays unchanged.

---

### Task 4: Verify the changes

- [ ] **Step 1: Clear view cache**

Run: `php artisan view:clear` and `php artisan cache:clear`

- [ ] **Step 2: Test in browser**

Navigate to POS → click Cash Register button → verify the new register options modal shows the two-column layout with cash summary and sales summary.

- [ ] **Step 3: Verify buttons still work**

Test Close, Cash In, Cash Out, and History buttons function correctly.
