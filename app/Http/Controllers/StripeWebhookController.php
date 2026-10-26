<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eventi Stripe per gli abbonamenti SinistroPro. Sullo stesso account passano anche CRM,
 * FusionWA e Moduli: si considerano solo i checkout con metadata app = sinistropro e gli
 * abbonamenti già collegati a un tenant (o marcati sinistropro).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBilling $billing): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response('Firma non valida', 400);
        }

        $object = $event->data->object;
        $app = config('billing.stripe_app');

        switch ($event->type) {
            case 'checkout.session.completed':
                if (($object->metadata->app ?? null) !== $app || $object->mode !== 'subscription') {
                    break;
                }

                if ($tenant = Tenant::find((int) ($object->metadata->tenant_id ?? 0))) {
                    $billing->applyCheckout($tenant, $object);
                }
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $tenant = Tenant::where('stripe_subscription_id', $object->id)->first()
                    ?? (($object->metadata->app ?? null) === $app ? Tenant::find((int) ($object->metadata->tenant_id ?? 0)) : null);

                // Un tenant con un abbonamento nuovo non deve farsi sovrascrivere da eventi di uno vecchio.
                if ($tenant && (! $tenant->stripe_subscription_id || $tenant->stripe_subscription_id === $object->id)) {
                    $billing->syncSubscription($tenant, $object);
                    Log::info('Stripe: abbonamento aggiornato', ['tenant' => $tenant->id, 'status' => $object->status]);
                }
                break;
        }

        return response('ok');
    }
}
