<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\WebhookSignature;

/**
 * Stands in for the Stripe API at the HTTP layer, so the real StripeClient,
 * CheckoutController and webhook signature check all run without a network.
 *
 * Install it with FakeStripe::install(); it records every call it receives.
 */
class FakeStripe implements ClientInterface
{
    public const WEBHOOK_SECRET = 'whsec_test_secret';

    /** @var list<array{method: string, path: string, params: array, headers: array}> */
    public array $calls = [];

    /** @var array<string, array> payment intents Stripe "knows", by id */
    private array $intents = [];

    /** Idempotency keys already used, mapped to the refund they produced. */
    private array $refundsByKey = [];

    public static function install(): self
    {
        $fake = new self;

        ApiRequestor::setHttpClient($fake);
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    /**
     * Register a PaymentIntent as Stripe would return it.
     */
    public function intent(string $id, int $userId, int $amount, string $status = 'succeeded', array $metadata = []): array
    {
        return $this->intents[$id] = $this->intentPayload($id, $amount, $status, ['user_id' => (string) $userId] + $metadata);
    }

    /**
     * A payment_intent.succeeded (or other) event body, signed the way Stripe signs it.
     *
     * @return array{string, array<string, string>} the raw body and the headers to send with it
     */
    public function signedEvent(string $type, array $object, ?string $secret = null): array
    {
        $body = json_encode([
            'id' => 'evt_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ]);

        return [$body, ['Stripe-Signature' => WebhookSignature::generateSignatureHeader($body, $secret ?? self::WEBHOOK_SECRET)]];
    }

    /** @return list<array> */
    public function callsTo(string $method, string $path): array
    {
        return array_values(array_filter(
            $this->calls,
            fn ($call) => $call['method'] === $method && $call['path'] === $path,
        ));
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $method = strtolower($method);
        $path = parse_url($absUrl, PHP_URL_PATH);

        $this->calls[] = ['method' => $method, 'path' => $path, 'params' => $params ?? [], 'headers' => $headers];

        return match (true) {
            $method === 'post' && $path === '/v1/payment_intents' => $this->respond($this->createIntent($params)),
            $method === 'get' && str_starts_with($path, '/v1/payment_intents/') => $this->retrieveIntent(basename($path)),
            $method === 'post' && $path === '/v1/refunds' => $this->respond($this->createRefund($params, $headers)),
            default => $this->respond(['error' => ['message' => "FakeStripe: no route for {$method} {$path}"]], 404),
        };
    }

    private function createIntent(array $params): array
    {
        $id = 'pi_'.bin2hex(random_bytes(6));

        return $this->intents[$id] = $this->intentPayload($id, (int) $params['amount'], 'requires_payment_method', $params['metadata'] ?? []);
    }

    private function retrieveIntent(string $id): array
    {
        return isset($this->intents[$id])
            ? $this->respond($this->intents[$id])
            : $this->respond(['error' => ['code' => 'resource_missing', 'message' => "No such payment_intent: {$id}"]], 404);
    }

    private function createRefund(array $params, array $headers): array
    {
        $key = collect($headers)->first(fn ($h) => str_starts_with(strtolower($h), 'idempotency-key:'));
        $key = $key ? trim(substr($key, strlen('idempotency-key:'))) : null;

        // Stripe returns the original refund when a key is replayed.
        if ($key !== null && isset($this->refundsByKey[$key])) {
            return $this->refundsByKey[$key];
        }

        $refund = ['id' => 're_'.bin2hex(random_bytes(6)), 'object' => 'refund', 'payment_intent' => $params['payment_intent'], 'status' => 'succeeded'];

        return $key === null ? $refund : $this->refundsByKey[$key] = $refund;
    }

    private function intentPayload(string $id, int $amount, string $status, array $metadata): array
    {
        return [
            'id' => $id,
            'object' => 'payment_intent',
            'amount' => $amount,
            'amount_received' => $status === 'succeeded' ? $amount : 0,
            'currency' => 'usd',
            'status' => $status,
            'client_secret' => $id.'_secret_fake',
            'metadata' => $metadata,
        ];
    }

    private function respond(array $body, int $code = 200): array
    {
        return [json_encode($body), $code, []];
    }
}
