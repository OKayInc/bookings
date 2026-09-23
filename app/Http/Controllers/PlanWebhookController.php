<?php

namespace App\Http\Controllers;

use App\Domain\Plans\PlanBillingException;
use App\Domain\Plans\PlanBillingService;
use App\Domain\Plans\PlanStripeGateway;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PlanStripeGateway $stripe,
        PlanBillingService $billing,
    ): Response {
        try {
            $event = $stripe->verifyWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
            $billing->processStripeEvent($event);
        } catch (PlanBillingException $exception) {
            return response($exception->getMessage(), 400);
        }

        return response()->noContent();
    }
}
