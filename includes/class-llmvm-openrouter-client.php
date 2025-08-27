<?php
/**
 * Stubbed OpenRouter API client.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_OpenRouter_Client {

    /**
     * Query the API (stubbed).
     *
     * @param string $api_key API key from settings.
     * @param string $prompt  Prompt to send.
     * @return array{model:string,answer:string}
     */
    public function query( string $api_key, string $prompt ): array {
        // This is a stub. Replace with real HTTP request to OpenRouter when ready.
        // For now, we simulate a deterministic response for repeatability.
        $model  = 'openrouter/stub-model-v1';
        $answer = sprintf( 'Stub response for prompt: "%s"', wp_strip_all_tags( $prompt ) );
        return [ 'model' => $model, 'answer' => $answer ];
    }
}


