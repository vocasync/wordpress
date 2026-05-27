<?php

declare( strict_types=1 );

namespace VocaSync;

use VocaSync\Api\Client;
use VocaSync\Content\Extractor;
use VocaSync\Content\WordWrapper;
use VocaSync\Editor\Editor;
use VocaSync\Generation\Artifact;
use VocaSync\Generation\Orchestrator;
use VocaSync\Render\PlayerRenderer;
use VocaSync\Rest\Controller;
use VocaSync\Settings\Settings;
use VocaSync\Webhook\Deliveries;
use VocaSync\Webhook\Receiver;

/**
 * Central wiring for the plugin. Instantiates services in dependency order and
 * registers their WordPress hooks.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Settings $settings;
	private Client $client;
	private Artifact $artifact;
	private Orchestrator $orchestrator;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->settings     = new Settings();
		$this->client       = new Client( $this->settings );
		$this->artifact     = new Artifact();
		$this->orchestrator = new Orchestrator( $this->client, $this->artifact, new Extractor(), $this->settings );

		// Services that attach hooks.
		$this->settings->register();
		$this->orchestrator->register();
		( new Receiver( $this->settings, new Deliveries(), $this->orchestrator ) )->register();
		( new Controller( $this->orchestrator, $this->artifact, $this->settings, $this->client ) )->register();
		( new PlayerRenderer( $this->artifact, $this->settings, new WordWrapper(), new Extractor() ) )->register();
		( new Editor( $this->settings, $this->artifact ) )->register();

		$this->maybe_schedule_reconciliation();

		load_plugin_textdomain( 'vocasync', false, dirname( plugin_basename( VOCASYNC_PLUGIN_FILE ) ) . '/languages' );
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function orchestrator(): Orchestrator {
		return $this->orchestrator;
	}

	/**
	 * Ensure the recurring reconciliation sweep is scheduled. Acts as the
	 * polling fallback / safety net for jobs whose webhook never arrived.
	 */
	private function maybe_schedule_reconciliation(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( as_has_scheduled_action( Orchestrator::HOOK_RECONCILE ) ) {
			return;
		}
		as_schedule_recurring_action(
			time() + 300,
			300, // every 5 minutes
			Orchestrator::HOOK_RECONCILE,
			array(),
			'vocasync'
		);
	}

	public static function activate(): void {
		( new Deliveries() )->create_table();
	}

	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Orchestrator::HOOK_RECONCILE );
		}
	}
}
