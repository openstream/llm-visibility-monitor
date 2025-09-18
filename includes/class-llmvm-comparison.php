<?php
/**
 * Comparison logic for LLM responses.
 *
 * This class handles the comparison of LLM responses with expected answers
 * using semantic similarity scoring.
 *
 * @package LLM_Visibility_Monitor
 * @since 0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comparison class for LLM responses.
 *
 * Handles semantic similarity scoring between actual responses and expected answers.
 *
 * @package LLM_Visibility_Monitor
 */
class LLMVM_Comparison {

	/**
	 * Compare a response with an expected answer and return a score.
	 *
	 * @param string $actual_response The actual response from the LLM.
	 * @param string $expected_answer The expected answer for comparison.
	 * @param string $original_prompt The original prompt that was sent.
	 * @return int|null The comparison score (1-10) or null if comparison fails.
	 */
	public static function compare_response( string $actual_response, string $expected_answer, string $original_prompt ) {
		// Get comparison model from settings
		$options = get_option( 'llmvm_options', [] );
		if ( ! is_array( $options ) ) {
			// Handle case where options are stored as JSON string
			if ( is_string( $options ) ) {
				$decoded = json_decode( $options, true );
				$options = is_array( $decoded ) ? $decoded : [];
			} else {
				$options = [];
			}
		}
		$comparison_model = isset( $options['comparison_model'] ) ? (string) $options['comparison_model'] : 'openai/gpt-4o-mini';
		$api_key = LLMVM_Cron::decrypt_api_key( $options['api_key'] ?? '' );
		
		// If no comparison model is set, return null
		if ( empty( $comparison_model ) ) {
			LLMVM_Logger::log( 'No comparison model set, skipping comparison' );
			return null;
		}
		
		// If expected answer is empty, return null
		if ( empty( trim( $expected_answer ) ) ) {
			LLMVM_Logger::log( 'No expected answer provided, skipping comparison' );
			return null;
		}
		
		// Prepare the comparison prompt
		$comparison_prompt = self::build_comparison_prompt( $actual_response, $expected_answer, $original_prompt );
		
		// Call the comparison model
		$comparison_result = self::call_comparison_model( $api_key, $comparison_model, $comparison_prompt );
		
		if ( $comparison_result === null ) {
			LLMVM_Logger::log( 'Comparison model call failed' );
			return array(
				'score' => null,
				'failed' => true,
				'reason' => 'Comparison model call failed'
			);
		}
		
		// Extract score from the response
		$score = self::extract_score_from_response( $comparison_result );
		
		if ( $score === null ) {
			LLMVM_Logger::log( 'Comparison score extraction failed', array(
				'comparison_model' => $comparison_model,
				'response' => $comparison_result,
				'expected_answer_length' => strlen( $expected_answer ),
				'actual_response_length' => strlen( $actual_response )
			) );
			return array(
				'score' => null,
				'failed' => true,
				'reason' => 'Could not extract valid score from comparison model response'
			);
		}
		
		LLMVM_Logger::log( 'Comparison completed', array(
			'comparison_model' => $comparison_model,
			'score' => $score,
			'expected_answer_length' => strlen( $expected_answer ),
			'actual_response_length' => strlen( $actual_response )
		) );
		
		return $score;
	}

	/**
	 * Build the comparison prompt for the LLM.
	 *
	 * @param string $actual_response The actual response from the LLM.
	 * @param string $expected_answer The expected answer for comparison.
	 * @param string $original_prompt The original prompt that was sent.
	 * @return string The formatted comparison prompt.
	 */
	private static function build_comparison_prompt( string $actual_response, string $expected_answer, string $original_prompt ): string {
		return sprintf(
			"Rate how well this response matches the expected answer. Respond with ONLY a number from 0-10, nothing else.\n\nResponse: %s\nExpected: %s\n\n0=not mentioned, 10=perfectly mentioned\n\nScore:",
			$actual_response,
			$expected_answer
		);
	}

