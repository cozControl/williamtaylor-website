<?php

namespace App\Domain\Cart;

use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;

final class CartService
{
    public function __construct(private Store $session, private CartPresenter $presenter) {}

    /** @return array<string, int> */
    public function lines(): array
    {
        $raw = $this->session->get('commerce_cart', []);
        $lines = [];
        if (is_array($raw)) {
            foreach (array_slice($raw, 0, 100, true) as $id => $quantity) {
                if (is_string($id) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $id) && is_int($quantity) && $quantity > 0 && $quantity <= 2147483647) {
                    $lines[$id] = $quantity;
                }
            }
        }
        if ($raw !== $lines) {
            $this->session->put('commerce_cart', $lines);
        }

        return $lines;
    }

    public function add(string $id, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 2147483647) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive whole quantity.']);
        }
        $lines = $this->lines();
        if (! isset($lines[$id]) && count($lines) >= 100) {
            throw ValidationException::withMessages(['cart' => 'Your bag is full. Remove an item before adding another.']);
        }
        $this->save($id, ($lines[$id] ?? 0) + $quantity, $lines);
    }

    public function update(string $id, int $quantity): void
    {
        $lines = $this->lines();
        if (! isset($lines[$id])) {
            throw ValidationException::withMessages(['cart' => 'This item is no longer in your bag.']);
        }
        $this->save($id, $quantity, $lines);
    }

    /** @param array<string, int> $lines */
    private function save(string $id, int $quantity, array $lines): void
    {
        if ($quantity < 1 || $quantity > 2147483647) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive whole quantity within available stock.']);
        }
        // Uncached reconstruction reads current canonical records and bulk availability.
        $proposed = $lines;
        $proposed[$id] = $quantity;
        $cart = $this->presenter->present($proposed);
        $line = collect($cart['lines'])->firstWhere('variant_id', $id);
        if ($line['issue'] !== null) {
            throw ValidationException::withMessages(['quantity' => $line['issue']]);
        }
        if ($cart['money_issue']) {
            throw ValidationException::withMessages(['cart' => 'This quantity cannot be priced. Please reduce it.']);
        }
        $this->session->put('commerce_cart', $proposed);
        request()->attributes->remove('cart.view');
    }

    public function remove(string $id): void
    {
        $lines = $this->lines();
        unset($lines[$id]);
        $this->session->put('commerce_cart', $lines);
        request()->attributes->remove('cart.view');
    }

    public function clear(): void
    {
        $this->session->forget('commerce_cart');
        request()->attributes->remove('cart.view');
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->presenter->present($this->lines());
    }

    /** Presentation reuse only; mutations and readiness always use snapshot().
     * @return array<string, mixed>
     */
    public function viewSnapshot(): array
    {
        if (! request()->attributes->has('cart.view')) {
            request()->attributes->set('cart.view', $this->snapshot());
        }

        return request()->attributes->get('cart.view');
    }
}
