<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\Notifications\NotificationResource;
use App\Models\Notification;
use App\Services\LangService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $notifications = NotificationService::instance()
            ->getUserNotifications(auth()->user());

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(): JsonResponse
    {
        $count = NotificationService::instance()->getUnreadCount(auth()->user());

        return response()->json(['count' => $count]);
    }

    public function read(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        $belongs = $notification->recipients()
            ->where('user_id', $user->id)
            ->whereNull('in_app_deleted_at')
            ->exists();

        if (!$belongs) {
            abort(403);
        }

        NotificationService::instance()->markAsRead($notification, $user);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Marked as read.')
                ->getLang('notification_marked_as_read'),
        ]);
    }

    public function readAll(): JsonResponse
    {
        NotificationService::instance()->markAllAsRead(auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('All notifications marked as read.')
                ->getLang('notification_all_marked_as_read'),
        ]);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        $belongs = $notification->recipients()
            ->where('user_id', $user->id)
            ->whereNull('in_app_deleted_at')
            ->exists();

        if (!$belongs) {
            abort(403);
        }

        NotificationService::instance()->deleteForUser($notification, $user);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification deleted.')
                ->getLang('notification_deleted'),
        ]);
    }

    public function destroyAll(): JsonResponse
    {
        NotificationService::instance()->deleteAllForUser(auth()->user());

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('All notifications deleted.')
                ->getLang('notifications_all_deleted'),
        ]);
    }
}
