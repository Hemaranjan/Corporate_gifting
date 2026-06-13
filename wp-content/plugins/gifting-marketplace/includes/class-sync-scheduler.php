<?php
/**
 * Giftelier Sync Hub — WP Cron Scheduler
 *
 * Manages periodic scheduling of connection syncs via WP-Cron.
 * Each active connection can be scheduled independently on its own interval.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GM_Sync_Scheduler
 *
 * Registers custom WP-Cron schedules, schedules/unschedules individual
 * sync connections, and runs the sync when the cron hook fires.
 */
class GM_Sync_Scheduler {

	/**
	 * The WP-Cron action hook name.
	 */
	const HOOK = 'gm_sync_periodic';

	/**
	 * Named intervals and their durations in seconds.
	 */
	const SCHEDULES = array(
		'15min'  => 900,
		'30min'  => 1800,
		'1hour'  => 3600,
		'6hours' => 21600,
		'daily'  => 86400,
	);

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
		add_action( self::HOOK,       array( $this, 'run_scheduled_sync'  ) );
	}

	// =========================================================================
	// Custom WP-Cron schedules
	// =========================================================================

	/**
	 * Inject custom intervals into WP-Cron's schedule list.
	 *
	 * @param array $schedules Existing WP-Cron schedules.
	 * @return array
	 */
	public function add_custom_schedules( array $schedules ): array {
		$schedules['gm_15min'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes', 'gifting-marketplace' ),
		);
		$schedules['gm_30min'] = array(
			'interval' => 1800,
			'display'  => __( 'Every 30 Minutes', 'gifting-marketplace' ),
		);
		$schedules['gm_1hour'] = array(
			'interval' => 3600,
			'display'  => __( 'Every Hour', 'gifting-marketplace' ),
		);
		$schedules['gm_6hours'] = array(
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'gifting-marketplace' ),
		);

		return $schedules;
	}

	// =========================================================================
	// Schedule management
	// =========================================================================

	/**
	 * Schedule or reschedule a connection's periodic sync.
	 *
	 * If the connection already has a scheduled event on the same interval,
	 * no action is taken. If the interval has changed, the old event is cleared
	 * and a new one is registered immediately.
	 *
	 * @param int    $connection_id The connection ID to schedule.
	 * @param string $interval      A WP-Cron recurrence slug (e.g. 'gm_1hour').
	 */
	public static function schedule_connection( int $connection_id, string $interval ): void {
		$args      = array( $connection_id );
		$timestamp = wp_next_scheduled( self::HOOK, $args );

		if ( $timestamp ) {
			$details = wp_get_schedule( self::HOOK, $args );
			if ( $details === $interval ) {
				return;
			}
			wp_clear_scheduled_hook( self::HOOK, $args );
		}

		wp_schedule_event( time(), $interval, self::HOOK, $args );
	}

	/**
	 * Remove all scheduled events for the given connection.
	 *
	 * @param int $connection_id The connection ID to unschedule.
	 */
	public static function unschedule_connection( int $connection_id ): void {
		$args = array( $connection_id );
		wp_clear_scheduled_hook( self::HOOK, $args );
	}

	// =========================================================================
	// Cron callback
	// =========================================================================

	/**
	 * Execute a scheduled sync for a single connection.
	 *
	 * Fired by the gm_sync_periodic cron hook. Loads the connection record,
	 * verifies it is still active, delegates to GM_Sync_Connection::run_sync(),
	 * and updates the last_sync / next_sync timestamps.
	 *
	 * @param int $connection_id The connection to sync.
	 */
	public function run_scheduled_sync( int $connection_id ): void {
		global $wpdb;

		$table      = $wpdb->prefix . 'gm_sync_connections';
		$connection = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $connection_id )
		);

		if ( ! $connection ) {
			return;
		}

		if ( 'active' !== $connection->status ) {
			return;
		}

		if ( class_exists( 'GM_Sync_Connection' ) ) {
			GM_Sync_Connection::run_sync( $connection_id );
		}

		$args      = array( $connection_id );
		$next_ts   = wp_next_scheduled( self::HOOK, $args );
		$next_sync = $next_ts ? gmdate( 'Y-m-d H:i:s', $next_ts ) : null;

		$wpdb->update(
			$table,
			array(
				'last_sync' => current_time( 'mysql', true ),
				'next_sync' => $next_sync,
			),
			array( 'id' => $connection_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	// =========================================================================
	// Utility
	// =========================================================================

	/**
	 * Return a human-readable string describing when the next sync will run.
	 *
	 * @param int $connection_id The connection ID to check.
	 * @return string|null Human-readable time string (e.g. "in 12 minutes"), or null if not scheduled.
	 */
	public static function get_next_sync( int $connection_id ): ?string {
		$args      = array( $connection_id );
		$timestamp = wp_next_scheduled( self::HOOK, $args );

		if ( ! $timestamp ) {
			return null;
		}

		$diff = $timestamp - time();

		if ( $diff <= 0 ) {
			return __( 'imminently', 'gifting-marketplace' );
		}

		if ( $diff < 60 ) {
			return sprintf(
				/* translators: %d: number of seconds */
				_n( 'in %d second', 'in %d seconds', $diff, 'gifting-marketplace' ),
				$diff
			);
		}

		if ( $diff < 3600 ) {
			$minutes = (int) round( $diff / 60 );
			return sprintf(
				/* translators: %d: number of minutes */
				_n( 'in %d minute', 'in %d minutes', $minutes, 'gifting-marketplace' ),
				$minutes
			);
		}

		if ( $diff < 86400 ) {
			$hours = (int) round( $diff / 3600 );
			return sprintf(
				/* translators: %d: number of hours */
				_n( 'in %d hour', 'in %d hours', $hours, 'gifting-marketplace' ),
				$hours
			);
		}

		$days = (int) round( $diff / 86400 );
		return sprintf(
			/* translators: %d: number of days */
			_n( 'in %d day', 'in %d days', $days, 'gifting-marketplace' ),
			$days
		);
	}
}
