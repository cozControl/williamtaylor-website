<?php

namespace App\Http\Controllers;

use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\ProcessSnippeWebhook;
use App\Domain\Payments\Snippe\SnippeException;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Domain\Payments\Snippe\VerifySnippeWebhook;
use App\Domain\Payments\Support\CheckoutPaymentState;
use Illuminate\Http\Request;

final class SnippePaymentController
{
    public function initiate(Order $order): mixed
    {
        try {
            $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();
            if ($payment?->method === 'mobile_money') {
                app(MobileMoneyPayment::class)->start($order);
            } elseif ($payment) {
                // Historical hosted records remain reconcilable, without creating new Sessions.
                app(StartSnippePayment::class)->refresh($payment);
            }
        } catch (SnippeException|\InvalidArgumentException) {
            // The committed Order and reservation remain available.
        }

        return redirect()->route('checkout.confirmation', $order->confirmation_reference)->header('Cache-Control', 'private, no-store');
    }

    public function status(string $reference): mixed
    {
        $order = Order::query()->where('confirmation_reference', $reference)->firstOrFail();
        $payment = $order->payments()->latest('id')->first();

        return response()->json(CheckoutPaymentState::for($order, $payment))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
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
