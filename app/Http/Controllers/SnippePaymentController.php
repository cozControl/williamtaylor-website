<?php

namespace App\Http\Controllers;

use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\ProcessSnippeWebhook;
use App\Domain\Payments\Snippe\SnippeException;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Domain\Payments\Snippe\VerifySnippeWebhook;
use Illuminate\Http\Request;

final class SnippePaymentController
{
    public function initiate(Order $order): mixed
    {
        try {
            $payment = app(StartSnippePayment::class)->start($order);
            if ($payment->active_order_id !== null && $payment->provider_checkout_url !== null && ! $payment->expires_at?->isPast() && $payment->reconciliation_issue === null && in_array($payment->last_provider_status, ['pending', 'active'], true)) {
                return redirect()->away($payment->provider_checkout_url)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
            }
        } catch (SnippeException|\InvalidArgumentException) {
            // The Order is already committed. Never show it as a failed Order.
        }

        return redirect()->route('checkout.confirmation', $order->confirmation_reference)->with('payment_notice', "Your order has been saved. We couldn't open the secure payment page right now. Please check its status or try payment again.")->header('Cache-Control', 'private, no-store');
    }

    public function retry(string $reference): mixed
    {
        abort_unless(config('snippe.enabled'), 404);
        $order = Order::query()->where('confirmation_reference', $reference)->firstOrFail();
        if ($order->status !== OrderStatus::PendingConfirmation || $order->payment_status !== 'unpaid') {
            return redirect()->route('checkout.confirmation', $reference);
        }

        return $this->initiate($order);
    }

    public function returned(string $reference): mixed
    {
        $payment = Payment::query()->where('return_reference', $reference)->firstOrFail();
        $order = Order::query()->with('lines')->findOrFail($payment->order_id);

        // Browser return is read-only. Reconciliation is a separate server command.
        return response()->view('frontend.order-confirmation', ['order' => $order, 'payment' => $payment, 'loadImportedStorefrontRuntime' => false])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function webhook(Request $request, VerifySnippeWebhook $verify, ProcessSnippeWebhook $process): mixed
    {
        $outcome = $process->process($verify->verify($request), $request->getContent());

        return response()->json(['received' => in_array($outcome, ['processed', 'ignored'], true)], in_array($outcome, ['processed', 'ignored'], true) ? 200 : 409);
    }
}
