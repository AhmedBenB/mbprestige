<?php

namespace Tests\Feature\Support;

use App\Enums\SupportTicketPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use App\Http\Controllers\App\SupportTicketController;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesListingFixtures;
use Tests\TestCase;

class SupportTicketFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesListingFixtures;

    public function test_client_can_create_support_ticket(): void
    {
        $client = $this->createClientUser();
        $listing = $this->createListing();

        $response = $this->actingAs($client)->post('/app/support', [
            'subject' => 'Besoin de details livraison',
            'message' => 'Bonjour, je veux verifier la livraison vers Lyon.',
            'priority' => 'high',
            'listing_slug' => $listing->slug,
        ]);

        $ticket = SupportTicket::query()->first();

        $response->assertRedirect("/app/support/{$ticket->id}");
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'user_id' => $client->id,
            'listing_id' => $listing->id,
            'status' => SupportTicketStatusEnum::PendingAdmin->value,
            'priority' => SupportTicketPriorityEnum::High->value,
        ]);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'author_type' => 'client',
            'is_internal' => 0,
        ]);
    }

    public function test_admin_can_reply_and_internal_note_is_hidden_from_client(): void
    {
        $client = $this->createClientUser();
        $admin = $this->createClientUser(null, [
            'role' => 'admin',
            'email' => 'admin-support@test.local',
        ]);

        $ticket = $this->createTicketForClient($client);

        $this->actingAs($admin)->post("/admin/support-tickets/{$ticket->id}/reply", [
            'message' => 'Note interne admin: client a rappeler demain.',
            'is_internal' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'author_type' => 'admin',
            'is_internal' => 1,
            'message' => 'Note interne admin: client a rappeler demain.',
        ]);

        $request = Request::create("/app/support/{$ticket->id}", 'GET');
        $request->setUserResolver(fn () => $client);
        $view = app(SupportTicketController::class)->show($request, $ticket);
        /** @var \App\Models\SupportTicket $ticketForClient */
        $ticketForClient = $view->getData()['ticket'];
        $visibleMessages = $ticketForClient->messages->pluck('message')->all();

        $this->assertNotContains('Note interne admin: client a rappeler demain.', $visibleMessages);
    }

    public function test_admin_can_update_ticket_status(): void
    {
        $client = $this->createClientUser();
        $admin = $this->createClientUser(null, [
            'role' => 'admin',
            'email' => 'admin-status@test.local',
        ]);

        $ticket = $this->createTicketForClient($client);

        $this->actingAs($admin)->post("/admin/support-tickets/{$ticket->id}/status", [
            'status' => 'resolved',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatusEnum::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_client_can_reply_again_after_admin_response(): void
    {
        $client = $this->createClientUser();
        $admin = $this->createClientUser(null, [
            'role' => 'admin',
            'email' => 'admin-reply@test.local',
        ]);

        $ticket = $this->createTicketForClient($client);

        $this->actingAs($admin)->post("/admin/support-tickets/{$ticket->id}/reply", [
            'message' => 'Bonjour, pouvez-vous confirmer le VIN ?',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatusEnum::PendingCustomer, $ticket->status);

        $this->actingAs($client)->post("/app/support/{$ticket->id}/reply", [
            'message' => 'Oui, je confirme le VIN et j attends votre retour.',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatusEnum::PendingAdmin, $ticket->status);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'author_type' => 'client',
            'is_internal' => 0,
            'message' => 'Oui, je confirme le VIN et j attends votre retour.',
        ]);
    }

    private function createTicketForClient($client): SupportTicket
    {
        $this->actingAs($client)->post('/app/support', [
            'subject' => 'Ticket test',
            'message' => 'Message initial pour le support client MBPRESTIGE.',
            'priority' => 'normal',
        ])->assertRedirect();

        return SupportTicket::query()->latest('id')->firstOrFail();
    }
}
