<?php

/**
 * ------------------------------------------------------------
 * Event.php
 * ------------------------------------------------------------
 * Handles event-related database operations.
 * ------------------------------------------------------------
 */

// Load the Database class.
require_once __DIR__ . '/Database.php';

// Load the EventIdGenerator class.
require_once __DIR__ . '/EventIdGenerator.php';

// Load the Validator class.
require_once __DIR__ . '/Validator.php';

// Load the Logger class.
require_once __DIR__ . '/Logger.php';


class Event
{
    /**
     * Database connection.
     */
    private $db;


    /**
     * Constructor.
     *
     * Gets the database connection from Database.php.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }


    /**
     * --------------------------------------------------------
     * Get Active Event
     * --------------------------------------------------------
     * Finds an event using its event ID.
     *
     * The event must:
     * - Exist in the database
     * - Be active
     *
     * Returns:
     * - Event details if valid
     * - false if the event is not available
     * --------------------------------------------------------
     */
    public function getActiveEvent($eventId)
    {
        try {

            // SQL query to find the active event.
            $query = "SELECT id, event_id, event_name, department,
                             start_date, end_date, webhook_url, webhook_secret, is_active
                      FROM events
                      WHERE event_id = ?
                      AND is_active = 1";


            // Prepare the SQL query.
            $statement = $this->db->prepare($query);


            // Execute the query using the event ID.
            $statement->execute([$eventId]);


            // Get the event record.
            $event = $statement->fetch();


            // Check whether an event was found.
            if ($event) {
                return $event;
            }


            // Event was not found or is not currently active.
            return false;


        } catch (\PDOException $e) {

            // Log database error.
            Logger::error(
                'Failed to fetch active event. '
                . 'Event ID: ' . $eventId
                . '. Error: ' . $e->getMessage()
            );

            return false;
        }
    }


