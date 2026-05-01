<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripePaymentService $stripe
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->stripe->handleWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature', '')
            );

            return response('OK', 200);
        } catch (\InvalidArgumentException $e) {
            return response($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            return response('Erreur webhook.', 500);
        }
    }
}
