<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateClientAssociationCodeRequest;
use App\Http\Requests\ReviewClientAssociationRequestDecisionRequest;
use App\Models\ClientAssociationCode;
use App\Models\ClientAssociationRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ClientAssociationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAssociationController extends Controller
{
    public function codes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = ClientAssociationCode::query()
            ->with(['organization', 'creator'])
            ->latest('created_at');

        if ($user->isPartnerAdmin()) {
            $query->where('organization_id', $user->organization_id);
        }

        return response()->json([
            'data' => $query
                ->limit(100)
                ->get()
                ->map(fn (ClientAssociationCode $code) => $this->codeResource($code))
                ->values(),
        ]);
    }

    public function storeCode(
        GenerateClientAssociationCodeRequest $request,
        ClientAssociationService $clientAssociationService,
        AuditLogService $auditLogService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isPartnerAdmin(), 403, 'Generation reservee aux admins partenaires.');

        $organization = $user->organization;
        abort_unless($organization, 422, 'Aucune organisation rattachee a cet administrateur.');

        $code = $clientAssociationService->generateCode(
            $organization,
            $user,
            $request->validated('label'),
            $request->validated('max_uses'),
        );

        $auditLogService->record(
            'admin.association_code.created',
            $user,
            [
                'organization_id' => $organization->id,
                'code_id' => $code->id,
            ],
            $code,
            request: $request,
        );

        return response()->json([
            'message' => 'Code client genere.',
            'data' => $this->codeResource($code->load(['organization', 'creator'])),
        ], 201);
    }

    public function requests(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', ClientAssociationRequest::class);

        $query = ClientAssociationRequest::query()
            ->with(['organization', 'client', 'reviewer'])
            ->latest('created_at');

        if ($user->isPartnerAdmin()) {
            $query->where('organization_id', $user->organization_id);
        }

        return response()->json([
            'data' => $query
                ->limit(100)
                ->get()
                ->map(fn (ClientAssociationRequest $associationRequest) => $this->requestResource($associationRequest))
                ->values(),
        ]);
    }

    public function review(
        ReviewClientAssociationRequestDecisionRequest $request,
        ClientAssociationRequest $associationRequest,
        ClientAssociationService $clientAssociationService,
        AuditLogService $auditLogService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('review', $associationRequest);

        $updated = $clientAssociationService->reviewAssociationRequest(
            $user,
            $associationRequest,
            $request->validated('decision'),
            $request->validated('admin_response'),
        );

        $auditLogService->record(
            'admin.association_request.reviewed',
            $user,
            [
                'organization_id' => $updated->organization_id,
                'decision' => $updated->status,
                'client_user_id' => $updated->user_id,
            ],
            $updated,
            request: $request,
        );

        return response()->json([
            'message' => $updated->status === ClientAssociationRequest::STATUS_ACCEPTED
                ? 'Demande acceptee.'
                : 'Demande refusee.',
            'data' => $this->requestResource($updated),
        ]);
    }

    private function codeResource(ClientAssociationCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'label' => $code->label,
            'max_uses' => $code->max_uses,
            'use_count' => $code->use_count,
            'is_active' => (bool) $code->is_active,
            'last_used_at' => optional($code->last_used_at)->toIso8601String(),
            'expires_at' => optional($code->expires_at)->toIso8601String(),
            'organization' => $code->organization ? [
                'id' => $code->organization->id,
                'name' => $code->organization->name,
            ] : null,
            'creator' => $code->creator ? [
                'id' => $code->creator->id,
                'name' => $code->creator->name,
                'email' => $code->creator->email,
            ] : null,
            'created_at' => optional($code->created_at)->toIso8601String(),
        ];
    }

    private function requestResource(ClientAssociationRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'client_message' => $request->client_message,
            'admin_response' => $request->admin_response,
            'created_at' => optional($request->created_at)->toIso8601String(),
            'reviewed_at' => optional($request->reviewed_at)->toIso8601String(),
            'organization' => $request->organization ? [
                'id' => $request->organization->id,
                'name' => $request->organization->name,
                'location' => $request->organization->location,
            ] : null,
            'client' => $request->client ? [
                'id' => $request->client->id,
                'name' => $request->client->name,
                'email' => $request->client->email,
                'phone' => $request->client->phone,
            ] : null,
            'reviewer' => $request->reviewer ? [
                'id' => $request->reviewer->id,
                'name' => $request->reviewer->name,
            ] : null,
        ];
    }
}
