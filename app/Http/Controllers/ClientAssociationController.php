<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientAssociationRequest;
use Illuminate\Http\JsonResponse;

class ClientAssociationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function store(StoreClientAssociationRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Le rattachement manuel a un garage n est plus disponible. Les demandes client sont maintenant centralisees par MBPRESTIGE.',
        ], 410);
    }
}

