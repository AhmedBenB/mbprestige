<?php

namespace App\Http\Controllers\App;

use App\Enums\SupportTicketPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with(['listing'])
            ->latest('last_message_at')
            ->latest('id')
            ->paginate(20);

        return view('app.support.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('app.support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'listing_slug' => ['nullable', 'string', 'exists:listings,slug'],
        ]);

        $listing = null;
        if (! empty($data['listing_slug'])) {
            $listing = Listing::query()->where('slug', $data['listing_slug'])->first();
        }

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'organization_id' => $request->user()->organization_id,
            'listing_id' => $listing?->id,
            'subject' => $data['subject'],
            'status' => SupportTicketStatusEnum::PendingAdmin,
            'priority' => $data['priority'] ?? SupportTicketPriorityEnum::Normal,
            'last_message_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'author_type' => 'client',
            'message' => $data['message'],
            'is_internal' => false,
        ]);

        return redirect()->route('app.support.show', $ticket)
            ->with('success', 'Votre demande a été envoyée au support MBPRESTIGE.');
    }

    public function show(Request $request, SupportTicket $supportTicket): View
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);

        $supportTicket->load([
            'listing',
            'messages' => fn ($q) => $q->where('is_internal', false)->with('user')->oldest('id'),
        ]);

        return view('app.support.show', ['ticket' => $supportTicket]);
    }

    public function reply(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_unless($supportTicket->user_id === $request->user()->id, 403);
        abort_if(in_array($supportTicket->status, [SupportTicketStatusEnum::Resolved, SupportTicketStatusEnum::Closed], true), 422, 'Ticket déjà clôturé.');

        $data = $request->validate([
            'message' => ['required', 'string', 'min:3'],
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'author_type' => 'client',
            'message' => $data['message'],
            'is_internal' => false,
        ]);

        $supportTicket->update([
            'status' => SupportTicketStatusEnum::PendingAdmin,
            'last_message_at' => now(),
        ]);

        return back()->with('success', 'Votre réponse a été envoyée.');
    }
}
