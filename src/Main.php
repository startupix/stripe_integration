<?php

namespace App;

use Exception;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Product;
use Stripe\StripeClient;
use Throwable;

class Main
{
    protected StripeClient $stripe_client;

    public function __construct()
    {
        $this->stripe_client = new StripeClient($_ENV['STRIPE_KEY']);
    }

    /**
     * Subscribe customer to payment plan
     *
     * @return string
     */
    public function index(): string
    {
        try {
            $product = $this->getProduct();

            $timestamp = (int)(microtime(true));
            $customer_email = "armanpetrosyan9+{$timestamp}@gmail.com";

            //  1. Create a Stripe customer with unique email and name
            $customer = $this->stripe_client->customers->create([
                'email' => $_GET['customer_email'] ?? $customer_email,
                'name' => $_GET['name'] ?? 'Arman Petrosyan',
            ]);

            $customer = $this->addPaymentToCustomer($customer->id);

            //  4. Create Subscription with a 7 day trial for the given pricing
            $subscription = $this->stripe_client->subscriptions->create([
                'customer' => $customer->id,
                'items' => [
                    [
                        'price_data' => [
                            'product' => $product->id,
                            'unit_amount' => 1000, // 10 USD
                            'currency' => 'usd',
                            'recurring' => [
                                'interval' => 'month',
                                'interval_count' => 1
                            ]
                        ]
                    ]
                ],
                'trial_end' => strtotime('+7 day'),
                'default_payment_method' => $customer->invoice_settings['default_payment_method']
            ]);

            //  5. echo() the date for upcoming charge for this new subscription
            $return_message = 'Customer next payment day will be: ' . date('r', $subscription->billing_cycle_anchor);
        } catch (ApiErrorException $e) {
            $return_message = $e->getMessage();
        } catch (Throwable $e) {
            $return_message = $e->getMessage();
        }
        return $return_message;
    }

    /**
     * Either create or retrieve product from stripe
     *
     * @return Product
     * @throws ApiErrorException
     */
    protected function getProduct(): Product
    {
        // get product for customer subscription
        $search = $this->stripe_client->products->search([
            'query' => 'active:\'true\' AND metadata[\'product_id\']:\'' . $_ENV['PRODUCT_ID'] . '\'',
        ]);
        // create product if not exists
        if (!empty($search->data) && $search->data[0]->id === $_ENV['PRODUCT_ID']) {
            $product = $search->data[0];
        } else {
            // create product
            $product = $this->stripe_client->products->create([
                'name' => 'Our Test Product',
                'id' => $_ENV['PRODUCT_ID'],
                'metadata' => [
                    'product_id' => $_ENV['PRODUCT_ID']
                ]
            ]);
        }
        return $product;
    }

    /**
     * Create payment method and attach to the customer as default.
     *
     * @param string $customer_id
     * @return Customer
     * @throws ApiErrorException
     * @throws Exception
     */
    protected function addPaymentToCustomer(string $customer_id): Customer
    {
        $date = new \DateTime('+4 months');
        //  2. Create a Stripe PaymentMethod for a given card $TEST_CARD
        $payment_method = $this->stripe_client->paymentMethods->create([
            'type' => 'card',
            'card' => [
                'number' => $_ENV['TEST_CARD'],
                'exp_month' => $date->format('m'),
                'exp_year' => $date->format('Y'),
                'cvc' => random_int(100, 999)
            ]
        ]);

        //  3. Attach this Payment Method to the customer you have created above
        $this->stripe_client->paymentMethods->attach(
            $payment_method->id,
            ['customer' => $customer_id]
        );

        // Update Customer payment settings to use attached card as default
        return $this->stripe_client->customers->update(
            $customer_id,
            [
                'invoice_settings' => [
                    'default_payment_method' => $payment_method->id
                ]
            ]
        );
    }
}