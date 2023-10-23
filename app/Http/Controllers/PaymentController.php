<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class PaymentController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public $stripe;

    public $stripeSecretKey;

    // This is your Stripe CLI webhook secret for testing your endpoint locally.
    public $endpoint_secret;

    public function __construct()
    {
        $this->stripeSecretKey = config('stripe.secret_key');

        $this->endpoint_secret = config('stripe.webhook_secret');

        $this->stripe = new \Stripe\StripeClient($this->stripeSecretKey);
    }

    /**
     * @param  Request  $request
    //  * @return void
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            // retrieve JSON from POST body
            // $jsonStr = file_get_contents('php://input');
            // $jsonObj = json_decode($jsonStr);

            // Create a PaymentIntent with amount and currency
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->calculateOrderAmount($request->items ?? []),
                'currency' => 'gbp',
                // In the latest version of the API, specifying the `automatic_payment_methods` parameter is optional because Stripe enables its functionality by default.
                // 'automatic_payment_methods' => [
                //     'enabled' => true,
                // ],
                'payment_method_types' => [
                    // 'bancontact',
                    'card',
                    'bacs_debit',
                    // 'eps',
                    // 'giropay',
                    // 'ideal',
                    // 'p24',
                    // 'sepa_debit',
                    // 'sofort',
                    'link',
                    'paypal',
                    // 'apple_pay',
                    // 'google_pay',
                ]
            ]);

            $output = [
                'clientSecret' => $paymentIntent->client_secret,
            ];

            return response()->json($output, 200);
        } catch (\Exception $e) {
            // http_response_code(500);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  Request $request
    //  * @return void
     */
    public function webhook(Request $request)
    {
        \Log::debug("Webhook called");
        \Log::debug($request);

        $payload = $request->getContent();
        $sig_header = $request->header("Stripe-Signature");
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $this->endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
          // Invalid payload
          // http_response_code(400);
            \Log::debug("Invalid payload");
            return response()->json(['error' => 'Invalid payload'], 400);
          // exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
          // Invalid signature
            \Log::debug("Invalid signature");
            return response()->json(['error' => 'Invalid signature'], 400);
            //   http_response_code(400);
            //   exit();
        }

        $charge = null;
        $paymentIntent = null;
        $paymentLink = null;
        $paymentMethod = null;

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.amount_capturable_updated':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.canceled':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.created':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.partially_funded':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.processing':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.requires_action':
                $paymentIntent = $event->data->object;
                break;
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                break;
            case 'charge.succeeded':
                $charge = $event->data->object;
                break;
            case 'payment_link.created':
                $paymentLink = $event->data->object;
                break;
            case 'payment_link.updated':
                $paymentLink = $event->data->object;
                break;
            case 'payment_method.attached':
                $paymentMethod = $event->data->object;
                break;
            case 'payment_method.automatically_updated':
                $paymentMethod = $event->data->object;
                break;
            case 'payment_method.detached':
                $paymentMethod = $event->data->object;
                break;
            case 'payment_method.updated':
                $paymentMethod = $event->data->object;
                break;
            // ... handle other event types
          default:
            echo 'Received unknown event type ' . $event->type;
        }

        \Log::debug("Charge: " . $charge);
        \Log::debug("Payment Intent: " . $paymentIntent);
        \Log::debug("Payment Link: " . $paymentLink);
        \Log::debug("Payment Method: " . $paymentMethod);
    }

    function calculateOrderAmount(array $items): int
    {
        // Replace this constant with a calculation of the order's amount
        // Calculate the order total on the server to prevent
        // people from directly manipulating the amount on the client
        return 15 * 100;
    }
}
