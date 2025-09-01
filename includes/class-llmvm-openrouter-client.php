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
            $answer = sprintf( 'Stub: %s', wp_strip_all_tags( $prompt ) ?: '' );
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
                'Referer'       => home_url( '/' ) ?: '',
                'X-Title'       => 'LLM Visibility Monitor',
            ],
            'body'    => wp_json_encode( $body ) ?: '',
            'timeout' => 60,
        ];

        // Only log the model being used, not the full request
        $resp = wp_remote_post( $url, $args );
        if ( is_wp_error( $resp ) ) {
            LLMVM_Logger::log( 'OpenRouter error', [ 'error' => $resp->get_error_message() ] );
            return [ 'model' => $model, 'answer' => '', 'status' => 0, 'error' => $resp->get_error_message() ];
        }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp ) ?: '';
        LLMVM_Logger::log( 'OpenRouter response', [ 'status' => $code ] );

        // Clean up the raw body preview for better logging - only log if there's meaningful content
        $raw_preview = substr( (string) $body, 0, 300 ) ?: '';
        if ( ! empty( trim( $raw_preview ) ) ) {
            // Try to parse the full JSON response first
            $full_json = json_decode( (string) $body, true );
            if ( is_array( $full_json ) ) {
                $clean_preview = '';
                if ( isset( $full_json['id'] ) ) {
                    $clean_preview .= 'id:' . $full_json['id'] . ' ';
                }
                if ( isset( $full_json['provider'] ) ) {
                    $clean_preview .= 'provider:' . $full_json['provider'] . ' ';
                }
                if ( isset( $full_json['model'] ) ) {
                    $clean_preview .= 'model:' . $full_json['model'] . ' ';
                }
                if ( isset( $full_json['choices'][0]['finish_reason'] ) ) {
                    $clean_preview .= 'finish_reason:' . $full_json['choices'][0]['finish_reason'] . ' ';
                }
                $clean_preview = trim( $clean_preview );
            } else {
                // Fallback: clean up the raw preview by removing JSON syntax
                $clean_preview = preg_replace( '/[{}[\]"]/', '', $raw_preview );
                $clean_preview = preg_replace( '/\s+/', ' ', trim( $clean_preview ) );
            }
            
            // Only log if we have meaningful content
            if ( ! empty( trim( $clean_preview ) ) ) {
                LLMVM_Logger::log( 'OpenRouter response details', [ 'details' => $clean_preview ] );
            }
        }
        $json = json_decode( (string) $body, true );
        if ( ! is_array( $json ) ) {
            return [ 'model' => $model, 'answer' => '', 'status' => (int) $code, 'error' => 'Invalid JSON from API' ];
        }
        if ( $code < 200 || $code >= 300 ) {
            $msg = (string) ( $json['error']['message'] ?? '' );
            // Provide more specific error messages for common issues
            if ( $code === 401 ) {
                if ( strpos( $msg, 'No auth credentials found' ) !== false ) {
                    $msg = 'OpenRouter service issue - try again later (status 401)';
                } else {
                    $msg = 'API key authentication failed - check your OpenRouter API key';
                }
            } elseif ( $code === 402 ) {
                $msg = 'OpenRouter credits insufficient - add credits to your account';
            } elseif ( $code >= 500 ) {
                $msg = 'OpenRouter server error - try again later (status ' . $code . ')';
            }
            return [ 'model' => $model, 'answer' => '', 'status' => (int) $code, 'error' => $msg ];
        }
        $answer = $json['choices'][0]['message']['content'] ?? '';
        // Only log a short preview of the answer to reduce log verbosity
        $preview = substr( (string) $answer, 0, 80 ) ?: '';
        if ( ! empty( trim( $preview ) ) ) {
            // Remove line breaks and extra spaces for cleaner logging
            $preview = preg_replace( '/\s+/', ' ', trim( $preview ) );
            LLMVM_Logger::log( 'Answer preview', [ 'preview' => $preview ] );
        }
        return [ 'model' => $model, 'answer' => (string) $answer, 'status' => (int) $code, 'error' => '' ];
    }
}