	/**
	 * Call the comparison model via OpenRouter.
	 *
	 * @param string $model The model to use for comparison.
	 * @param string $prompt The comparison prompt.
	 * @return string|null The response from the model or null if failed.
	 */
	private static function call_comparison_model( string $api_key, string $model, string $prompt ): ?string {
		// Get OpenRouter client
		$openrouter_client = new LLMVM_OpenRouter_Client();
		
		// Call the model
		$response = $openrouter_client->query( $api_key, $prompt, $model );
		
		$score_text = $response['answer'] ?? '';
		$status = $response['status'] ?? 0;
		$error = $response['error'] ?? '';
		
		LLMVM_Logger::log( 'Comparison model response debug', array(
			'status' => $status,
			'error' => $error,
			'model' => $model,
			'score_text' => $score_text,
			'score_text_length' => strlen( $score_text ),
			'is_empty' => empty( $score_text ),
			'score_text_hex' => bin2hex( $score_text ),
			'trimmed_length' => strlen( trim( $score_text ) )
		) );
		
		if ( $status >= 400 || $score_text === '' || $score_text === null ) {
			LLMVM_Logger::log( 'Comparison model call failed', array(
				'status' => $status,
				'error' => $error,
				'model' => $model,
				'score_text' => $score_text,
				'score_text_length' => strlen( $score_text )
			) );
			return null;
		}
		
		return $score_text;
	}

	/**
	 * Extract the score from the model response.
	 *
	 * @param string $response The response from the comparison model.
	 * @return int|null The extracted score (0-10) or null if extraction fails.
	 */
	private static function extract_score_from_response( string $response ): ?int {
		// Clean the response
		$response = trim( $response );
		
		// Look for a number between 0 and 10 (improved regex to handle 10 properly)
		if ( preg_match( '/\b(10|[0-9])\b/', $response, $matches ) ) {
			$score = (int) $matches[1];
			
			// Ensure score is within valid range
			if ( $score >= 0 && $score <= 10 ) {
				return $score;
			}
		}
		
		// If no valid score found, try to extract any number and clamp it
		if ( preg_match( '/\b(\d+)\b/', $response, $matches ) ) {
			$score = (int) $matches[1];
			// Clamp to 0-10 range
			$score = max( 0, min( 10, $score ) );
			return $score;
		}
		
		// Try to find numbers at the beginning of the response (common pattern)
		if ( preg_match( '/^(\d+)/', $response, $matches ) ) {
			$score = (int) $matches[1];
			$score = max( 0, min( 10, $score ) );
			return $score;
		}
		
		// Try to find numbers at the end of the response (common pattern)
		if ( preg_match( '/(\d+)$/', $response, $matches ) ) {
			$score = (int) $matches[1];
			$score = max( 0, min( 10, $score ) );
			return $score;
		}
		
		LLMVM_Logger::log( 'Could not extract valid score from comparison response', array(
			'response' => $response,
			'response_length' => strlen( $response )
		) );
		
		return null;
	}

	/**
	 * Generate a prompt-level comparison summary from multiple model results.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param string $prompt_text The original prompt text.
	 * @param string $expected_answer The expected answer.
	 * @param array $results Array of results from different models.
	 * @return array|null Summary data or null if generation fails.
	 */
	public static function generate_prompt_summary( string $prompt_id, string $prompt_text, string $expected_answer, array $results ): ?array {
		if ( empty( $results ) || empty( $expected_answer ) ) {
			return null;
		}

		// Extract all answers and scores
		$answers = array();
		$scores = array();
		$models = array();

		foreach ( $results as $result ) {
			if ( ! empty( $result['answer'] ) ) {
				$answers[] = $result['answer'];
				$models[] = $result['model'];
				
				// Treat NULL scores as 0 to properly reflect failed comparisons
				if ( isset( $result['comparison_score'] ) && $result['comparison_score'] !== null ) {
					$scores[] = (int) $result['comparison_score'];
				} else {
					$scores[] = 0; // NULL or missing score = 0
				}
			}
		}

		if ( empty( $answers ) ) {
			return null;
		}

		// Calculate score statistics
		$score_stats = array();
		if ( ! empty( $scores ) ) {
			$score_stats = array(
				'average_score' => round( array_sum( $scores ) / count( $scores ), 1 ),
				'min_score'     => min( $scores ),
				'max_score'     => max( $scores ),
			);
		}

		// Generate comparison summary using LLM
		$summary = self::generate_comparison_summary( $prompt_text, $expected_answer, $answers, $models, $score_stats );

		return array(
			'summary'       => $summary,
			'average_score' => $score_stats['average_score'] ?? null,
			'min_score'     => $score_stats['min_score'] ?? null,
			'max_score'     => $score_stats['max_score'] ?? null,
			'total_models'  => count( $results ),
		);
	}

