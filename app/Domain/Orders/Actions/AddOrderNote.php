<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderNote;
use App\Models\User;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AddOrderNote
{
    public function __construct(private DemoMode $demo, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Order $order, string $note): OrderNote
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_NOTES_CREATE);
        abort_unless($this->demo->configured() && $order->is_demo, 404);
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 2000 || $note !== strip_tags($note)) {
            throw ValidationException::withMessages(['note' => 'Enter 1–2000 plain-text characters.']);
        }
        $created = $order->notes()->create(['actor_id' => $actor->id, 'visibility' => 'internal', 'note' => $note, 'created_at' => now('UTC')]);
        $this->audit->handle('order.note-added', $order, $actor, null, ['note_id' => $created->id], PermissionRegistry::ORDERS_NOTES_CREATE);

        return $created;
    }
}
