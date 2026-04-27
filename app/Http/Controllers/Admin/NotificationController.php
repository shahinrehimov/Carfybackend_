<?php

namespace App\Http\Controllers\Admin;

use App\Enum\NotificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notifications\NotificationRejectRequest;
use App\Http\Requests\Admin\Notifications\NotificationStoreRequest;
use App\Http\Requests\Admin\Notifications\NotificationUpdateRequest;
use App\Http\Resources\Admin\Notifications\NotificationListResource;
use App\Http\Resources\Admin\Notifications\NotificationResource;
use App\Models\Notification;
use App\Services\LangService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Notification::query()
            ->with(['translatable', 'sender'])
            ->orderByDesc('id');

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return NotificationListResource::collection($query->paginate(20));
    }

    public function pending(): AnonymousResourceCollection
    {
        $notifications = Notification::query()
            ->with(['translatable', 'sender'])
            ->where('status', NotificationStatusEnum::PENDING_APPROVAL)
            ->orderByDesc('id')
            ->paginate(20);

        return NotificationListResource::collection($notifications);
    }

    public function show(Notification $notification): NotificationResource
    {
        $notification->load(['translatable', 'sender', 'approvedBy']);

        return NotificationResource::make($notification);
    }

    public function store(NotificationStoreRequest $request): JsonResponse
    {
        $notification = NotificationService::instance()
            ->createManual($request->validated(), auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification created.')
                ->getLang('notification_created'),
            'data' => ['uuid' => $notification->uuid],
        ]);
    }

    public function update(NotificationUpdateRequest $request, Notification $notification): JsonResponse
    {
        if (!$notification->isEditable()) {
            abort(422, LangService::instance()
                ->setDefault('This notification cannot be edited.')
                ->getLang('notification_not_editable'));
        }

        NotificationService::instance()->updateNotification($notification, $request->validated());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification updated.')
                ->getLang('notification_updated'),
        ]);
    }

    public function send(Notification $notification): JsonResponse
    {
        NotificationService::instance()->send($notification);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification is being sent.')
                ->getLang('notification_sending'),
        ]);
    }

    public function schedule(Request $request, Notification $notification): JsonResponse
    {
        $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        if (!$notification->isEditable() && $notification->status !== NotificationStatusEnum::APPROVED) {
            abort(422, LangService::instance()
                ->setDefault('This notification cannot be scheduled.')
                ->getLang('notification_not_schedulable'));
        }

        $notification->update(['scheduled_at' => $request->scheduled_at]);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification scheduled.')
                ->getLang('notification_scheduled'),
        ]);
    }

    public function resend(Notification $notification): JsonResponse
    {
        $clone = NotificationService::instance()->resend($notification, auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification is being resent.')
                ->getLang('notification_resending'),
            'data' => ['uuid' => $clone->uuid],
        ]);
    }

    public function approve(Notification $notification): JsonResponse
    {
        NotificationService::instance()->approve($notification, auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification approved and is being sent.')
                ->getLang('notification_approved'),
        ]);
    }

    public function reject(NotificationRejectRequest $request, Notification $notification): JsonResponse
    {
        NotificationService::instance()->reject(
            $notification,
            auth()->user(),
            $request->rejection_reason
        );

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification rejected.')
                ->getLang('notification_rejected'),
        ]);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        if (!$notification->isEditable()) {
            abort(422, LangService::instance()
                ->setDefault('Only draft or pending notifications can be deleted.')
                ->getLang('notification_not_deletable'));
        }

        $notification->delete();

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification deleted.')
                ->getLang('notification_deleted'),
        ]);
    }

    public function targetingPreview(Request $request): JsonResponse
    {
        $request->validate([
            'target_type' => ['required', 'string'],
            'target_filters' => ['nullable', 'array'],
        ]);

        $count = NotificationService::instance()->previewTargetCount(
            $request->target_type,
            $request->target_filters ?? []
        );

        return response()->json(['count' => $count]);
    }
}
