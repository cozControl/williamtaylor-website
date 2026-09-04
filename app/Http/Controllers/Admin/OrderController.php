<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\Actions\AddOrderNote;
use App\Domain\Orders\Actions\ChangeOrderPaymentStatus;
use App\Domain\Orders\Actions\CreateDemoOrder;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Support\Demo\DemoMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    public function index(Request $request, DemoMode $demo): View
    {
        abort_unless($demo->configured(), 404);
        $query = Order::query()->withCount('items')->where('is_demo', true);
        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_telephone', 'like', "%{$search}%"));
        }
        foreach (['status', 'payment_status'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($from = $request->date('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        $query->orderBy('created_at', $request->query('sort') === 'oldest' ? 'asc' : 'desc')->orderBy('id');

        return view('admin.orders.index', ['orders' => $query->paginate(20)->withQueryString(), 'statuses' => OrderStatus::cases(), 'paymentStatuses' => PaymentStatus::cases()]);
    }

    public function create(DemoMode $demo): View
    {
        abort_unless($demo->configured(), 404);

        return view('admin.orders.create', ['idempotencyKey' => (string) str()->ulid()]);
    }

    public function store(Request $request, CreateDemoOrder $create): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:80'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['required', 'email', 'max:254'],
            'customer_telephone' => ['required', 'string', 'max:40'],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_instructions' => ['nullable', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000', 'not_regex:/<[^>]+>/'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.variant_name' => ['nullable', 'string', 'max:200'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.unit_amount_minor' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);
        $order = $create->handle($request->user(), $data);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Demo Order created.');
    }

    public function show(Order $order, DemoMode $demo): View
    {
        abort_unless($demo->configured() && $order->is_demo, 404);

        return view('admin.orders.show', ['order' => $order->load(['items', 'statusEvents.actor', 'notes.actor', 'paymentEvents.actor'])]);
    }

    public function transition(Request $request, Order $order, TransitionOrder $transition): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'lock_version' => ['required', 'integer'], 'reason' => ['nullable', 'string', 'max:500']]);
        $next = OrderStatus::tryFrom($data['status']);
        if ($next === null) {
            abort(422);
        }
        $transition->handle($request->user(), $order, $next, $data['lock_version'], $data['reason'] ?? null);

        return back()->with('status', 'Order status updated.');
    }

    public function note(Request $request, Order $order, AddOrderNote $add): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $add->handle($request->user(), $order, $data['note']);

        return back()->with('status', 'Internal note added.');
    }

    public function payment(Request $request, Order $order, ChangeOrderPaymentStatus $change): RedirectResponse
    {
        $data = $request->validate(['payment_status' => ['required', 'string'], 'lock_version' => ['required', 'integer'], 'payment_reason' => ['required', 'string', 'max:500']]);
        $next = PaymentStatus::tryFrom($data['payment_status']);
        if ($next === null) {
            abort(422);
        }
        $change->handle($request->user(), $order, $next, $data['lock_version'], $data['payment_reason']);

        return back()->with('status', 'Demonstration payment status updated.');
    }

    public function summary(Order $order, DemoMode $demo): View
    {
        abort_unless($demo->configured() && $order->is_demo, 404);

        return view('admin.orders.summary', ['order' => $order->load('items')]);
    }

    public function receipt(Order $order, DemoMode $demo): View
    {
        abort_unless($demo->configured() && $order->is_demo, 404);
        abort_unless($order->payment_status === PaymentStatus::Paid && $order->receipt_reference, 404);

        return view('admin.orders.receipt', ['order' => $order->load('items')]);
    }
}
