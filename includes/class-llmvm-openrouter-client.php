<?php
/**
 * Stubbed OpenRouter API client.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LLMVM_OpenRouter_Client {

    /**
     * Query the OpenRouter API.
     *
     * @param string $api_key API key from settings.
     * @param string $prompt  Prompt to send.
     * @param string $model   Model id (e.g. 'openrouter/stub-model-v1' for tests or 'openai/gpt-4o-mini').
     * @return array{model:string,answer:string,status:int,error:string}
     */
    public function query( string $api_key, string $prompt, string $model ): array {
        $model = $model ?: 'openrouter/stub-model-v1';

        if ( 'openrouter/stub-model-v1' === $model ) {
            $answer = sprintf( 'Stub: %s', wp_strip_all_tags( $prompt ) );
            return [ 'model' => $model, 'answer' => $answer, 'status' => 200, 'error' => '' ];
        }

        // Real request to OpenRouter.
        $url  = 'https://openrouter.ai/api/v1/chat/completions';
        $body = [
            'model'    => $model,
            'messages' => [ [ 'role' => 'user', 'content' => $prompt ] ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Referer'       => home_url( '/' ),
                'X-Title'       => 'LLM Visibility Monitor',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ];

        LLMVM_Logger::log( 'OpenRouter request', [ 'model' => $model ] );
        $resp = wp_remote_post( $url, $args );
        if ( is_wp_error( $resp ) ) {
            LLMVM_Logger::log( 'OpenRouter error', [ 'error' => $resp->get_error_message() ] );
            return [ 'model' => $model, 'answer' => '', 'status' => 0, 'error' => $resp->get_error_message() ];
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        LLMVM_Logger::log( 'OpenRouter response', [ 'status' => $code ] );

        LLMVM_Logger::log( 'OpenRouter raw body preview', [ 'preview' => substr( (string) $body, 0, 300 ) ] );
        $json = json_decode( (string) $body, true );
        if ( ! is_array( $json ) ) {
            return [ 'model' => $model, 'answer' => '', 'status' => (int) $code, 'error' => 'Invalid JSON from API' ];
        }
        if ( $code < 200 || $code >= 300 ) {
            $msg = (string) ( $json['error']['message'] ?? '' );
            return [ 'model' => $model, 'answer' => '', 'status' => (int) $code, 'error' => $msg ];
        }
        $answer = $json['choices'][0]['message']['content'] ?? '';
        LLMVM_Logger::log( 'OpenRouter parsed answer preview', [ 'preview' => substr( (string) $answer, 0, 200 ) ] );
        return [ 'model' => $model, 'answer' => (string) $answer, 'status' => (int) $code, 'error' => '' ];
    }
}


