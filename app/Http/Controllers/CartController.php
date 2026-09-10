<?php

namespace App\Http\Controllers;

use App\Domain\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CartController
{
    public function show(Request $request, CartService $cart): mixed
    {
        $snapshot = $cart->snapshot();

        return $request->expectsJson() ? $this->response($snapshot, '') : response()->view('frontend.cart', ['cart' => $snapshot, 'loadImportedStorefrontRuntime' => false])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, CartService $cart): mixed
    {
        return $this->mutate($request, $cart, function () use ($request, $cart) {
            $data = $request->validate(['variant_id' => 'required|ulid', 'quantity' => 'required|integer|min:1|max:2147483647'], ['variant_id.*' => 'Choose an available option.', 'quantity.*' => 'Enter a positive whole quantity within available stock.']);
            $cart->add($data['variant_id'], (int) $data['quantity']);
        }, 'Added to your bag.');
    }

    public function update(Request $request, string $variant, CartService $cart): mixed
    {
        return $this->mutate($request, $cart, function () use ($request, $variant, $cart) {
            $data = $request->validate(['quantity' => 'required|integer|min:1|max:2147483647'], ['quantity.*' => 'Enter a positive whole quantity within available stock.']);
            $cart->update($variant, (int) $data['quantity']);
        }, 'Cart updated.');
    }

    public function destroy(Request $request, string $variant, CartService $cart): mixed
    {
        return $this->mutate($request, $cart, fn () => $cart->remove($variant), 'Item removed.');
    }

    private function mutate(Request $request, CartService $cart, callable $action, string $message): mixed
    {
        try {
            $action();
        } catch (ValidationException $error) {
            if (! $request->expectsJson()) {
                throw $error;
            }

            return $this->response($cart->snapshot(), collect($error->errors())->flatten()->first(), 422);
        }

        return $request->expectsJson() ? $this->response($cart->snapshot(), $message) : redirect()->route('cart.show')->with('cart_message', $message);
    }

    /** @param array<string, mixed> $cart */
    private function response(array $cart, string $message, int $status = 200): mixed
    {
        return response()->json(['cart' => $cart, 'html' => view('frontend.partials.cart-content', compact('cart'))->render(), 'message' => $message], $status)->header('Cache-Control', 'private, no-store');
    }
}
