<?php

declare(strict_types=1);

/**
 * Unit tests for MobileMoneyCallbackHandler — HMAC webhook signature
 * verification.
 *
 * Only the signature-verification branch is unit-testable in isolation:
 * once a signature is accepted as valid, handle() looks up the Payment
 * model via Eloquent and calls $payment->update(...), which requires a
 * booted Laravel application and a real database connection (tests/Unit
 * does not boot the framework — see tests/Pest.php). The rejection path
 * is a self-contained, security-critical rule — a forged or replayed
 * callback must never be able to mark a payment as confirmed — and is
 * fully exercised here without touching the database.
 */

use Modules\Commerce\Internal\Services\MobileMoneyCallbackHandler;

it('rejects_a_callback_whose_signature_does_not_match_the_hmac_of_the_body', function (): void {
    $handler = new MobileMoneyCallbackHandler(secret: 'top-secret');

    $action = fn () => $handler->handle(
        reference: 'ref-1',
        status: 'success',
        rawBody: '{"reference":"ref-1","status":"success"}',
        signature: 'sha256=deadbeef',
    );

    expect($action)->toThrow(\InvalidArgumentException::class, 'Invalid mobile money callback signature.');
});

it('rejects_a_callback_signed_with_the_wrong_secret', function (): void {
    $rawBody        = '{"reference":"ref-1","status":"success"}';
    $wrongSignature = 'sha256=' . hash_hmac('sha256', $rawBody, 'a-different-secret');

    $handler = new MobileMoneyCallbackHandler(secret: 'top-secret');

    $action = fn () => $handler->handle('ref-1', 'success', $rawBody, $wrongSignature);

    expect($action)->toThrow(\InvalidArgumentException::class);
});

it('rejects_a_callback_whose_body_was_tampered_with_after_signing', function (): void {
    $secret       = 'top-secret';
    $signedBody   = '{"reference":"ref-1","status":"success"}';
    $signature    = 'sha256=' . hash_hmac('sha256', $signedBody, $secret);
    $tamperedBody = '{"reference":"ref-1","status":"failed"}';

    $handler = new MobileMoneyCallbackHandler($secret);

    $action = fn () => $handler->handle('ref-1', 'failed', $tamperedBody, $signature);

    expect($action)->toThrow(\InvalidArgumentException::class);
});

it('rejects_an_empty_signature', function (): void {
    $handler = new MobileMoneyCallbackHandler(secret: 'top-secret');

    $action = fn () => $handler->handle('ref-1', 'success', '{}', '');

    expect($action)->toThrow(\InvalidArgumentException::class);
});

it('rejects_a_signature_missing_the_sha256_prefix', function (): void {
    $secret  = 'top-secret';
    $rawBody = '{"reference":"ref-1","status":"success"}';

    // Correct HMAC, but without the "sha256=" prefix the handler requires.
    $signatureWithoutPrefix = hash_hmac('sha256', $rawBody, $secret);

    $handler = new MobileMoneyCallbackHandler($secret);

    $action = fn () => $handler->handle('ref-1', 'success', $rawBody, $signatureWithoutPrefix);

    expect($action)->toThrow(\InvalidArgumentException::class);
});