	/**
	 * Generate a comparison summary using an LLM.
	 *
	 * @param string $prompt_text The original prompt.
	 * @param string $expected_answer The expected answer.
	 * @param array $answers Array of answers from different models.
	 * @param array $models Array of model names.
	 * @param array $score_stats Score statistics.
	 * @return string|null The generated summary or null if generation fails.
	 */
	private static function generate_comparison_summary( string $prompt_text, string $expected_answer, array $answers, array $models, array $score_stats ): ?string {
		// Get comparison model from settings
		$options = get_option( 'llmvm_options', [] );
		if ( ! is_array( $options ) ) {
			// Handle case where options are stored as JSON string
			if ( is_string( $options ) ) {
				$decoded = json_decode( $options, true );
				$options = is_array( $decoded ) ? $decoded : [];
			} else {
				$options = [];
			}
		}
		$comparison_model = isset( $options['comparison_model'] ) ? (string) $options['comparison_model'] : 'openai/gpt-4o-mini';
		$api_key = LLMVM_Cron::decrypt_api_key( $options['api_key'] ?? '' );

		if ( empty( $api_key ) || empty( $comparison_model ) ) {
			return null;
		}

		// Build summary prompt
		$summary_prompt = self::build_summary_prompt( $prompt_text, $expected_answer, $answers, $models, $score_stats );

		// Call the comparison model
		$summary_result = self::call_comparison_model( $api_key, $comparison_model, $summary_prompt );

		if ( $summary_result === null ) {
			LLMVM_Logger::log( 'Summary generation failed', array(
				'prompt_text' => $prompt_text,
				'expected_answer' => $expected_answer,
				'total_answers' => count( $answers )
			) );
			return null;
		}

		return $summary_result;
	}

	/**
	 * Build the prompt for generating a comparison summary.
	 *
	 * @param string $prompt_text The original prompt.
	 * @param string $expected_answer The expected answer.
	 * @param array $answers Array of answers from different models.
	 * @param array $models Array of model names.
	 * @param array $score_stats Score statistics.
	 * @return string The summary prompt.
	 */
	private static function build_summary_prompt( string $prompt_text, string $expected_answer, array $answers, array $models, array $score_stats ): string {
		$summary_prompt = "Please provide a brief comparison summary of how well the following AI model responses match the expected answer.\n\n";
		$summary_prompt .= "**Original Prompt:** " . $prompt_text . "\n\n";
		$summary_prompt .= "**Expected Answer:** " . $expected_answer . "\n\n";

		if ( ! empty( $score_stats ) ) {
			$summary_prompt .= "**Score Statistics:** Average: " . $score_stats['average_score'] . "/10, Range: " . $score_stats['min_score'] . "-" . $score_stats['max_score'] . "/10\n\n";
		}

		$summary_prompt .= "**Model Responses:**\n";
		for ( $i = 0; $i < count( $answers ); $i++ ) {
			$model_name = isset( $models[ $i ] ) ? $models[ $i ] : 'Unknown Model';
			$summary_prompt .= ( $i + 1 ) . ". **" . $model_name . ":** " . $answers[ $i ] . "\n\n";
		}

		$summary_prompt .= "Please provide a concise 2-3 sentence summary focusing on:\n";
		$summary_prompt .= "- How many responses successfully mention the expected answer (celebrate any mentions as positive results)\n";
		$summary_prompt .= "- Overall assessment with a balanced, constructive tone\n";
		$summary_prompt .= "- If most responses mention the expected answer, highlight this as a strong performance\n";
		$summary_prompt .= "- If some responses don't mention it, note this neutrally without calling them 'poor' or 'bad'\n\n";
		$summary_prompt .= "Use an encouraging, professional tone that focuses on what worked well. Any mention of the expected answer should be considered a positive result.\n\n";
		$summary_prompt .= "Keep the summary professional and informative for email reports. **Important: Respond in the same language as the original prompt.**";

		return $summary_prompt;
	}


