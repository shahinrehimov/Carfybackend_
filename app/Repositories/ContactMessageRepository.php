<?php

namespace App\Repositories;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContactMessageRepository
{
    public function load(array $validated): LengthAwarePaginator
    {
        return ContactMessage::query()
            ->when(
                $validated['search'] ?? null,
                fn($q, $search) => $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                })
            )
            ->when(
                isset($validated['is_read']),
                fn($q) => $q->where('is_read', $validated['is_read'])
            )
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function store(Request $request): ContactMessage
    {
        return DB::transaction(fn() => ContactMessage::create($request->validated()));
    }

    public function markAsRead(ContactMessage $contactMessage): void
    {
        DB::transaction(fn() => $contactMessage->update(['is_read' => true]));
    }

    public function markAsUnread(ContactMessage $contactMessage): void
    {
        DB::transaction(fn() => $contactMessage->update(['is_read' => false]));
    }

    public function destroy(ContactMessage $contactMessage): void
    {
        DB::transaction(fn() => $contactMessage->delete());
    }
}
