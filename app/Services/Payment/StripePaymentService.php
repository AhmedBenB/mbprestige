<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Purchases\PurchaseReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentService
{
    private bool $configured = false;

    public function __construct(
        private readonly PurchaseReservationService $purchaseService
    ) {}

    public function createDepositPaymentIntent(
        Listing $listing,
        User $user,
        Purchase $purchase,
        float $depositAmount
    ): array {
        $this->ensureStripeConfigured();

        $amountCents = (int) round($depositAmount * 100);

        $intent = PaymentIntent::create([
            'amount' => $amountCents,
            'currency' => strtolower($listing->currency),
            'description' => "MBPRESTIGE - acompte {$listing->title}",
            'metadata' => [
                'payment_type' => PaymentTypeEnum::Deposit->value,
                'listing_id' => (string) $listing->id,
                'purchase_id' => (string) $purchase->id,
                'user_id' => (string) $user->id,
                'organization_id' => (string) ($user->organization_id ?? ''),
            ],
            'receipt_email' => $user->email,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'organization_id' => $user->organization_id,
            'purchase_id' => $purchase->id,
            'type' => PaymentTypeEnum::Deposit,
            'provider' => 'stripe',
            'provider_payment_intent_id' => $intent->id,
            'amount' => $depositAmount,
            'currency' => strtoupper($listing->currency),
            'status' => PaymentStatusEnum::Pending,
            'metadata' => [
                'source' => 'intent',
            ],
        ]);

        $this->purchaseService->markDepositPending($purchase);

        Log::info('Stripe intent created', [
            'payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
            'listing_id' => $listing->id,
            'intent_id' => $intent->id,
        ]);

        return [
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'amount' => $depositAmount,
            'currency' => $listing->currency,
            'payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
        ];
    }

    public function createDepositCheckoutSession(
        Listing $listing,
        User $user,
        Purchase $purchase,
        float $depositAmount
    ): \Stripe\Checkout\Session {
        $this->ensureStripeConfigured();

        $amountCents = (int) round($depositAmount * 100);

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card', 'sepa_debit'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($listing->currency),
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => "Acompte - {$listing->title}",
                        'description' => "Reservation #{$purchase->id} - MBPRESTIGE",
                        'images' => $listing->coverImage ? [$listing->coverImage->url()] : [],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $user->email,
            'success_url' => route('app.payment.success', ['listing' => $listing]) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('app.payment.cancel', ['listing' => $listing]),
            'metadata' => [
                'payment_type' => PaymentTypeEnum::Deposit->value,
                'listing_id' => (string) $listing->id,
                'purchase_id' => (string) $purchase->id,
                'user_id' => (string) $user->id,
                'organization_id' => (string) ($user->organization_id ?? ''),
            ],
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'organization_id' => $user->organization_id,
            'purchase_id' => $purchase->id,
            'type' => PaymentTypeEnum::Deposit,
            'provider' => 'stripe',
            'provider_session_id' => $session->id,
            'amount' => $depositAmount,
            'currency' => strtoupper($listing->currency),
            'status' => PaymentStatusEnum::Pending,
            'metadata' => [
                'source' => 'checkout',
            ],
        ]);

        $this->purchaseService->markDepositPending($purchase);

        Log::info('Stripe checkout session created', [
            'payment_id' => $payment->id,
            'purchase_id' => $purchase->id,
            'listing_id' => $listing->id,
            'session_id' => $session->id,
        ]);

        return $session;
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        $this->ensureStripeConfigured();

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            throw new \InvalidArgumentException('Payload invalide.', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new \InvalidArgumentException('Signature invalide.', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        match ($event->type) {
            'payment_intent.succeeded' => $this->onPaymentIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->onPaymentIntentFailed($event->data->object),
            'checkout.session.completed' => $this->onCheckoutCompleted($event->data->object),
            default => null,
        };
    }

    private function onPaymentIntentSucceeded(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent) {
            $payment = Payment::query()
                ->where('provider', 'stripe')
                ->where('provider_payment_intent_id', $intent->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('No payment row for payment_intent.succeeded', ['intent_id' => $intent->id]);
                return;
            }

            $this->markPaymentPaid($payment, [
                'intent_status' => $intent->status,
                'event' => 'payment_intent.succeeded',
            ]);
        });
    }

    private function onPaymentIntentFailed(PaymentIntent $intent): void
    {
        $payment = Payment::query()
            ->where('provider', 'stripe')
            ->where('provider_payment_intent_id', $intent->id)
            ->first();

        if (! $payment) {
            Log::warning('No payment row for payment_intent.payment_failed', ['intent_id' => $intent->id]);
            return;
        }

        $payment->update([
            'status' => PaymentStatusEnum::Failed,
            'metadata' => array_merge($payment->metadata ?? [], [
                'event' => 'payment_intent.payment_failed',
            ]),
        ]);

        Log::warning('Stripe payment failed', [
            'payment_id' => $payment->id,
            'intent_id' => $intent->id,
        ]);
    }

    private function onCheckoutCompleted(\Stripe\Checkout\Session $session): void
    {
        DB::transaction(function () use ($session) {
            $payment = Payment::query()
                ->where('provider', 'stripe')
                ->where('provider_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $payment = $this->createFallbackCheckoutPayment($session);
                if (! $payment) {
                    return;
                }
            }

            $updates = [
                'provider_payment_intent_id' => $session->payment_intent ?: $payment->provider_payment_intent_id,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'event' => 'checkout.session.completed',
                    'payment_status' => $session->payment_status,
                ]),
            ];

            $payment->update($updates);
            $payment->refresh();

            if ($session->payment_status === 'paid') {
                $this->markPaymentPaid($payment, [
                    'session_id' => $session->id,
                    'event' => 'checkout.session.completed',
                ]);
            }
        });
    }

    private function createFallbackCheckoutPayment(\Stripe\Checkout\Session $session): ?Payment
    {
        $metadata = (array) ($session->metadata ?? []);
        $purchaseId = isset($metadata['purchase_id']) ? (int) $metadata['purchase_id'] : null;
        $listingId = isset($metadata['listing_id']) ? (int) $metadata['listing_id'] : null;
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;
        $organizationId = isset($metadata['organization_id']) && $metadata['organization_id'] !== ''
            ? (int) $metadata['organization_id']
            : null;

        if (! $purchaseId || ! $listingId || ! $userId) {
            Log::warning('Unable to create fallback payment row from checkout session metadata', [
                'session_id' => $session->id,
            ]);
            return null;
        }

        $amount = isset($session->amount_total) ? ((float) $session->amount_total / 100) : 0.0;
        $currency = strtoupper((string) ($session->currency ?? 'EUR'));

        return Payment::create([
            'user_id' => $userId,
            'listing_id' => $listingId,
            'organization_id' => $organizationId,
            'purchase_id' => $purchaseId,
            'type' => PaymentTypeEnum::Deposit,
            'provider' => 'stripe',
            'provider_session_id' => $session->id,
            'provider_payment_intent_id' => $session->payment_intent ?: null,
            'amount' => $amount,
            'currency' => $currency,
            'status' => PaymentStatusEnum::Pending,
            'metadata' => [
                'source' => 'fallback_checkout_webhook',
            ],
        ]);
    }

    private function markPaymentPaid(Payment $payment, array $extraMeta = []): void
    {
        if ($payment->status !== PaymentStatusEnum::Paid) {
            $payment->update([
                'status' => PaymentStatusEnum::Paid,
                'paid_at' => now(),
                'metadata' => array_merge($payment->metadata ?? [], $extraMeta),
            ]);
            $payment->refresh();
        }

        if ($payment->purchase_id) {
            $purchase = Purchase::find($payment->purchase_id);
            if ($purchase) {
                $this->purchaseService->markDepositPaid($purchase, $payment);
            }
        }

        Log::info('Stripe payment marked as paid', [
            'payment_id' => $payment->id,
            'purchase_id' => $payment->purchase_id,
            'listing_id' => $payment->listing_id,
        ]);
    }

    private function ensureStripeConfigured(): void
    {
        if ($this->configured) {
            return;
        }

        if (! class_exists(Stripe::class)) {
            throw new \RuntimeException(
                'Le SDK Stripe est manquant. Installez-le avec Composer et activez l’extension PHP curl.'
            );
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $this->configured = true;
    }
}
