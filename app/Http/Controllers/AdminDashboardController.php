<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminSettingsRequest;
use App\Models\CustomerSearch;
use App\Models\SearchResult;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\AdminSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminDashboardController extends Controller
{
    public function index(Request $request, AdminSettingsService $settingsService): JsonResponse
    {
        if (
            !Schema::hasTable('customer_searches') ||
            !Schema::hasTable('search_runs') ||
            !Schema::hasTable('search_results')
        ) {
            return response()->json([
                'data' => [
                    'kpis' => [
                        'active_searches' => 0,
                        'today_candidates' => 0,
                        'high_priority' => 0,
                        'alerts_sent' => 0,
                        'auto_rejected' => 0,
                    ],
                    'high_priority_matches' => [],
                    'recent_notifications' => [],
                    'recent_runs' => [],
                ],
                'meta' => [
                    'phase2_ready' => false,
                    'message' => 'Les tables Phase 2 ne sont pas encore migrees.',
                ],
            ]);
        }

        $settings = $settingsService->all();
        $priorityScore = (int) ($settings['matching']['high_priority_score'] ?? 85);
        /** @var User $user */
        $user = $request->user();

        $recentResults = $this->resultQueryForUser($user)
            ->latest('created_at')
            ->limit(10)
            ->get();

        $priorityResults = $this->resultQueryForUser($user)
            ->where('match_score', '>=', $priorityScore)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'kpis' => [
                    'active_searches' => $this->searchQueryForUser($user)
                        ->where('status', CustomerSearch::STATUS_ACTIVE)
                        ->count(),
                    'today_candidates' => $this->resultQueryForUser($user)
                        ->whereDate('created_at', today())
                        ->count(),
                    'high_priority' => $this->resultQueryForUser($user)
                        ->where('match_score', '>=', $priorityScore)
                        ->count(),
                    'alerts_sent' => $this->resultQueryForUser($user)
                        ->whereNotNull('shared_with_client_at')
                        ->count(),
                    'auto_rejected' => $this->resultQueryForUser($user)
                        ->where('match_status', SearchResult::STATUS_REJECTED)
                        ->count(),
                ],
                'high_priority_matches' => $priorityResults->map(fn (SearchResult $result) => [
                    'id' => $result->id,
                    'vehicle_label' => $result->title ?: trim(($result->make ?? '') . ' ' . ($result->model ?? '')),
                    'year' => $result->year,
                    'mileage' => $result->mileage,
                    'fuel' => $result->fuel,
                    'transmission' => null,
                    'country_origin' => null,
                    'estimated_margin' => null,
                    'score' => $result->match_score,
                    'decision' => $result->match_status,
                ])->values(),
                'recent_notifications' => $recentResults
                    ->whereNotNull('shared_with_client_at')
                    ->take(10)
                    ->map(fn (SearchResult $result) => [
                        'id' => $result->id,
                        'channel' => $result->shared_channel ?: 'manual',
                        'recipient_type' => 'client',
                        'label' => $result->title ?: trim(($result->make ?? '') . ' ' . ($result->model ?? '')),
                        'status' => 'sent',
                        'sent_at' => optional($result->shared_with_client_at)->toIso8601String(),
                    ])->values(),
                'recent_runs' => $this->runQueryForUser($user)
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (SearchRun $run) => [
                        'id' => $run->id,
                        'status' => $run->status,
                        'searches_processed' => 1,
                        'listings_processed' => $run->result_count,
                        'priority_count' => $this->resultQueryForUser($user)
                            ->where('search_run_id', $run->id)
                            ->where('match_score', '>=', $priorityScore)
                            ->count(),
                        'rejected_count' => $this->resultQueryForUser($user)
                            ->where('search_run_id', $run->id)
                            ->where('match_status', SearchResult::STATUS_REJECTED)
                            ->count(),
                        'manual_review_count' => $this->resultQueryForUser($user)
                            ->where('search_run_id', $run->id)
                            ->where('match_status', SearchResult::STATUS_ON_HOLD)
                            ->count(),
                        'ran_at' => optional($run->created_at)->toIso8601String(),
                    ])->values(),
            ],
            'meta' => [
                'phase2_ready' => true,
            ],
        ]);
    }

    public function settings(AdminSettingsService $settingsService): JsonResponse
    {
        return response()->json([
            'data' => $settingsService->all(),
        ]);
    }

    public function updateSettings(UpdateAdminSettingsRequest $request, AdminSettingsService $settingsService): JsonResponse
    {
        return response()->json([
            'message' => 'Parametres enregistres.',
            'data' => $settingsService->save($request->validated()),
        ]);
    }

    private function searchQueryForUser(User $user)
    {
        $query = CustomerSearch::query();

        if ($user->isPartnerAdmin()) {
            $query->where('organization_id', $user->organization_id);
        } else {
            $query->whereNull('parent_search_id');
        }

        return $query;
    }

    private function resultQueryForUser(User $user)
    {
        $query = SearchResult::query();

        if ($user->isPartnerAdmin()) {
            $query->whereHas('search', fn ($builder) => $builder->where('organization_id', $user->organization_id));
        }

        return $query;
    }

    private function runQueryForUser(User $user)
    {
        $query = SearchRun::query();

        if ($user->isPartnerAdmin()) {
            $query->whereHas('search', fn ($builder) => $builder->where('organization_id', $user->organization_id));
        }

        return $query;
    }
}
