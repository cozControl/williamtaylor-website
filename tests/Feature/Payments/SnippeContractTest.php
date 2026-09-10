<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Domain\Payments\Snippe\SnippeClient;
use App\Domain\Payments\Snippe\SnippeException;
use App\Domain\Payments\Snippe\SnippeMoney;
use App\Domain\Payments\Snippe\VerifySnippeWebhook;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SnippeContractTest extends TestCase
{
    public function test_exact_money_conversion_rejects_fraction_currency_minimum_and_overflow(): void
    {
        $this->assertSame(500, SnippeMoney::tzs(50000, 'TZS'));
        $this->assertSame(320000, SnippeMoney::tzs(32000000, 'TZS'));
        $this->assertSame(125000, SnippeMoney::tzs(12500000, 'TZS', false));
        foreach ([[0, 'TZS'], [-100, 'TZS'], [49900, 'TZS'], [50001, 'TZS'], [50000, 'USD'], [PHP_INT_MAX, 'TZS']] as [$amount, $currency]) {
            try {
                SnippeMoney::tzs($amount, $currency);
                $this->fail('Invalid amount accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            SnippeMoney::tzs(PHP_INT_MAX * 2, 'TZS');
            $this->fail('Overflow accepted');
        } catch (\TypeError) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_raw_body_hmac_rejects_missing_invalid_changed_stale_future_and_malformed(): void
    {
        config(['snippe.webhook_secret' => 'contract-secret']);
        $raw = '{"type":"payment.completed","data":{}}';
        $timestamp = (string) time();
        $valid = hash_hmac('sha256', $timestamp.'.'.$raw, 'contract-secret');
        $request = fn ($body, $time, $signature) => Request::create('/webhooks/snippe', 'POST', [], [], [], ['HTTP_X_WEBHOOK_TIMESTAMP' => $time, 'HTTP_X_WEBHOOK_SIGNATURE' => $signature], $body);
        $this->assertSame('payment.completed', app(VerifySnippeWebhook::class)->verify($request($raw, $timestamp, $valid))['type']);
        foreach ([[$raw, $timestamp, ''], [$raw, $timestamp, str_repeat('0', 64)], [$raw.' ', $timestamp, $valid], [$raw, (string) (time() - 301), null], [$raw, (string) (time() + 301), null], [$raw, 'bad', null], ['{broken', $timestamp, null]] as [$body, $time, $signature]) {
            $signature ??= hash_hmac('sha256', $time.'.'.$body, 'contract-secret');
            try {
                app(VerifySnippeWebhook::class)->verify($request($body, $time, $signature));
                $this->fail('Invalid webhook accepted');
            } catch (HttpException $error) {
                $this->assertSame($body === '{broken' ? 400 : 401, $error->getStatusCode());
            }
        }
    }

    public function test_client_errors_are_sanitized_and_posts_are_not_retried(): void
    {
        config(['snippe.enabled' => true, 'snippe.api_key' => 'contract-key']);
        Http::preventStrayRequests();
        foreach ([400, 401, 403, 409, 422, 429, 500, 503] as $status) {
            Http::swap(new Factory);
            Http::fake(['*' => Http::response(['message' => 'sensitive provider payload'], $status)]);
            try {
                app(SnippeClient::class)->create(['amount' => 500], 'wt-contract');
                $this->fail('Provider failure accepted');
            } catch (SnippeException $error) {
                $this->assertStringNotContainsString('sensitive', $error->getMessage());
                $this->assertSame($status === 429 ? 'rate_limited' : 'http_'.$status, $error->reason);
            }
            Http::assertSentCount(1);
        }
        Http::swap(new Factory);
        Http::fake(['*' => Http::failedConnection()]);
        try {
            app(SnippeClient::class)->create(['amount' => 500], 'wt-timeout');
            $this->fail('Timeout accepted');
        } catch (SnippeException $error) {
            $this->assertTrue($error->ambiguous);
        }
        foreach ([['data' => []], ['data' => ['reference' => 'sess_one', 'status' => 'pending', 'amount' => 500, 'currency' => 'TZS', 'checkout_url' => 'https://evil.test/checkout']]] as $body) {
            Http::swap(new Factory);
            Http::fake(['*' => Http::response($body)]);
            try {
                app(SnippeClient::class)->create(['amount' => 500], 'wt-malformed');
                $this->fail('Malformed response accepted');
            } catch (SnippeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_get_retries_transient_errors_and_cancel_uses_documented_endpoint(): void
    {
        config(['snippe.enabled' => true, 'snippe.api_key' => 'contract-key']);
        Http::fake(['*' => Http::sequence()->push([], 503)->push(['data' => ['reference' => 'sess_contract', 'status' => 'expired', 'amount' => 500, 'currency' => 'TZS']], 200)->push(['message' => 'session cancelled'], 200)]);
        $this->assertSame('expired', app(SnippeClient::class)->get('sess_contract')->status);
        app(SnippeClient::class)->cancel('sess_contract', 'wt-cancel');
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://api.snippe.sh/api/v1/sessions/sess_contract/cancel' && $request->hasHeader('Idempotency-Key', 'wt-cancel'));
    }
}
