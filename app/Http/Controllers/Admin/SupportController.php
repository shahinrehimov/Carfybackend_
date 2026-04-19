<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\SupportMessageStoreRequest;
use App\Http\Requests\Admin\Support\SupportTicketAssignRequest;
use App\Http\Requests\Admin\Support\SupportTicketSearchRequest;
use App\Http\Requests\Admin\Support\SupportTicketStoreRequest;
use App\Http\Resources\Admin\Support\SupportMessageResource;
use App\Http\Resources\Admin\Support\SupportTicketResource;
use App\Http\Resources\GeneralResource;
use App\Models\SupportTicket;
use App\Repositories\SupportTicketRepository;
use App\Services\LangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SupportController extends Controller
{
    public function __construct(private readonly SupportTicketRepository $repo)
    {
    }

    public function index(SupportTicketSearchRequest $request): AnonymousResourceCollection
    {
        $tickets = $this->repo->loadForAdmin($request->validated());

        return SupportTicketResource::collection($tickets);
    }

    public function show(SupportTicket $supportTicket): SupportTicketResource
    {
        $ticket = $this->repo->showForAdmin($supportTicket);

        return SupportTicketResource::make($ticket);
    }

    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $admin = auth('admin')->user();
        $ticket = $this->repo->storeForAdmin($request, $admin);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Ticket created')->getLang('support_ticket_created'),
            'data'    => SupportTicketResource::make($ticket),
        ]), Response::HTTP_CREATED);
    }

    public function reply(SupportMessageStoreRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $admin = auth('admin')->user();
        $message = $this->repo->replyForAdmin($request, $supportTicket, $admin);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Message sent')->getLang('support_message_sent'),
            'data'    => SupportMessageResource::make($message),
        ]));
    }

    public function assign(SupportTicketAssignRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $ticket = $this->repo->assign($supportTicket, $request->validated('admin_id'));

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Ticket assigned')->getLang('support_ticket_assigned'),
            'data'    => SupportTicketResource::make($ticket),
        ]));
    }

    public function close(SupportTicket $supportTicket): JsonResponse
    {
        $this->repo->close($supportTicket);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Ticket closed')->getLang('support_ticket_closed_success'),
        ]));
    }

    public function getFilters(): JsonResponse
    {
        return response()->json(GeneralResource::make($this->repo->getFilters()));
    }
}
