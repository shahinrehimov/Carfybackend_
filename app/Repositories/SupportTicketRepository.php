<?php

namespace App\Repositories;

use App\Enum\SupportTicketStatusEnum;
use App\Models\Admin;
use App\Models\CompanyAdmin;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupportTicketRepository
{
    public function loadForSeller(array $validated, int $companyId): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->where('company_id', $companyId)
            ->withCount(['messages'])
            ->withCount([
                'messages as unread_count' => fn($q) => $q
                    ->where('is_read_by_seller', false)
                    ->where('sender_type', Admin::class),
            ])
            ->when($validated['status'] ?? null, fn($q) => $q->where('status', $validated['status']))
            ->when($validated['search'] ?? null, fn($q) => $q->where('subject', 'like', '%' . $validated['search'] . '%'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function loadForAdmin(array $validated): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->with(['company:id,store_code', 'assignedAdmin:id,name,surname'])
            ->withCount(['messages'])
            ->withCount([
                'messages as unread_count' => fn($q) => $q
                    ->where('is_read_by_admin', false)
                    ->where('sender_type', CompanyAdmin::class),
            ])
            ->when($validated['status'] ?? null, fn($q) => $q->where('status', $validated['status']))
            ->when($validated['company_id'] ?? null, fn($q) => $q->where('company_id', $validated['company_id']))
            ->when($validated['assigned_to_admin_id'] ?? null, fn($q) => $q->where('assigned_to_admin_id', $validated['assigned_to_admin_id']))
            ->when($validated['search'] ?? null, fn($q) => $q->where('subject', 'like', '%' . $validated['search'] . '%'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function showForSeller(SupportTicket $ticket): SupportTicket
    {
        $ticket->load(['messages.media']);
        $ticket->loadCount(['messages']);

        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('sender_type', Admin::class)
            ->where('is_read_by_seller', false)
            ->update(['is_read_by_seller' => true]);

        return $ticket;
    }

    public function showForAdmin(SupportTicket $ticket): SupportTicket
    {
        $ticket->load(['messages.media', 'messages.sender', 'company:id,store_code', 'assignedAdmin:id,name,surname']);
        $ticket->loadCount(['messages']);

        SupportMessage::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('sender_type', CompanyAdmin::class)
            ->where('is_read_by_admin', false)
            ->update(['is_read_by_admin' => true]);

        return $ticket;
    }

    public function storeForSeller(Request $request, int $companyId, CompanyAdmin $companyAdmin): SupportTicket
    {
        return DB::transaction(function () use ($request, $companyId, $companyAdmin) {
            $validated = $request->validated();

            $ticket = SupportTicket::create([
                'company_id'   => $companyId,
                'subject'      => $validated['subject'],
                'status'       => SupportTicketStatusEnum::OPEN,
                'opened_by'    => 'seller',
                'opened_by_id' => $companyAdmin->id,
                'last_message_at' => now(),
            ]);

            $message = $this->createMessage($ticket, CompanyAdmin::class, $companyAdmin->id, $validated['body'], false, true);

            FileUpload::multipleUpload($request, 'files', 'support_files', $message);

            return $ticket;
        });
    }

    public function storeForAdmin(Request $request, Admin $admin): SupportTicket
    {
        return DB::transaction(function () use ($request, $admin) {
            $validated = $request->validated();

            $ticket = SupportTicket::create([
                'company_id'         => $validated['company_id'],
                'subject'            => $validated['subject'],
                'status'             => SupportTicketStatusEnum::OPEN,
                'opened_by'          => 'admin',
                'opened_by_id'       => $admin->id,
                'assigned_to_admin_id' => $admin->id,
                'last_message_at'    => now(),
            ]);

            $message = $this->createMessage($ticket, Admin::class, $admin->id, $validated['body'], true, false);

            FileUpload::multipleUpload($request, 'files', 'support_files', $message);

            return $ticket;
        });
    }

    public function replyForSeller(Request $request, SupportTicket $ticket, CompanyAdmin $companyAdmin): SupportMessage
    {
        return DB::transaction(function () use ($request, $ticket, $companyAdmin) {
            $validated = $request->validated();

            $message = $this->createMessage($ticket, CompanyAdmin::class, $companyAdmin->id, $validated['body'], false, true);

            FileUpload::multipleUpload($request, 'files', 'support_files', $message);

            $ticket->update([
                'last_message_at' => now(),
                'status' => $ticket->isClosed() ? $ticket->status : SupportTicketStatusEnum::IN_PROGRESS,
            ]);

            return $message;
        });
    }

    public function replyForAdmin(Request $request, SupportTicket $ticket, Admin $admin): SupportMessage
    {
        return DB::transaction(function () use ($request, $ticket, $admin) {
            $validated = $request->validated();

            $message = $this->createMessage($ticket, Admin::class, $admin->id, $validated['body'], true, false);

            FileUpload::multipleUpload($request, 'files', 'support_files', $message);

            $ticket->update([
                'last_message_at' => now(),
                'status' => $ticket->isClosed() ? $ticket->status : SupportTicketStatusEnum::IN_PROGRESS,
            ]);

            return $message->load('media');
        });
    }

    public function assign(SupportTicket $ticket, int $adminId): SupportTicket
    {
        $ticket->update(['assigned_to_admin_id' => $adminId]);
        return $ticket->load('assignedAdmin:id,name,surname');
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicketStatusEnum::CLOSED]);
        return $ticket;
    }

    public function getFilters(): array
    {
        return [
            'statuses' => SupportTicketStatusEnum::getList(),
        ];
    }

    private function createMessage(
        SupportTicket $ticket,
        string $senderType,
        int $senderId,
        string $body,
        bool $isReadBySeller,
        bool $isReadByAdmin
    ): SupportMessage {
        return SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type'       => $senderType,
            'sender_id'         => $senderId,
            'body'              => $body,
            'is_read_by_seller' => $isReadBySeller,
            'is_read_by_admin'  => $isReadByAdmin,
        ]);
    }
}
