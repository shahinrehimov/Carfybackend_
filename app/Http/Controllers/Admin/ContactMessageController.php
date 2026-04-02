<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessages\ContactMessageIndexRequest;
use App\Http\Resources\Admin\ContactMessages\ContactMessagesResource;
use App\Http\Resources\GeneralResource;
use App\Models\ContactMessage;
use App\Repositories\ContactMessageRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactMessageController extends Controller
{
    private ContactMessageRepository $repo;

    public function __construct(ContactMessageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(ContactMessageIndexRequest $request): AnonymousResourceCollection
    {
        return ContactMessagesResource::collection($this->repo->load($request->validated()));
    }

    public function show(ContactMessage $contactMessage): ContactMessagesResource
    {
        return ContactMessagesResource::make($contactMessage);
    }

    public function markAsRead(ContactMessage $contactMessage): JsonResponse
    {
        $this->repo->markAsRead($contactMessage);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Marked as read!')
                ->getLang('admin_marked_as_read'),
        ]));
    }

    public function markAsUnread(ContactMessage $contactMessage): JsonResponse
    {
        $this->repo->markAsUnread($contactMessage);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Marked as unread!')
                ->getLang('admin_marked_as_unread'),
        ]));
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $this->repo->destroy($contactMessage);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Deleted successfully!')
                ->getLang('admin_deleted_successfully'),
        ]));
    }
}
