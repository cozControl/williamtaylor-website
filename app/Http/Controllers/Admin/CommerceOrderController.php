<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Cart\CartPresenter;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Checkout\Admin\OrderStatusPresenter;
use App\Domain\Checkout\CancelUnpaidOrderService;
use App\Domain\Checkout\Models\Order;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class CommerceOrderController extends Controller
{
    public function index(Request $request, OrderStatusPresenter $presenter, CartPresenter $money): Response
    {
        $filters = $this->filters($request, $presenter);
        $filters['search'] = $request->session()->get('commerce.orders.search.'.$request->user()?->id, '');
        $query = Order::query()->withCount('lines')->withSum('lines', 'quantity')
            ->withExists(['payments as needs_attention' => fn (Builder $q) => $q->whereNotNull('reconciliation_issue')]);
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            // Laravel compiles case-insensitive matching for the current database; values stay bound.
            $like = '%'.$search.'%';
            $phone = preg_replace('/[\s().-]+/', '', $search);
            $query->where(function (Builder $q) use ($like, $phone): void {
                foreach (['order_number', 'customer_snapshot->name', 'customer_snapshot->email', 'customer_snapshot->phone'] as $column) {
                    $q->orWhereLike($column, $like);
                }
                if (is_string($phone) && preg_match('/^\+?[0-9]{4,}$/', $phone)) {
                    $q->orWhere('customer_snapshot->phone', 'like', '%'.$phone.'%');
                }
            });
        }
        foreach (['status', 'payment_status', 'fulfillment_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['attention'])) {
            $query->whereHas('payments', fn (Builder $q) => $q->whereNotNull('reconciliation_issue'));
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $date = CarbonImmutable::parse($filters[$field], config('app.timezone'));
                $query->where('placed_at', $operator, ($field === 'from' ? $date->startOfDay() : $date->endOfDay())->utc());
            }
        }
        $metrics = [
            'Total Orders' => Order::query()->count(),
            'Awaiting payment' => Order::query()->where('status', 'pending_confirmation')->where('payment_status', 'unpaid')->count(),
            'Paid / confirmed' => Order::query()->where('status', 'confirmed')->where('payment_status', 'paid')->count(),
            'Awaiting fulfillment' => Order::query()->where('payment_status', 'paid')->where('fulfillment_status', 'unfulfilled')->count(),
            'Needs attention' => Order::query()->whereHas('payments', fn (Builder $q) => $q->whereNotNull('reconciliation_issue'))->count(),
        ];
        $orders = $query->orderByDesc('placed_at')->orderByDesc('id')->paginate(20)->appends(Arr::except($filters, ['search']));

        return $this->privateView('admin.commerce.orders.index', compact('orders', 'metrics', 'filters', 'presenter', 'money'));
    }

    public function filter(Request $request, OrderStatusPresenter $presenter): RedirectResponse
    {
        $filters = $request->boolean('clear') ? [] : $this->filters($request, $presenter);
        $request->session()->put('commerce.orders.search.'.$request->user()?->id, trim($filters['search'] ?? ''));

        return redirect()->route('admin.commerce.orders.index', Arr::except($filters, ['search']));
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, OrderStatusPresenter $presenter): array
    {
        return $request->validate([
            'search' => [$request->isMethod('POST') ? 'nullable' : 'exclude', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(array_keys($presenter->orders()))],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'paid'])],
            'fulfillment_status' => ['nullable', Rule::in(['unfulfilled'])],
            'attention' => ['nullable', Rule::in(['1'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
    }

    public function show(Order $order, OrderStatusPresenter $presenter, CartPresenter $money): Response
    {
        $order->load('lines');
        $payments = $order->payments()->orderBy('created_at')->orderBy('id')->get();
        $cancellationRestriction = app(CancelUnpaidOrderService::class)->restriction($order, $payments);
        $cancelledBy = $order->cancelled_by ? User::query()->find($order->cancelled_by)?->name : null;
        $reservations = InventoryReservation::query()->where('order_id', $order->id)->orderBy('reserved_at')->get()->keyBy('order_line_id');
        $movements = InventoryMovement::query()->where('source_type', 'commerce_order')->where('source_id', $order->id)->where('type', 'order_issue')->orderBy('occurred_at')->get();
        $products = Product::query()->whereIn('id', $order->lines->pluck('product_id'))->pluck('id');
        $variants = ProductVariant::query()->whereIn('id', $order->lines->pluck('variant_id'))->pluck('id');
        $timeline = [['at' => $order->placed_at, 'label' => 'Order placed']];
        if ($order->cancelled_at) {
            $timeline[] = ['at' => $order->cancelled_at, 'label' => 'Order cancelled · '.($cancelledBy ?? 'Staff')];
        }
        foreach ($payments as $index => $payment) {
            foreach (['created_at' => 'Payment attempt recorded', 'request_started_at' => 'Payment requested', 'failed_at' => 'Payment failed', 'completed_at' => 'Payment completed'] as $field => $label) {
                if ($payment->$field !== null) {
                    $timeline[] = ['at' => $payment->$field, 'label' => $label.' · Attempt '.($index + 1)];
                }
            }
        }
        foreach (['confirmed_at' => 'Order confirmed', 'closed_at' => $presenter->label($order->status)] as $field => $label) {
            if ($order->$field !== null && ! ($field === 'closed_at' && $order->cancelled_at !== null)) {
                $timeline[] = ['at' => $order->$field, 'label' => $label];
            }
        }
        foreach ($reservations as $reservation) {
            foreach (['reserved_at' => 'Inventory reserved', 'consumed_at' => 'Inventory reservation consumed', 'released_at' => 'Reservation released'] as $field => $label) {
                if ($reservation->$field !== null) {
                    $timeline[] = ['at' => $reservation->$field, 'label' => $label.' · '.$reservation->quantity.' units'];
                }
            }
        }
        foreach ($movements as $movement) {
            $timeline[] = ['at' => $movement->occurred_at, 'label' => 'Stock issued · '.abs($movement->quantity_delta).' units'];
        }
        // Curated attention events only; never expose audit metadata, actors' permissions or request details.
        $attentionHistory = AuditRecord::query()->where('resource_type', (new Payment)->getMorphClass())
            ->whereIn('resource_identifier', $payments->pluck('id'))->where('action', 'commerce.payment.needs_attention')
            ->latest('created_at')->limit(50)->get(['created_at']);
        foreach ($attentionHistory as $event) {
            $timeline[] = ['at' => $event->created_at, 'label' => 'Payment review required'];
        }
        usort($timeline, fn (array $a, array $b) => CarbonImmutable::parse($a['at'], 'UTC') <=> CarbonImmutable::parse($b['at'], 'UTC'));

        return $this->privateView('admin.commerce.orders.show', compact('order', 'payments', 'reservations', 'movements', 'products', 'variants', 'timeline', 'presenter', 'money', 'cancellationRestriction', 'cancelledBy'));
    }

    public function confirmCancellation(Order $order, CancelUnpaidOrderService $cancel): Response
    {
        $restriction = $cancel->restriction($order, $order->payments()->get());

        return $this->privateView('admin.commerce.orders.cancel', compact('order', 'restriction'));
    }

    public function cancel(Request $request, Order $order, CancelUnpaidOrderService $cancel): RedirectResponse
    {
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:500'], 'confirm_cancellation' => ['accepted']]);
        $message = $cancel->handle($request->user(), $order, $data['cancellation_reason']);

        return redirect()->route('admin.commerce.orders.show', $order)->with('status', $message);
    }

    public function check(Order $order, Payment $payment, StartSnippePayment $reconcile): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id, 404);
        // A bound reference is immutable. This guard excludes refresh()'s Session-creation branch.
        abort_unless($payment->provider === 'snippe' && ($payment->provider_session_reference !== null || ($payment->method === 'mobile_money' && $payment->provider_payment_reference !== null)) && $payment->active_order_id === $order->id, 409);
        $message = 'Snippe payment checks are unavailable until the integration is enabled and configured.';
        if (config('snippe.enabled') && filled(config('snippe.api_key'))) {
            if ($payment->io_lease_until?->isFuture() || $payment->next_reconcile_at?->isFuture()) {
                $message = 'A payment check is already running or waiting for its next permitted check. Please try again later.';
            } else {
                $result = $reconcile->refresh($payment);
                $message = $result->reconciliation_issue !== null || $result->failure_code !== null
                    ? 'Payment verification needs review. See the recorded payment information below.'
                    : 'Payment check finished. The latest verified status is shown below.';
            }
        }

        return redirect()->route('admin.commerce.orders.show', $order)->with('status', $message);
    }

    /** @param array<string, mixed> $data */
    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
