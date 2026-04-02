<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\Contact\ContactStoreRequest;
use App\Http\Resources\GeneralResource;
use App\Repositories\ContactMessageRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    private ContactMessageRepository $repo;

    public function __construct(ContactMessageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function store(ContactStoreRequest $request): JsonResponse
    {
        $this->repo->store($request);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Your message has been sent successfully!')
                ->getLang('contact_message_sent_successfully'),
        ]));
    }
}
