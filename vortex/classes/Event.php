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
                             start_date, end_date, is_active
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
        $endDate
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


            // Generate a unique event ID.
            $eventId = EventIdGenerator::generate(
                $eventName,
                $startDate
            );


            // SQL query to insert the event.
            $query = "INSERT INTO events
                    (event_id, event_name, department, start_date, end_date)
                    VALUES (?, ?, ?, ?, ?)";


            // Prepare the query.
            $statement = $this->db->prepare($query);


            // Execute the query.
            $statement->execute([
                $eventId,
                $eventName,
                $department,
                $startDate,
                $endDate
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
                    'event_id' => $eventId
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
                             start_date, end_date, is_active, created_at
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
}

?>