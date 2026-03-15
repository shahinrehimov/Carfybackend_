<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Resources\App\Sessions\SessionResource;
use App\Http\Resources\GeneralResource;
use App\Repositories\SessionRepository;
use App\Services\LangService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    public function __construct(private readonly SessionRepository $repo)
    {
    }

    public function index(): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $data = $this->repo->getSessions($user);

        return response()->json(GeneralResource::make([
            'current_session' => $data['current_session']
                ? SessionResource::make($data['current_session'])
                : null,
            'other_sessions' => SessionResource::collection($data['other_sessions']),
        ]));
    }

    public function logoutSession(int $tokenId): JsonResponse
    {
        $user = UserService::instance()->getUser();
        $this->repo->deleteSession($user, $tokenId);

        return response()->json(GeneralResource::make([
            'message' => LangService::instance()
                ->setDefault('Session terminated successfully.')
                ->getLang('session_terminated_successfully'),
        ]));
    }
}