	/**
	 * Check if all models for a prompt have completed processing.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param array $expected_models Array of expected model names.
	 * @return bool True if all models are complete.
	 */
	public static function are_all_models_complete( string $prompt_id, array $expected_models ): bool {
		global $wpdb;

		$table_name = LLMVM_Database::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $expected_models ), '%s' ) );

		// Get the prompt text for this prompt ID from the stored prompts
		$prompts = get_option( 'llmvm_prompts', array() );
		$prompt_text = '';
		
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['id'] ) && $prompt['id'] === $prompt_id ) {
				$prompt_text = $prompt['text'] ?? '';
				break;
			}
		}

		if ( empty( $prompt_text ) ) {
			return false;
		}

		// Build LIKE conditions for model matching to handle suffixes like :online
		$model_conditions = array();
		foreach ( $expected_models as $model ) {
			$model_conditions[] = "model LIKE %s";
		}
		$model_like_placeholders = implode( ' OR ', $model_conditions );
		
		$like_params = array( $prompt_text );
		foreach ( $expected_models as $model ) {
			$like_params[] = $wpdb->esc_like( $model ) . '%';
		}

		$completed_models = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
			"SELECT DISTINCT model FROM {$table_name} WHERE prompt = %s AND ({$model_like_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string, placeholders are safely generated
			...$like_params
		) );

		return count( $completed_models ) === count( $expected_models );
	}

	/**
	 * Get all results for a specific prompt with the same expected answer.
	 *
	 * @param string $prompt_id The prompt ID.
	 * @param array $expected_models Array of expected model names.
	 * @param string $expected_answer The expected answer to filter by.
	 * @param string $batch_run_id Optional batch run ID to filter by current run only.
	 * @return array Array of results for the prompt.
	 */
	public static function get_prompt_results( string $prompt_id, array $expected_models, string $expected_answer = '', string $batch_run_id = '' ): array {
		global $wpdb;

		$table_name = LLMVM_Database::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $expected_models ), '%s' ) );

		// Get the prompt text for this prompt ID from the stored prompts
		$prompts = get_option( 'llmvm_prompts', array() );
		$prompt_text = '';
		
		foreach ( $prompts as $prompt ) {
			if ( isset( $prompt['id'] ) && $prompt['id'] === $prompt_id ) {
				$prompt_text = $prompt['text'] ?? '';
				break;
			}
		}

		if ( empty( $prompt_text ) ) {
			return array();
		}

		// Build LIKE conditions for model matching to handle suffixes like :online
		$model_conditions = array();
		foreach ( $expected_models as $model ) {
			$model_conditions[] = "model LIKE %s";
		}
		$model_like_placeholders = implode( ' OR ', $model_conditions );
		
		$like_params = array( $prompt_text );
		foreach ( $expected_models as $model ) {
			$like_params[] = $wpdb->esc_like( $model ) . '%';
		}

		// Add expected answer filter if provided
		$expected_answer_condition = '';
		if ( ! empty( $expected_answer ) ) {
			$expected_answer_condition = ' AND expected_answer = %s';
			$like_params[] = $expected_answer;
		}

		// Add batch run ID filter if provided (to limit to current run only)
		// Since results table doesn't have run_id, we'll use a time-based approach
		$batch_run_condition = '';
		if ( ! empty( $batch_run_id ) ) {
			// For batch runs, we'll get the job creation time from the queue table
			// and filter results within a reasonable time window (e.g., 5 minutes)
			$queue_table = $wpdb->prefix . 'llmvm_queue';
			$job_time = $wpdb->get_var( $wpdb->prepare(
				"SELECT created_at FROM {$queue_table} WHERE JSON_EXTRACT(job_data, '$.batch_run_id') = %s ORDER BY created_at ASC LIMIT 1",
				$batch_run_id
			) );
			
			if ( $job_time ) {
				// Filter results within 5 minutes of the batch start time
				$batch_run_condition = ' AND created_at >= %s AND created_at <= DATE_ADD(%s, INTERVAL 5 MINUTE)';
				$like_params[] = $job_time;
				$like_params[] = $job_time;
			}
		}

		$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table operations require direct queries.
			"SELECT * FROM {$table_name} WHERE prompt = %s AND ({$model_like_placeholders}){$expected_answer_condition}{$batch_run_condition} ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table_name() returns constant string, placeholders are safely generated
			...$like_params
		), ARRAY_A );

		return $results ? $results : array();
	}
}
