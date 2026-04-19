<?php

namespace App\Http\Controllers\Sellers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sellers\Support\SupportMessageStoreRequest;
use App\Http\Requests\Sellers\Support\SupportTicketSearchRequest;
use App\Http\Requests\Sellers\Support\SupportTicketStoreRequest;
use App\Http\Resources\GeneralResource;
use App\Http\Resources\Sellers\Support\SupportMessageResource;
use App\Http\Resources\Sellers\Support\SupportTicketResource;
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
        $companyAdmin = auth('company_admin')->user();
        $tickets = $this->repo->loadForSeller($request->validated(), $companyAdmin->company_id);

        return SupportTicketResource::collection($tickets);
    }

    public function show(SupportTicket $supportTicket): SupportTicketResource
    {
        $this->authorizeTicket($supportTicket);

        $ticket = $this->repo->showForSeller($supportTicket);

        return SupportTicketResource::make($ticket);
    }

    public function store(SupportTicketStoreRequest $request): JsonResponse
    {
        $companyAdmin = auth('company_admin')->user();
        $ticket = $this->repo->storeForSeller($request, $companyAdmin->company_id, $companyAdmin);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Ticket created')->getLang('support_ticket_created'),
            'data'    => SupportTicketResource::make($ticket),
        ]), Response::HTTP_CREATED);
    }

    public function reply(SupportMessageStoreRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeTicket($supportTicket);

        if ($supportTicket->isClosed()) {
            return response()->json(GeneralResource::make([
                'message' => LangService::instance()->setDefault('Ticket is closed')->getLang('support_ticket_closed'),
            ]), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $companyAdmin = auth('company_admin')->user();
        $message = $this->repo->replyForSeller($request, $supportTicket, $companyAdmin);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Message sent')->getLang('support_message_sent'),
            'data'    => SupportMessageResource::make($message),
        ]));
    }

    public function close(SupportTicket $supportTicket): JsonResponse
    {
        $this->authorizeTicket($supportTicket);

        $this->repo->close($supportTicket);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()->setDefault('Ticket closed')->getLang('support_ticket_closed_success'),
        ]));
    }

    private function authorizeTicket(SupportTicket $ticket): void
    {
        $companyAdmin = auth('company_admin')->user();
        abort_if($ticket->company_id !== $companyAdmin->company_id, Response::HTTP_FORBIDDEN);
    }
}
