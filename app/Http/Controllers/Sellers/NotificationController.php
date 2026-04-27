<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Notifications\NotificationStoreRequest;
use App\Http\Requests\Sellers\Notifications\NotificationUpdateRequest;
use App\Http\Resources\Admin\Notifications\NotificationListResource;
use App\Http\Resources\Admin\Notifications\NotificationResource;
use App\Models\Notification;
use App\Services\LangService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $seller = auth()->user();

        $notifications = Notification::query()
            ->with(['translatable', 'sender'])
            ->where('sender_type', $seller->getMorphClass())
            ->where('sender_id', $seller->id)
            ->orderByDesc('id')
            ->paginate(20);

        return NotificationListResource::collection($notifications);
    }

    public function show(Notification $notification): NotificationResource
    {
        $this->authorizeNotification($notification);

        $notification->load(['translatable', 'sender']);

        return NotificationResource::make($notification);
    }

    public function store(NotificationStoreRequest $request): JsonResponse
    {
        $notification = NotificationService::instance()->createManual($request->validated(), auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification submitted for approval.')
                ->getLang('notification_submitted_for_approval'),
            'data' => ['uuid' => $notification->uuid],
        ]);
    }

    public function update(NotificationUpdateRequest $request, Notification $notification): JsonResponse
    {
        $this->authorizeNotification($notification);

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

    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorizeNotification($notification);

        if (!$notification->isEditable()) {
            abort(422, LangService::instance()
                ->setDefault('Only pending or rejected notifications can be deleted.')
                ->getLang('notification_not_deletable'));
        }

        $notification->delete();

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification deleted.')
                ->getLang('notification_deleted'),
        ]);
    }

    public function resend(Notification $notification): JsonResponse
    {
        $this->authorizeNotification($notification);

        $clone = NotificationService::instance()->resend($notification, auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification submitted for approval.')
                ->getLang('notification_submitted_for_approval'),
            'data' => ['uuid' => $clone->uuid],
        ]);
    }

    public function targetingPreview(): JsonResponse
    {
        $seller = auth()->user();

        $count = NotificationService::instance()->previewTargetCount('bulk', [
            'company_id' => $seller->company_id,
        ]);

        return response()->json(['count' => $count]);
    }

    private function authorizeNotification(Notification $notification): void
    {
        $seller = auth()->user();

        if ($notification->sender_type !== $seller->getMorphClass() || $notification->sender_id !== $seller->id) {
            abort(403);
        }
    }
}
