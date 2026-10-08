<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogs\AuditLogIndexRequest;
use App\Http\Resources\Admin\AuditLogs\AuditLogResource;
use App\Http\Resources\Admin\AuditLogs\AuditLogsResource;
use App\Models\RouteLog;
use App\Repositories\AuditLogRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    private AuditLogRepository $repo;

    public function __construct(AuditLogRepository $repo)
    {
        $this->repo = $repo;
    }

    public function index(AuditLogIndexRequest $request): AnonymousResourceCollection
    {
        return AuditLogsResource::collection($this->repo->load($request->validated()));
    }

    public function show(RouteLog $routeLog): AuditLogResource
    {
        return AuditLogResource::make($routeLog->load(['creatable', 'company']));
    }
}
