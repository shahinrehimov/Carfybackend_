<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notifications\NotificationSettingUpdateRequest;
use App\Http\Resources\Admin\Notifications\NotificationSettingResource;
use App\Models\NotificationTypeSetting;
use App\Services\LangService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationSettingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $settings = NotificationTypeSetting::query()->orderBy('id')->get();

        return NotificationSettingResource::collection($settings);
    }

    public function update(NotificationSettingUpdateRequest $request): JsonResponse
    {
        NotificationService::instance()->updateSettings($request->settings);

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('Notification settings updated.')
                ->getLang('notification_settings_updated'),
        ]);
    }

    public function sync(): JsonResponse
    {
        $added = NotificationService::instance()->syncEventTypes();

        return response()->json([
            'message' => LangService::instance()
                ->setDefault('@count new event type(s) synced.')
                ->getLang('notification_settings_synced', ['@count' => $added]),
            'added' => $added,
        ]);
    }
}