    /**
     * --------------------------------------------------------
     * Create Event
     * --------------------------------------------------------
     * Creates a new event after validating the input.
     * --------------------------------------------------------
     */
    public function createEvent(
        $eventName,
        $department,
        $startDate,
        $endDate,
        $webhookUrl = null
    ) {
        try {

            // Validate event name.
            if (!Validator::required($eventName)) {

                Logger::error(
                    'Event creation failed: Event name is required.'
                );

                return [
                    'success' => false,
                    'message' => 'Event name is required.'
                ];
            }


            // Validate department name.
            if (!Validator::required($department)) {

                Logger::error(
                    'Event creation failed: Department is required.'
                );

                return [
                    'success' => false,
                    'message' => 'Department is required.'
                ];
            }


            // Validate start date.
            if (!Validator::required($startDate)) {

                Logger::error(
                    'Event creation failed: Start date is required.'
                );

                return [
                    'success' => false,
                    'message' => 'Start date is required.'
                ];
            }


            // Validate end date.
            if (!Validator::required($endDate)) {

                Logger::error(
                    'Event creation failed: End date is required.'
                );

                return [
                    'success' => false,
                    'message' => 'End date is required.'
                ];
            }


            // Check whether the dates are valid.
            $startTimestamp = strtotime($startDate);
            $endTimestamp = strtotime($endDate);


            if (
                $startTimestamp === false ||
                $endTimestamp === false
            ) {

                Logger::error(
                    'Event creation failed: Invalid start date or end date.'
                );

                return [
                    'success' => false,
                    'message' => 'Invalid start date or end date.'
                ];
            }


            // Check whether end date is after start date.
            if ($endTimestamp <= $startTimestamp) {

                Logger::error(
                    'Event creation failed: '
                    . 'End date must be later than start date.'
                );

                return [
                    'success' => false,
                    'message' => 'End date must be later than start date.'
                ];
            }

            // Validate webhook URL if provided
            $webhookSecret = null;
            if (!empty($webhookUrl)) {
                $webhookUrl = trim($webhookUrl);
                if (!filter_var($webhookUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($webhookUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    Logger::error('Event creation failed: Invalid webhook URL: ' . $webhookUrl);
                    return [
                        'success' => false,
                        'message' => 'Invalid webhook URL. Must start with http:// or https://'
                    ];
                }
                $webhookSecret = bin2hex(random_bytes(24));
            } else {
                $webhookUrl = null;
            }


            // Generate a unique event ID.
            $eventId = EventIdGenerator::generate(
                $eventName,
                $startDate
            );


            // SQL query to insert the event.
            $query = "INSERT INTO events
                    (event_id, event_name, department, start_date, end_date, webhook_url, webhook_secret)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";


            // Prepare the query.
            $statement = $this->db->prepare($query);


            // Execute the query.
            $statement->execute([
                $eventId,
                $eventName,
                $department,
                $startDate,
                $endDate,
                $webhookUrl,
                $webhookSecret
            ]);


            // Log successful event creation.
            Logger::info(
                'Event created successfully. '
                . 'Event ID: ' . $eventId
                . ', Event Name: ' . $eventName
            );


            // Return successful result.
            return [
                'success' => true,
                'message' => 'Event created successfully.',
                'data' => [
                    'event_id' => $eventId,
                    'webhook_url' => $webhookUrl,
                    'webhook_secret' => $webhookSecret
                ]
            ];


        } catch (\PDOException $e) {

            // Log database error.
            Logger::error(
                'Event creation database error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Unable to create event.'
            ];


        } catch (\Throwable $e) {

            // Log unexpected error.
            Logger::error(
                'Event creation unexpected error: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' =>
                    'An unexpected error occurred while creating the event.'
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Deactivate Event
     * --------------------------------------------------------
     * Deactivates an existing event by setting is_active to 0.
     *
     * @param string $eventId
     * @return array
     * --------------------------------------------------------
     */
    public function deactivateEvent($eventId)
    {
        try {
            $eventId = trim((string) $eventId);

            // Validate event ID.
            if (!Validator::eventId($eventId)) {
                return [
                    'success' => false,
                    'message' => 'Event ID is required.',
                    'error_code' => 400
                ];
            }

            // Check if the event exists in the database.
            $query = "SELECT id, is_active FROM events WHERE event_id = ? LIMIT 1";
            $statement = $this->db->prepare($query);
            $statement->execute([$eventId]);
            $event = $statement->fetch();

            if (!$event) {
                Logger::error('Event deactivation failed: Event not found. Event ID: ' . $eventId);
                return [
                    'success' => false,
                    'message' => 'Event not found.',
                    'error_code' => 404
                ];
            }

            // If already deactivated, return success idempotently.
            if ((int) $event['is_active'] === 0) {
                Logger::info('Event already deactivated. Event ID: ' . $eventId);
                return [
                    'success' => true,
                    'message' => 'Event deactivated successfully.',
                    'data' => [
                        'event_id' => $eventId
                    ]
                ];
            }

            // Deactivate the event.
            $updateQuery = "UPDATE events SET is_active = 0 WHERE event_id = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$eventId]);

            Logger::info('Event deactivated successfully. Event ID: ' . $eventId);

            return [
                'success' => true,
                'message' => 'Event deactivated successfully.',
                'data' => [
                    'event_id' => $eventId
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('Event deactivation database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred while deactivating the event.',
                'error_code' => 500
            ];
        } catch (\Throwable $e) {
            Logger::error('Event deactivation unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while deactivating the event.',
                'error_code' => 500
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Activate Event
     * --------------------------------------------------------
     * Activates an existing event by setting is_active to 1.
     *
     * @param string $eventId
     * @return array
     * --------------------------------------------------------
     */
    public function activateEvent($eventId)
    {
        try {
            $eventId = trim((string) $eventId);

            // Validate event ID.
            if (!Validator::eventId($eventId)) {
                return [
                    'success' => false,
                    'message' => 'Event ID is required.',
                    'error_code' => 400
                ];
            }

            // Check if the event exists in the database.
            $query = "SELECT id, is_active FROM events WHERE event_id = ? LIMIT 1";
            $statement = $this->db->prepare($query);
            $statement->execute([$eventId]);
            $event = $statement->fetch();

            if (!$event) {
                Logger::error('Event activation failed: Event not found. Event ID: ' . $eventId);
                return [
                    'success' => false,
                    'message' => 'Event not found.',
                    'error_code' => 404
                ];
            }

            // If already active, return success idempotently.
            if ((int) $event['is_active'] === 1) {
                Logger::info('Event already active. Event ID: ' . $eventId);
                return [
                    'success' => true,
                    'message' => 'Event activated successfully.',
                    'data' => [
                        'event_id' => $eventId
                    ]
                ];
            }

            // Activate the event.
            $updateQuery = "UPDATE events SET is_active = 1 WHERE event_id = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$eventId]);

            Logger::info('Event activated successfully. Event ID: ' . $eventId);

            return [
                'success' => true,
                'message' => 'Event activated successfully.',
                'data' => [
                    'event_id' => $eventId
                ]
            ];

        } catch (\PDOException $e) {
            Logger::error('Event activation database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'A database error occurred while activating the event.',
                'error_code' => 500
            ];
        } catch (\Throwable $e) {
            Logger::error('Event activation unexpected error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while activating the event.',
                'error_code' => 500
            ];
        }
    }


    /**
     * --------------------------------------------------------
     * Get All Events
     * --------------------------------------------------------
     * Retrieves all events ordered by creation date descending.
     *
     * @param int $limit
     * @return array
     * --------------------------------------------------------
     */
    public function getAllEvents($limit = 50)
    {
        try {
            $limit = max(1, min((int) $limit, 200));
            $query = "SELECT id, event_id, event_name, department,
                             start_date, end_date, webhook_url, webhook_secret, is_active, created_at
                      FROM events
                      ORDER BY id DESC
                      LIMIT " . $limit;

            $statement = $this->db->prepare($query);
            $statement->execute();

            return $statement->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to fetch events: ' . $e->getMessage());
            return [];
        } catch (\Throwable $e) {
            Logger::error('Unexpected error fetching events: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * --------------------------------------------------------
     * Update Event Webhook
     * --------------------------------------------------------
     * Updates or resets the webhook_url and secret for an event.
     *
     * @param string $eventId
     * @param string|null $webhookUrl
     * @param string|null $customSecret
     * @return array
     * --------------------------------------------------------
     */
    public function updateWebhook($eventId, $webhookUrl, ?string $customSecret = null)
    {
        try {
            $eventId = trim((string) $eventId);
            if (!Validator::eventId($eventId)) {
                return ['success' => false, 'message' => 'Event ID is required.'];
            }

            // Check if event exists
            $stmt = $this->db->prepare("SELECT id, webhook_secret FROM events WHERE event_id = ? LIMIT 1");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            if (!$event) {
                return ['success' => false, 'message' => 'Event not found.'];
            }

            $webhookUrl = trim((string) $webhookUrl);
            if (empty($webhookUrl)) {
                // Clear webhook
                $upd = $this->db->prepare("UPDATE events SET webhook_url = NULL, webhook_secret = NULL WHERE event_id = ?");
                $upd->execute([$eventId]);
                Logger::info("Cleared webhook for event #{$eventId}");
                return [
                    'success' => true,
                    'message' => 'Webhook URL removed successfully.',
                    'webhook_url' => null,
                    'webhook_secret' => null,
                    'data' => [
                        'event_id' => $eventId,
                        'webhook_url' => null,
                        'webhook_secret' => null
                    ]
                ];
            }

            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($webhookUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return ['success' => false, 'message' => 'Invalid webhook URL format. Must start with http:// or https://'];
            }

            // Determine secret: custom secret passed, or preserve existing, or generate new
            $secret = trim((string)$customSecret);
            if ($secret === '') {
                $secret = !empty($event['webhook_secret']) ? $event['webhook_secret'] : bin2hex(random_bytes(24));
            }

            $upd = $this->db->prepare("UPDATE events SET webhook_url = ?, webhook_secret = ? WHERE event_id = ?");
            $upd->execute([$webhookUrl, $secret, $eventId]);
            Logger::info("Updated webhook for event #{$eventId} to {$webhookUrl}");

            return [
                'success' => true,
                'message' => 'Webhook URL updated successfully.',
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $secret,
                'data' => [
                    'event_id' => $eventId,
                    'webhook_url' => $webhookUrl,
                    'webhook_secret' => $secret
                ]
            ];
        } catch (\PDOException $e) {
            Logger::error("Failed to update event webhook: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error updating webhook.'];
        }
    }


    /**
     * --------------------------------------------------------
     * Get Total Events Count
     * --------------------------------------------------------
     * Returns total count of events in database.
     *
     * @return int
     * --------------------------------------------------------
     */
    public function getTotalCount()
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM events");
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            Logger::error('Failed to get total events count: ' . $e->getMessage());
            return 0;
        } catch (\Throwable $e) {
            Logger::error('Unexpected error getting total events count: ' . $e->getMessage());
            return 0;
        }
    }


    /**
     * --------------------------------------------------------
     * Delete Event
     * --------------------------------------------------------
     * Safely deletes an event if and only if it has zero transactions
     * and zero collected revenue.
     * Operates within a single database transaction.
     *
     * @param string $eventId
     * @return array
     * --------------------------------------------------------
     */
    public function deleteEvent($eventId)
    {
        try {
            $eventId = trim((string) $eventId);

            // Validate event ID
            if (!Validator::eventId($eventId)) {
                return [
                    'success'    => false,
                    'message'    => 'Valid Event ID is required.',
                    'error_code' => 400
                ];
            }

            // Start atomic database transaction
            $this->db->beginTransaction();

            // 1. Fetch event record with lock
            $stmt = $this->db->prepare("SELECT id, event_id, event_name, department, webhook_url, is_active FROM events WHERE event_id = ? FOR UPDATE");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success'    => false,
                    'message'    => 'Event not found.',
                    'error_code' => 404
                ];
            }

            // 2. Safety Rule for Financial Data:
            // Check whether the event has ANY transactions (successful or otherwise) or collected revenue > 0
            $txStmt = $this->db->prepare("
                SELECT COUNT(*) as tx_count, COALESCE(SUM(amount), 0) as total_rev 
                FROM transactions 
                WHERE event_id = ?
            ");
            $txStmt->execute([$eventId]);
            $txSummary = $txStmt->fetch(\PDO::FETCH_ASSOC);

            $txCount  = (int)($txSummary['tx_count'] ?? 0);
            $totalRev = (float)($txSummary['total_rev'] ?? 0.0);

            if ($txCount > 0 || $totalRev > 0) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                Logger::warning("Blocked deletion of event #{$eventId} ('{$event['event_name']}'): has {$txCount} transactions and ₹{$totalRev} collected revenue.");
                return [
                    'success'      => false,
                    'message'      => "This event has payment records and can't be deleted. Deactivate it instead.",
                    'error_code'   => 400,
                    'has_payments' => true
                ];
            }

            // 3. Events with zero transactions can be deleted.
            // Cascade / clean up any event-specific references inside this transaction
            $delStmt = $this->db->prepare("DELETE FROM events WHERE event_id = ?");
            $delStmt->execute([$eventId]);

            // Commit atomic transaction
            $this->db->commit();

            Logger::info("Event #{$eventId} ('{$event['event_name']}') successfully deleted.");

            return [
                'success' => true,
                'message' => "Event '{$event['event_name']}' (#{$eventId}) was deleted successfully.",
                'data'    => [
                    'event_id'   => $eventId,
                    'event_name' => $event['event_name'],
                    'department' => $event['department'],
                    'is_active'  => (int)$event['is_active']
                ]
            ];

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("Database error deleting event #{$eventId}: " . $e->getMessage());
            return [
                'success'    => false,
                'message'    => 'A database error occurred while deleting the event.',
                'error_code' => 500
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error("Unexpected error deleting event #{$eventId}: " . $e->getMessage());
            return [
                'success'    => false,
                'message'    => 'An unexpected error occurred while deleting the event.',
                'error_code' => 500
            ];
        }
    }

    /**
     * --------------------------------------------------------
     * Get Event By ID
     * --------------------------------------------------------
     * Fetches any event record by its alphanumeric event_id
     * regardless of whether it is active or inactive.
     * --------------------------------------------------------
     */
    public function getEventById(string $eventId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM events WHERE event_id = ?");
            $stmt->execute([$eventId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: false;
        } catch (\PDOException $e) {
            Logger::error("Failed to fetch event by ID {$eventId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * --------------------------------------------------------
     * Rename Event
     * --------------------------------------------------------
     * Renames an event after validation.
     * --------------------------------------------------------
     */
    public function renameEvent(string $eventId, string $newEventName): array
    {
        $newEventName = trim($newEventName);
        if ($newEventName === '') {
            return ['success' => false, 'message' => 'Event name cannot be empty.'];
        }
        if (mb_strlen($newEventName) > 255) {
            return ['success' => false, 'message' => 'Event name exceeds 255 characters.'];
        }

        try {
            $event = $this->getEventById($eventId);
            if (!$event) {
                return ['success' => false, 'message' => 'Event not found.'];
            }

            $oldName = $event['event_name'];
            $stmt = $this->db->prepare("UPDATE events SET event_name = ? WHERE event_id = ?");
            $stmt->execute([$newEventName, $eventId]);

            Logger::info("Event #{$eventId} renamed from '{$oldName}' to '{$newEventName}'.");

            return [
                'success'    => true,
                'message'    => "Event renamed to '{$newEventName}' successfully.",
                'old_name'   => $oldName,
                'event_name' => $newEventName,
                'event_id'   => $eventId
            ];
        } catch (\PDOException $e) {
            Logger::error("Database error renaming event #{$eventId}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

?>