<?php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\Models\Order;
use App\Domain\Checkout\PlaceOrderService;
use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CheckoutController
{
    public function show(Request $request, CartService $cart): mixed
    {
        $summary = $cart->snapshot();
        $submission = bin2hex(random_bytes(32));
        $attempts = $request->session()->get('checkout_attempts', []);
        $attempts[$submission] = PlaceOrderService::reviewFingerprint($summary);
        $request->session()->put('checkout_attempts', array_slice($attempts, -20, null, true));

        return response()->view('frontend.checkout', ['cart' => $summary, 'submission' => $submission, 'loadImportedStorefrontRuntime' => false])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, PlaceOrderService $place): mixed
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/[ ()-]/', '', trim($request->input('phone')))]);
        }
        $data = $request->validate([
            'submission' => 'required|string|size:64', 'name' => 'required|string|max:160',
            'email' => 'required|email|max:254', 'phone' => ['required', 'string', 'max:16', 'regex:/^\+?[0-9]{7,15}$/'],
            'address' => 'required|string|max:500', 'city' => 'required|string|max:120', 'region' => 'required|string|max:120', 'postal' => 'nullable|string|max:20',
        ]);
        $key = $data['submission'];
        unset($data['submission']);
        foreach ($data as $field => $value) {
            $data[$field] = is_string($value) ? trim($value) : $value;
        }
        $data['email'] = mb_strtolower($data['email']);
        $data['phone'] = preg_replace('/[ ()-]/', '', $data['phone']);
        $data['postal'] ??= null;
        ksort($data);
        $review = $request->session()->get('checkout_attempts', [])[$key] ?? null;
        if (! is_string($review)) {
            throw ValidationException::withMessages(['cart' => 'Please open checkout again before placing your order.']);
        }
        $order = $place->place($data, hash('sha256', $key), $review);
        if (config('snippe.enabled')) {
            return app(SnippePaymentController::class)->initiate($order);
        }

        return redirect()->route('checkout.confirmation', $order->confirmation_reference)->header('Cache-Control', 'private, no-store');
    }

    public function confirmation(string $reference): mixed
    {
        $order = Order::query()->where('confirmation_reference', $reference)->with('lines')->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();

        return response()->view('frontend.order-confirmation', ['order' => $order, 'payment' => $payment, 'loadImportedStorefrontRuntime' => false])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
