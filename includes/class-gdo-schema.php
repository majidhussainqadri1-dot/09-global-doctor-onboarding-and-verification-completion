<?php
defined( 'ABSPATH' ) || exit;

final class GDO_Schema {
	public static function table( $name ) {
		global $wpdb;
		$allowed = array(
			'applications','evidence','transitions','consents','access_log','appeals','outbox','rate_limits',
			'reviewer_profiles','risk_signals','quality_samples','access_grants','metrics',
		);
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'gdo_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$engine = 'ENGINE=InnoDB ' . $c;
		$apps = self::table( 'applications' );
		$evidence = self::table( 'evidence' );
		$transitions = self::table( 'transitions' );
		$consents = self::table( 'consents' );
		$access = self::table( 'access_log' );
		$appeals = self::table( 'appeals' );
		$outbox = self::table( 'outbox' );
		$rates = self::table( 'rate_limits' );
		$reviewers = self::table( 'reviewer_profiles' );
		$risks = self::table( 'risk_signals' );
		$quality = self::table( 'quality_samples' );
		$grants = self::table( 'access_grants' );
		$metrics = self::table( 'metrics' );

		dbDelta( "CREATE TABLE {$apps} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NULL,
			version int(10) unsigned NOT NULL DEFAULT 1,
			application_type varchar(40) NOT NULL DEFAULT 'homeopathic_doctor',
			jurisdiction varchar(16) NOT NULL DEFAULT '',
			preferred_language varchar(20) NOT NULL DEFAULT 'en-US',
			state varchar(40) NOT NULL DEFAULT 'draft',
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			profile_json longtext NOT NULL,
			profile_fingerprint char(64) NOT NULL,
			identity_fingerprint char(64) NOT NULL DEFAULT '',
			approved_snapshot_json longtext NULL,
			approved_fingerprint char(64) NULL,
			submission_hash char(64) NULL,
			policy_version varchar(40) NOT NULL DEFAULT '',
			terms_version varchar(60) NOT NULL DEFAULT '',
			assigned_reviewer_id bigint(20) unsigned NULL,
			recommender_id bigint(20) unsigned NULL,
			finalizer_id bigint(20) unsigned NULL,
			recommended_decision varchar(20) NULL,
			recommendation_reason text NULL,
			recommendation_at datetime NULL,
			submitted_at datetime NULL,
			reviewed_at datetime NULL,
			decision_at datetime NULL,
			verified_until datetime NULL,
			draft_expires_at datetime NULL,
			more_info_due_at datetime NULL,
			renewal_open_at datetime NULL,
			revoked_at datetime NULL,
			withdrawn_at datetime NULL,
			retention_until datetime NULL,
			legal_hold tinyint(1) unsigned NOT NULL DEFAULT 0,
			consent_version varchar(40) NULL,
			claim_version bigint(20) unsigned NOT NULL DEFAULT 0,
			claim_status varchar(20) NOT NULL DEFAULT 'none',
			claim_ack_at datetime NULL,
			claim_last_error text NULL,
			renewed_from_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY application_uuid (application_uuid),
			UNIQUE KEY user_version (user_id,version),
			KEY user_state (user_id,state),
			KEY assigned_state (assigned_reviewer_id,state),
			KEY jurisdiction_state (jurisdiction,state),
			KEY identity_fingerprint (identity_fingerprint),
			KEY verified_until (verified_until),
			KEY draft_expires_at (draft_expires_at),
			KEY retention_until (retention_until)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$evidence} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			document_type varchar(30) NOT NULL,
			purpose_code varchar(60) NOT NULL DEFAULT 'professional_verification',
			version int(10) unsigned NOT NULL DEFAULT 1,
			status varchar(30) NOT NULL DEFAULT 'quarantine',
			original_name varchar(255) NOT NULL,
			mime_type varchar(100) NOT NULL,
			file_size bigint(20) unsigned NOT NULL,
			source_sha256 char(64) NOT NULL DEFAULT '',
			storage_name varchar(180) NOT NULL,
			ciphertext_sha256 char(64) NOT NULL,
			content_hmac char(64) NOT NULL,
			key_id varchar(80) NOT NULL,
			envelope_version varchar(10) NOT NULL,
			malware_status varchar(30) NOT NULL DEFAULT 'not_scanned',
			scan_provider varchar(80) NULL,
			scan_reference varchar(160) NULL,
			metadata_removed tinyint(1) unsigned NOT NULL DEFAULT 0,
			pixel_width int(10) unsigned NULL,
			pixel_height int(10) unsigned NULL,
			checklist_json longtext NULL,
			findings_json longtext NULL,
			review_note text NULL,
			registry_result varchar(40) NULL,
			registry_source varchar(255) NULL,
			reviewer_id bigint(20) unsigned NULL,
			reviewed_at datetime NULL,
			validity_from date NULL,
			validity_until date NULL,
			expires_at datetime NULL,
			retention_state varchar(30) NOT NULL DEFAULT 'active',
			deletion_proof char(64) NULL,
			deleted_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY app_type_version (application_id,document_type,version),
			UNIQUE KEY storage_name (storage_name),
			KEY source_sha256 (source_sha256),
			KEY user_status (user_id,status),
			KEY retention_state (retention_state),
			KEY application_active (application_id,retention_state,deleted_at)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$transitions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NULL,
			from_state varchar(40) NOT NULL,
			to_state varchar(40) NOT NULL,
			reason_code varchar(80) NOT NULL,
			reason_text text NOT NULL,
			trace_id char(36) NULL,
			event_hash char(64) NOT NULL,
			previous_hash char(64) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_hash (event_hash),
			KEY application_id (application_id),
			KEY actor_id (actor_id),
			KEY created_at (created_at)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$consents} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			consent_version varchar(40) NOT NULL,
			wording_hash char(64) NOT NULL,
			purpose text NOT NULL,
			retention_notice text NOT NULL,
			lawful_basis varchar(60) NOT NULL DEFAULT 'consent_and_professional_verification',
			accepted_at datetime NOT NULL,
			withdrawn_at datetime NULL,
			evidence_hash char(64) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY application_consent (application_id,consent_version),
			KEY user_id (user_id)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$access} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			evidence_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			purpose_code varchar(80) NOT NULL,
			purpose_hash char(64) NOT NULL,
			result varchar(30) NOT NULL,
			actor_digest char(64) NOT NULL,
			trace_id char(36) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY evidence_id (evidence_id),
			KEY reviewer_created (reviewer_id,created_at),
			KEY application_id (application_id)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$appeals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			appeal_uuid char(36) NOT NULL,
			application_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			source_state varchar(40) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'open',
			reason text NOT NULL,
			grounds_json longtext NULL,
			evidence_json longtext NULL,
			assigned_reviewer_id bigint(20) unsigned NULL,
			deadline_at datetime NULL,
			resolution text NULL,
			decision varchar(40) NULL,
			resolver_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			resolved_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY appeal_uuid (appeal_uuid),
			KEY application_status (application_id,status),
			KEY assigned_status (assigned_reviewer_id,status),
			KEY user_id (user_id)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$outbox} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			event_type varchar(80) NOT NULL,
			recipient_user_id bigint(20) unsigned NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int(10) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			available_at datetime NOT NULL,
			delivered_at datetime NULL,
			dead_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			KEY status_available (status,available_at),
			KEY event_type (event_type)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$rates} (
			bucket_hash char(64) NOT NULL,
			window_started bigint(20) unsigned NOT NULL,
			hits int(10) unsigned NOT NULL DEFAULT 0,
			expires_at bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$reviewers} (
			user_id bigint(20) unsigned NOT NULL,
			jurisdictions_json longtext NOT NULL,
			languages_json longtext NOT NULL,
			max_open_cases int(10) unsigned NOT NULL DEFAULT 25,
			status varchar(20) NOT NULL DEFAULT 'active',
			qualification_checked_at datetime NULL,
			access_reviewed_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (user_id),
			KEY status (status)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$risks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			signal_type varchar(50) NOT NULL,
			severity varchar(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			related_digest char(64) NULL,
			resolution varchar(30) NULL,
			resolution_reason text NULL,
			resolver_id bigint(20) unsigned NULL,
			resolved_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY application_status (application_id,status),
			KEY severity_status (severity,status)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$quality} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			application_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			original_decision varchar(30) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			source varchar(30) NOT NULL DEFAULT 'automatic',
			outcome varchar(30) NULL,
			reason text NULL,
			auditor_id bigint(20) unsigned NULL,
			completed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY reviewer_status (reviewer_id,status),
			KEY application_id (application_id)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$grants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grant_hash char(64) NOT NULL,
			application_id bigint(20) unsigned NOT NULL,
			evidence_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			purpose_code varchar(80) NOT NULL,
			session_digest char(64) NOT NULL,
			mode varchar(20) NOT NULL DEFAULT 'view',
			expires_at datetime NOT NULL,
			used_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY grant_hash (grant_hash),
			KEY reviewer_expires (reviewer_id,expires_at),
			KEY evidence_id (evidence_id)
		) {$engine};" );

		dbDelta( "CREATE TABLE {$metrics} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			metric_name varchar(80) NOT NULL,
			metric_value decimal(20,6) NOT NULL DEFAULT 0,
			dimensions_json longtext NULL,
			observed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY name_observed (metric_name,observed_at)
		) {$engine};" );
	}
}
