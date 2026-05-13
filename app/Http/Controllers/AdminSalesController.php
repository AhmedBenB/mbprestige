<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminSalesController extends Controller
{
    public function purchases(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = DB::table('purchases')
            ->leftJoin('users', 'users.id', '=', 'purchases.user_id')
            ->leftJoin('payments', 'payments.id', '=', 'purchases.payment_id')
            ->select([
                'purchases.*',
                'users.email as user_email',
                'users.name as user_name',
                'payments.status as payment_status',
                'payments.amount as payment_amount',
                'payments.currency as payment_currency',
            ])
            ->orderByDesc('purchases.id');

        if ($request->filled('status')) {
            $query->where('purchases.status', $request->string('status')->toString());
        }
        if ($request->filled('organization_id')) {
            $query->where('purchases.organization_id', (int) $request->organization_id);
        }
        if ($request->filled('user_id')) {
            $query->where('purchases.user_id', (int) $request->user_id);
        }

        $rows = $query->paginate($perPage);

        return response()->json($rows);
    }

    public function purchase(Request $request, int $id): JsonResponse
    {
        $purchase = DB::table('purchases')
            ->leftJoin('users', 'users.id', '=', 'purchases.user_id')
            ->select([
                'purchases.*',
                'users.email as user_email',
                'users.name as user_name',
            ])
            ->where('purchases.id', $id)
            ->first();

        if (!$purchase) {
            return response()->json(['message' => 'Achat introuvable.'], 404);
        }

        $payment = DB::table('payments')
            ->where('purchase_id', $id)
            ->orWhere('id', $purchase->payment_id)
            ->orderByDesc('id')
            ->first();

        if ($payment && isset($payment->metadata) && is_string($payment->metadata) && $payment->metadata !== '') {
            $decoded = json_decode($payment->metadata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payment->metadata = $decoded;
            }
        }

        return response()->json([
            'purchase' => $purchase,
            'payment' => $payment,
        ]);
    }

    public function updatePurchase(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:reserved,deposit_pending,deposit_paid,completed,cancelled,expired',
            'reserved_at' => 'sometimes|nullable|date',
            'deposit_paid_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date',
            'payment_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation invalide.', 'errors' => $validator->errors()], 422);
        }

        $exists = DB::table('purchases')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Achat introuvable.'], 404);
        }

        $data = $validator->validated();
        if ($data === []) {
            return response()->json(['message' => 'Aucune modification.'], 422);
        }

        $data['updated_at'] = now();

        DB::table('purchases')->where('id', $id)->update($data);

        $updated = DB::table('purchases')->where('id', $id)->first();

        return response()->json([
            'message' => 'Achat mis a jour.',
            'data' => $updated,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = DB::table('payments')
            ->leftJoin('users', 'users.id', '=', 'payments.user_id')
            ->select([
                'payments.*',
                'users.email as user_email',
                'users.name as user_name',
            ])
            ->orderByDesc('payments.id');

        if ($request->filled('status')) {
            $query->where('payments.status', $request->string('status')->toString());
        }
        if ($request->filled('organization_id')) {
            $query->where('payments.organization_id', (int) $request->organization_id);
        }
        if ($request->filled('type')) {
            $query->where('payments.type', $request->string('type')->toString());
        }

        $rows = $query->paginate($perPage);

        return response()->json($rows);
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $payment = DB::table('payments')->where('id', $id)->first();

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        if (isset($payment->metadata) && is_string($payment->metadata) && $payment->metadata !== '') {
            $decoded = json_decode($payment->metadata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payment->metadata = $decoded;
            }
        }

        return response()->json(['data' => $payment]);
    }

    public function updatePayment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:pending,authorized,paid,failed,cancelled,refunded',
            'type' => 'sometimes|string|max:50',
            'provider' => 'sometimes|nullable|string|max:50',
            'provider_session_id' => 'sometimes|nullable|string|max:255',
            'provider_payment_intent_id' => 'sometimes|nullable|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'paid_at' => 'sometimes|nullable|date',
            'metadata' => 'sometimes|nullable|array',
            'purchase_id' => 'sometimes|nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation invalide.', 'errors' => $validator->errors()], 422);
        }

        $exists = DB::table('payments')->where('id', $id)->exists();
        if (!$exists) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $data = $validator->validated();
        if ($data === []) {
            return response()->json(['message' => 'Aucune modification.'], 422);
        }

        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $data['metadata'] === null
                ? null
                : json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        $data['updated_at'] = now();

        DB::table('payments')->where('id', $id)->update($data);

        $updated = DB::table('payments')->where('id', $id)->first();

        if ($updated && isset($updated->metadata) && is_string($updated->metadata) && $updated->metadata !== '') {
            $decoded = json_decode($updated->metadata, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $updated->metadata = $decoded;
            }
        }

        return response()->json([
            'message' => 'Paiement mis a jour.',
            'data' => $updated,
        ]);
    }
}
