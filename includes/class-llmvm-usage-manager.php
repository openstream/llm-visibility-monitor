<?php
/**
 * Usage management for LLM Visibility Monitor.
 *
 * @package LLM_Visibility_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Usage Manager class for LLM Visibility Monitor.
 *
 * Handles usage limits, plan checking, and usage tracking.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Usage_Manager {

	/**
	 * Get usage limits for a user based on their plan.
	 */
	public static function get_user_limits( int $user_id ): array {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return self::get_free_limits();
		}

		// Check for unlimited plan (administrators)
		if ( $user->has_cap( 'llmvm_unlimited_plan' ) ) {
			return self::get_unlimited_limits();
		}

		// Check for pro plan
		if ( $user->has_cap( 'llmvm_pro_plan' ) ) {
			return self::get_pro_limits();
		}

		// Default to free plan
		return self::get_free_limits();
	}

	/**
	 * Get free plan limits.
	 */
	public static function get_free_limits(): array {
		$options = get_option( 'llmvm_options', array() );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		
		return array(
			'max_prompts' => isset( $options['free_max_prompts'] ) ? (int) $options['free_max_prompts'] : 3,
			'max_models_per_prompt' => isset( $options['free_max_models'] ) ? (int) $options['free_max_models'] : 3,
			'max_runs_per_month' => isset( $options['free_max_runs'] ) ? (int) $options['free_max_runs'] : 30,
			'plan_name' => 'Free'
		);
	}

	/**
	 * Get pro plan limits.
	 */
	public static function get_pro_limits(): array {
		$options = get_option( 'llmvm_options', array() );
		// Ensure we have a proper array to prevent PHP 8.1 deprecation warnings.
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		
		return array(
			'max_prompts' => isset( $options['pro_max_prompts'] ) ? (int) $options['pro_max_prompts'] : 10,
			'max_models_per_prompt' => isset( $options['pro_max_models'] ) ? (int) $options['pro_max_models'] : 6,
			'max_runs_per_month' => isset( $options['pro_max_runs'] ) ? (int) $options['pro_max_runs'] : 300,
			'plan_name' => 'Pro'
		);
	}

	/**
	 * Get unlimited plan limits.
	 */
	public static function get_unlimited_limits(): array {
		return array(
			'max_prompts' => 999999,
			'max_models_per_prompt' => 999999,
			'max_runs_per_month' => 999999,
			'plan_name' => 'Unlimited'
		);
	}

	/**
	 * Check if user can add more prompts.
	 */
	public static function can_add_prompt( int $user_id ): bool {
		$limits = self::get_user_limits( $user_id );
		$usage = LLMVM_Database::get_user_usage( $user_id );
		
		// Count current prompts for this user
		$prompts = get_option( 'llmvm_prompts', array() );
		$user_prompts = 0;
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['user_id'] ) && (int) $prompt['user_id'] === $user_id ) {
				$user_prompts++;
			}
		}
		
		return $user_prompts < $limits['max_prompts'];
	}

	/**
	 * Check if user can add more models to a prompt.
	 */
	public static function can_add_models_to_prompt( int $user_id, int $current_model_count ): bool {
		$limits = self::get_user_limits( $user_id );
		return $current_model_count <= $limits['max_models_per_prompt'];
	}

	/**
	 * Check if user can run prompts (has remaining runs).
	 */
	public static function can_run_prompts( int $user_id, int $runs_needed = 1 ): bool {
		$limits = self::get_user_limits( $user_id );
		$usage = LLMVM_Database::get_user_usage( $user_id );
		
		return ( $usage['runs_used'] + $runs_needed ) <= $limits['max_runs_per_month'];
	}

	/**
	 * Get remaining runs for user this month.
	 */
	public static function get_remaining_runs( int $user_id ): int {
		$limits = self::get_user_limits( $user_id );
		$usage = LLMVM_Database::get_user_usage( $user_id );
		
		return max( 0, $limits['max_runs_per_month'] - $usage['runs_used'] );
	}

	/**
	 * Get remaining prompts for user.
	 */
	public static function get_remaining_prompts( int $user_id ): int {
		$limits = self::get_user_limits( $user_id );
		
		// Count current prompts for this user
		$prompts = get_option( 'llmvm_prompts', array() );
		$user_prompts = 0;
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['user_id'] ) && (int) $prompt['user_id'] === $user_id ) {
				$user_prompts++;
			}
		}
		
		return max( 0, $limits['max_prompts'] - $user_prompts );
	}

	/**
	 * Calculate total runs needed for a set of prompts.
	 */
	public static function calculate_runs_needed( array $prompts ): int {
		$total_runs = 0;
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['models'] ) && is_array( $prompt['models'] ) ) {
				$total_runs += count( $prompt['models'] );
			} else {
				$total_runs += 1; // Fallback for single model
			}
		}
		return $total_runs;
	}

	/**
	 * Get user's current usage summary.
	 */
	public static function get_usage_summary( int $user_id ): array {
		$limits = self::get_user_limits( $user_id );
		$usage = LLMVM_Database::get_user_usage( $user_id );
		
		// Count current prompts for this user
		$prompts = get_option( 'llmvm_prompts', array() );
		$user_prompts = 0;
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['user_id'] ) && (int) $prompt['user_id'] === $user_id ) {
				$user_prompts++;
			}
		}
		
		
		return array(
			'plan_name' => $limits['plan_name'],
			'prompts' => array(
				'used' => $user_prompts,
				'limit' => $limits['max_prompts'],
				'remaining' => max( 0, $limits['max_prompts'] - $user_prompts )
			),
			'runs' => array(
				'used' => $usage['runs_used'],
				'limit' => $limits['max_runs_per_month'],
				'remaining' => max( 0, $limits['max_runs_per_month'] - $usage['runs_used'] )
			),
			'models_per_prompt_limit' => $limits['max_models_per_prompt']
		);
	}
}
