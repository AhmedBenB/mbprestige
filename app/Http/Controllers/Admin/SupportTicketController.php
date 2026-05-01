<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['user', 'listing', 'handler'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(function ($query) use ($term) {
                    $query->where('id', $term)
                        ->orWhere('subject', 'like', "%{$term}%")
                        ->orWhereHas('user', fn ($sq) => $sq->where('email', 'like', "%{$term}%"));
                });
            })
            ->latest('last_message_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.support.index', compact('tickets'));
    }

    public function show(SupportTicket $supportTicket): View
    {
        $supportTicket->load(['user.organization', 'listing', 'handler', 'messages.user']);

        return view('admin.support.show', ['ticket' => $supportTicket]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:3'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'author_type' => 'admin',
            'message' => $data['message'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        $supportTicket->update([
            'status' => (bool) ($data['is_internal'] ?? false)
                ? $supportTicket->status
                : SupportTicketStatusEnum::PendingCustomer,
            'handled_by' => $supportTicket->handled_by ?? $request->user()->id,
            'handled_at' => $supportTicket->handled_at ?? now(),
            'last_message_at' => now(),
        ]);

        Log::info('Admin replied support ticket', [
            'ticket_id' => $supportTicket->id,
            'admin_id' => $request->user()->id,
            'internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        return back()->with('success', 'Réponse envoyée.');
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,pending_admin,pending_customer,resolved,closed'],
        ]);

        $status = SupportTicketStatusEnum::from($data['status']);

        $supportTicket->update([
            'status' => $status,
            'handled_by' => $supportTicket->handled_by ?? $request->user()->id,
            'handled_at' => $supportTicket->handled_at ?? now(),
            'resolved_at' => in_array($status, [SupportTicketStatusEnum::Resolved, SupportTicketStatusEnum::Closed], true)
                ? ($supportTicket->resolved_at ?? now())
                : null,
        ]);

        Log::info('Admin updated support ticket status', [
            'ticket_id' => $supportTicket->id,
            'admin_id' => $request->user()->id,
            'status' => $status->value,
        ]);

        return back()->with('success', 'Statut du ticket mis à jour.');
    }
}
