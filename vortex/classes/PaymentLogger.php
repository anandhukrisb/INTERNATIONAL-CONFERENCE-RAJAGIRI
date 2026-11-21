<?php

/**
 * ------------------------------------------------------------
 * PaymentLogger.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 *
 * Purpose:
 * Records every significant payment lifecycle event into the
 * `payment_logs` table — both successes and failures —
 * including full request/response payloads, Razorpay error
 * details, card/method metadata, and customer context.
 *
 * Usage:
 *   PaymentLogger::log([
 *       'event_type'          => 'order.created',
 *       'transaction_id'      => 42,
 *       'vortex_transaction_id' => 'VTX...',
 *       'razorpay_order_id'   => 'order_...',
 *       'currency'            => 'USD',
 *       'amount'              => 100.00,
 *       'payment_status'      => 'created',
 *       'request_payload'     => [...],   // array — will be JSON-encoded
 *       'response_payload'    => [...],   // array — will be JSON-encoded
 *       'customer_email'      => '...',
 *       'customer_mobile'     => '...',
 *       'api_client_id'       => 3,
 *   ]);
 *
 * All keys are optional except `event_type`.
 * Never throws — logging errors are silently swallowed so they
 * never break the payment flow.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';


class PaymentLogger
{
    // --------------------------------------------------------
    // Recognised event_type constants (non-exhaustive)
    // --------------------------------------------------------
    const EVENT_ORDER_CREATED       = 'order.created';
    const EVENT_ORDER_FAILED        = 'order.failed';
    const EVENT_VERIFY_SUCCESS      = 'verify.success';
    const EVENT_VERIFY_FAILED       = 'verify.failed';
    const EVENT_WEBHOOK_RECEIVED    = 'webhook.received';
    const EVENT_WEBHOOK_CAPTURED    = 'webhook.captured';
    const EVENT_WEBHOOK_FAILED      = 'webhook.failed';
    const EVENT_WEBHOOK_REFUNDED    = 'webhook.refunded';
    const EVENT_3DS_TIMEOUT         = '3ds.timeout';
    const EVENT_PAYMENT_FAILED_JS   = 'payment.failed.js';   // from checkout JS → PHP relay


    /**
     * --------------------------------------------------------
     * log()
     * --------------------------------------------------------
     * Insert one row into `payment_logs`.
     *
     * @param  array $data  Associative array — see file docblock.
     * @return int|false    Last insert ID on success, false on failure.
     * --------------------------------------------------------
     */
    public static function log(array $data): int|false
    {
        try {
            $db = Database::getInstance()->getConnection();

            // ------------------------------------------------
            // Resolve is_international from currency
            // ------------------------------------------------
            $currency = strtoupper(trim($data['currency'] ?? ''));
            $isInternational = ($currency !== '' && $currency !== 'INR') ? 1 : 0;

            // ------------------------------------------------
            // JSON-encode payload arrays
            // ------------------------------------------------
            $requestPayload  = self::encodePayload($data['request_payload']  ?? null);
            $responsePayload = self::encodePayload($data['response_payload'] ?? null);

            // ------------------------------------------------
            // Sanitise / extract fields
            // ------------------------------------------------
            $eventType           = substr(trim($data['event_type']            ?? 'unknown'), 0, 50);
            $transactionId       = isset($data['transaction_id'])      && is_numeric($data['transaction_id'])
                                   ? (int) $data['transaction_id'] : null;
            $vortexTransactionId = substr(trim($data['vortex_transaction_id'] ?? ''), 0, 64)  ?: null;
            $razorpayOrderId     = substr(trim($data['razorpay_order_id']     ?? ''), 0, 100) ?: null;
            $razorpayPaymentId   = substr(trim($data['razorpay_payment_id']   ?? ''), 0, 100) ?: null;
            $apiClientId         = isset($data['api_client_id']) && is_numeric($data['api_client_id'])
                                   ? (int) $data['api_client_id'] : null;
            $paymentStatus       = substr(trim($data['payment_status']        ?? ''), 0, 20)  ?: null;
            $paymentMethod       = substr(trim($data['payment_method']        ?? ''), 0, 50)  ?: null;
            $cardNetwork         = substr(trim($data['card_network']          ?? ''), 0, 30)  ?: null;
            $cardLast4           = substr(trim($data['card_last4']            ?? ''), 0, 4)   ?: null;
            $cardType            = substr(trim($data['card_type']             ?? ''), 0, 20)  ?: null;
            $errorCode           = substr(trim($data['error_code']            ?? ''), 0, 100) ?: null;
            $errorDescription    = trim($data['error_description']            ?? '') ?: null;
            $errorReason         = substr(trim($data['error_reason']          ?? ''), 0, 100) ?: null;
            $errorStep           = substr(trim($data['error_step']            ?? ''), 0, 100) ?: null;
            $errorSource         = substr(trim($data['error_source']          ?? ''), 0, 50)  ?: null;
            $customerEmail       = substr(trim($data['customer_email']        ?? ''), 0, 255) ?: null;
            $customerMobile      = substr(trim($data['customer_mobile']       ?? ''), 0, 30)  ?: null;
            $amount              = isset($data['amount']) && is_numeric($data['amount'])
                                   ? round((float)$data['amount'], 2) : null;
            $ipAddress           = substr(trim($data['ip_address'] ?? self::resolveIp()), 0, 45) ?: null;
            $userAgent           = substr(trim($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500) ?: null;

            // ------------------------------------------------
            // INSERT
            // ------------------------------------------------
            $sql = "INSERT INTO payment_logs (
                        event_type,
                        transaction_id,
                        vortex_transaction_id,
                        razorpay_order_id,
                        razorpay_payment_id,
                        api_client_id,
                        currency,
                        is_international,
                        payment_status,
                        payment_method,
                        card_network,
                        card_last4,
                        card_type,
                        request_payload,
                        response_payload,
                        error_code,
                        error_description,
                        error_reason,
                        error_step,
                        error_source,
                        amount,
                        customer_email,
                        customer_mobile,
                        ip_address,
                        user_agent
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $eventType,
                $transactionId,
                $vortexTransactionId,
                $razorpayOrderId,
                $razorpayPaymentId,
                $apiClientId,
                $currency ?: null,
                $isInternational,
                $paymentStatus,
                $paymentMethod,
                $cardNetwork,
                $cardLast4,
                $cardType,
                $requestPayload,
                $responsePayload,
                $errorCode,
                $errorDescription,
                $errorReason,
                $errorStep,
                $errorSource,
                $amount,
                $customerEmail,
                $customerMobile,
                $ipAddress,
                $userAgent,
            ]);

            return (int) $db->lastInsertId();

        } catch (\Throwable $e) {
            // Never break the payment flow — just log internally
            Logger::error('PaymentLogger::log() failed: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * --------------------------------------------------------
     * logFromRazorpayOrder()
     * --------------------------------------------------------
     * Convenience wrapper for order.created events.
     *
     * @param array       $orderRequest  Payload sent to Razorpay
     * @param array|false $orderResponse Razorpay order object (or false on failure)
     * @param array       $context       Extra context (transaction_id, email, etc.)
     * --------------------------------------------------------
     */
    public static function logFromRazorpayOrder(
        array $orderRequest,
        $orderResponse,
        array $context = []
    ): int|false {
        $success = ($orderResponse !== false && !empty($orderResponse['id']));

        return self::log(array_merge([
            'event_type'       => $success ? self::EVENT_ORDER_CREATED : self::EVENT_ORDER_FAILED,
            'razorpay_order_id'=> $success ? ($orderResponse['id'] ?? null) : null,
            'payment_status'   => $success ? 'created' : 'failed',
            'request_payload'  => $orderRequest,
            'response_payload' => $orderResponse ?: ['error' => 'Razorpay order creation returned false'],
        ], $context));
    }


    /**
     * --------------------------------------------------------
     * logVerification()
     * --------------------------------------------------------
     * Convenience wrapper for verify.success / verify.failed events.
     *
     * @param bool   $success
     * @param array  $requestData   What was submitted to verify_payment.php
     * @param array  $context       Transaction context from the DB
     * @param string $failReason    Human-readable reason (on failure)
     * --------------------------------------------------------
     */
    public static function logVerification(
        bool $success,
        array $requestData,
        array $context = [],
        string $failReason = ''
    ): int|false {
        $data = array_merge([
            'event_type'          => $success ? self::EVENT_VERIFY_SUCCESS : self::EVENT_VERIFY_FAILED,
            'payment_status'      => $success ? 'captured' : 'failed',
            'request_payload'     => $requestData,
            'razorpay_payment_id' => $requestData['razorpay_payment_id'] ?? null,
            'razorpay_order_id'   => $requestData['razorpay_order_id']   ?? null,
        ], $context);

        if (!$success && $failReason !== '') {
            $data['error_description'] = $failReason;
            $data['error_code']        = 'SIGNATURE_VERIFICATION_FAILED';
        }

        return self::log($data);
    }


    /**
     * --------------------------------------------------------
     * logWebhook()
     * --------------------------------------------------------
     * Convenience wrapper for incoming Razorpay webhook events.
     *
     * @param string $eventName   e.g. 'payment.captured', 'payment.failed'
     * @param array  $payload     Full decoded webhook JSON
     * @param array  $context     Transaction context
     * --------------------------------------------------------
     */
    public static function logWebhook(
        string $eventName,
        array $payload,
        array $context = []
    ): int|false {
        // Map Razorpay event names to our internal event_type
        $eventTypeMap = [
            'payment.captured'  => self::EVENT_WEBHOOK_CAPTURED,
            'payment.failed'    => self::EVENT_WEBHOOK_FAILED,
            'refund.created'    => self::EVENT_WEBHOOK_REFUNDED,
            'refund.processed'  => self::EVENT_WEBHOOK_REFUNDED,
        ];
        $internalEventType = $eventTypeMap[$eventName] ?? ('webhook.' . $eventName);

        // Extract payment entity if present
        $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
        $errorPayload  = $paymentEntity['error_code'] ?? null
                         ? $paymentEntity
                         : ($payload['payload']['payment']['entity']['error'] ?? []);

        $data = array_merge([
            'event_type'          => $internalEventType,
            'razorpay_order_id'   => $paymentEntity['order_id']   ?? null,
            'razorpay_payment_id' => $paymentEntity['id']         ?? null,
            'currency'            => $paymentEntity['currency']    ?? null,
            'amount'              => isset($paymentEntity['amount'])
                                     ? round($paymentEntity['amount'] / 100, 2) : null,
            'payment_status'      => $paymentEntity['status']     ?? null,
            'payment_method'      => $paymentEntity['method']     ?? null,
            'card_network'        => $paymentEntity['card']['network'] ?? null,
            'card_last4'          => $paymentEntity['card']['last4']   ?? null,
            'card_type'           => $paymentEntity['card']['type']    ?? null,
            'customer_email'      => $paymentEntity['email']      ?? null,
            'customer_mobile'     => $paymentEntity['contact']    ?? null,
            'error_code'          => $paymentEntity['error_code']        ?? null,
            'error_description'   => $paymentEntity['error_description'] ?? null,
            'error_reason'        => $paymentEntity['error_reason']      ?? null,
            'error_step'          => $paymentEntity['error_step']        ?? null,
            'error_source'        => $paymentEntity['error_source']      ?? null,
            'response_payload'    => $payload,
        ], $context);

        return self::log($data);
    }


    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    private static function encodePayload($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        return (string) $value;
    }

    private static function resolveIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP']    ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR']     ?? '',
            $_SERVER['HTTP_X_REAL_IP']           ?? '',
            $_SERVER['REMOTE_ADDR']              ?? '',
        ];
        foreach ($candidates as $ip) {
            $ip = trim(explode(',', $ip)[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '';
    }
}

?>
