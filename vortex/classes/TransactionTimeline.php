<?php

/**
 * ------------------------------------------------------------
 * TransactionTimeline.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Manages chronological event audit trails for every
 *           transaction (attempts, failures, chargebacks, won disputes,
 *           refunds, speed changes).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class TransactionTimeline
{
    /**
     * Record an event in the chronological transaction timeline.
     *
     * @param string $vortexTxId   Vortex Transaction ID (e.g. VTX1791175942335D586008C)
     * @param string $eventType    Event type (e.g. PAYMENT_FAILED, PAYMENT_CAPTURED, CHARGEBACK_CREATED, REFUND_PROCESSED)
     * @param string $status       Normalized status (PENDING, SUCCESS, FAILED, DISPUTED, REFUNDED)
     * @param array  $details      Optional details (transaction_id, order_id, payment_id, amount, currency, reason, source, metadata)
     * @return bool
     */
    public static function record(string $vortexTxId, string $eventType, string $status, array $details = []): bool
    {
        try {
            $db = Database::getInstance()->getConnection();

            $txId      = $details['transaction_id'] ?? null;
            $orderId   = $details['order_id'] ?? null;
            $paymentId = $details['payment_id'] ?? null;
            $amount    = $details['amount'] ?? null;
            $currency  = $details['currency'] ?? 'INR';
            $reason    = $details['reason'] ?? null;
            $source    = $details['source'] ?? 'RAZORPAY_WEBHOOK';
            $metadata  = !empty($details['metadata']) ? json_encode($details['metadata'], JSON_UNESCAPED_SLASHES) : null;

            // Auto-fill transaction_id / order_id / payment_id from transactions table if missing
            if ((!$txId || !$orderId) && !empty($vortexTxId)) {
                $stmtTx = $db->prepare("SELECT id, razorpay_order_id, razorpay_payment_id, amount, currency FROM transactions WHERE vortex_transaction_id = ? LIMIT 1");
                $stmtTx->execute([$vortexTxId]);
                $txRow = $stmtTx->fetch(PDO::FETCH_ASSOC);
                if ($txRow) {
                    $txId      = $txId ?: $txRow['id'];
                    $orderId   = $orderId ?: $txRow['razorpay_order_id'];
                    $paymentId = $paymentId ?: $txRow['razorpay_payment_id'];
                    $amount    = ($amount !== null) ? $amount : $txRow['amount'];
                    $currency  = $currency ?: ($txRow['currency'] ?? 'INR');
                }
            }

            $stmt = $db->prepare("
                INSERT INTO transaction_timeline 
                    (vortex_transaction_id, transaction_id, order_id, payment_id, event_type, status, amount, currency, reason, source, metadata, created_at)
                VALUES 
                    (:vortex_id, :tx_id, :order_id, :payment_id, :event_type, :status, :amount, :currency, :reason, :source, :metadata, NOW())
            ");

            $stmt->execute([
                ':vortex_id'   => $vortexTxId,
                ':tx_id'       => $txId,
                ':order_id'    => $orderId,
                ':payment_id'  => $paymentId,
                ':event_type'  => $eventType,
                ':status'      => $status,
                ':amount'      => $amount,
                ':currency'    => $currency,
                ':reason'      => $reason,
                ':source'      => $source,
                ':metadata'    => $metadata
            ]);

            Logger::info("Timeline recorded [{$vortexTxId}] -> Event: {$eventType}, Status: {$status}");
            return true;
        } catch (\Throwable $e) {
            Logger::error("Failed to record timeline for [{$vortexTxId}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve full chronological timeline for a transaction.
     *
     * @param string $vortexTxId
     * @param int|null $dbId
     * @return array
     */
    public static function getTimeline(string $vortexTxId, ?int $dbId = null): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            if (!empty($dbId)) {
                $stmt = $db->prepare("
                    SELECT * FROM transaction_timeline 
                    WHERE vortex_transaction_id = :vortex_id 
                       OR transaction_id = :db_id
                    ORDER BY id ASC
                ");
                $stmt->execute([
                    ':vortex_id' => $vortexTxId,
                    ':db_id'     => $dbId
                ]);
            } else {
                $stmt = $db->prepare("
                    SELECT * FROM transaction_timeline 
                    WHERE vortex_transaction_id = :vortex_id 
                    ORDER BY id ASC
                ");
                $stmt->execute([
                    ':vortex_id' => $vortexTxId
                ]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            Logger::error("Failed to fetch timeline: " . $e->getMessage());
            return [];
        }
    }
}
