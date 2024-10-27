<?php
/**
 * Defines end points for all rest calls
 **/

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
* Register endpoints to refresh log
*/
add_action(
	'rest_api_init',
	function () {

		register_rest_route(
			'ai_chatbot_easy_integration/v1',
			'/keywordsearch',
			array(
				'methods'             => 'GET',
				'callback'            => 'ai_chatbot_easy_integration_log_search_rest',
				'permission_callback' => function () {
					$settings = ai_chatbot_easy_integration_get_settings();
					if ( is_array( $settings ) && isset( $settings['access_capability'] ) ) {
						return current_user_can( $settings['access_capability'] );
					}
				},
			)
		);

		register_rest_route(
			'ai_chatbot_easy_integration/v1',
			'/processchat',
			array(
				'methods'  => 'GET',
				'callback' => 'ai_chatbot_easy_integration_process_chat_rest',
			)
		);
	}
);

/**
 * Process chat request
 */
function ai_chatbot_easy_integration_process_chat_rest() {
	check_ajax_referer( 'wp_rest', '_wpnonce' );
	$settings = ai_chatbot_easy_integration_get_settings();

	if ( isset( $_GET['message'] ) && isset( $_GET['sessionid'] ) && isset( $_GET['faq'] ) && isset( $_GET['agent'] ) && isset( $_GET['openai_action'] ) ) {
		$message       = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		$sessionid     = sanitize_text_field( wp_unslash( $_GET['sessionid'] ) );
		$faq           = sanitize_text_field( wp_unslash( $_GET['faq'] ) );
		$agent         = sanitize_text_field( wp_unslash( $_GET['agent'] ) );
		$openai_action = sanitize_text_field( wp_unslash( $_GET['openai_action'] ) );
	} else {
		die();
	}

	ai_chatbot_easy_integration_insert_log_message( $message, $sessionid, '', $sessionid, '', 'from_user' );

	// live chat session is active so exit.
	if ( '' !== $agent ) {
		die();
	}

	$response = __( "I'm afraid that I don't know how to answer that? Try asking it a different way.", 'ai-chatbot-easy-integration' );

	if ( 'texttospeech' != $openai_action ) {
		echo '<p><strong>';
		esc_html_e( 'Agent: ', 'ai-chatbot-easy-integration' );
		echo '</strong>';
	}

	// process request type.
	if ( 'texttospeech' == $openai_action ) {
		$response = ai_chatbot_easy_integration_process_open_ai_complex_commands( $message, $openai_action );
	} elseif ( 'moderation' == $openai_action ) {
		$response = ai_chatbot_easy_integration_process_open_ai_complex_commands( $message, $openai_action );
	} elseif ( 'generations' == $openai_action ) {
		$response = ai_chatbot_easy_integration_process_open_ai_complex_commands( $message, $openai_action );
	} elseif ( isset( $settings['open_ai_actions'] ) && 'true' == $settings['open_ai_actions'] && isset( $settings['openai_api_key'] ) && '' != $settings['openai_api_key'] && 'vision' == $openai_action ) {
		$response = ai_chatbot_easy_integration_open_ai_request( '', $message, 'vision' );
	} elseif ( 'true' == $faq ) {
		$searchterm = ai_chatbot_easy_integration_strip_common_terms( $message );
		$response   = ai_chatbot_easy_integration_lookup_wordpress_search_results( $searchterm, $faq );
	} elseif ( isset( $settings['open_ai_search'] ) && 'true' == $settings['open_ai_search'] && isset( $settings['openai_api_key'] ) && '' != $settings['openai_api_key'] ) {
		$response = ai_chatbot_easy_integration_open_ai_request( '', $message );
	} elseif ( function_exists( 'ai_chatbot_easy_integration_custom_calls_websearch_process' ) ) {
		$searchterm = ai_chatbot_easy_integration_strip_common_terms( $message );
		$response   = ai_chatbot_easy_integration_custom_calls_websearch_process( $searchterm );
	} else {
			$response = ai_chatbot_easy_integration_lookup_wordpress_search_results( $searchterm, $faq );
	}

	// fall back to open ai if no results are found.
	if ( strstr( $response, 'know how to answer that' ) && isset( $settings['openai_api_key'] ) && '' != $settings['openai_api_key'] ) {
		$response = ai_chatbot_easy_integration_open_ai_request( '', $message );
	}

	ai_chatbot_easy_integration_insert_log_message( $response, $sessionid, '', $sessionid, '', 'from_chatbot' );
	$trustedtags = ai_chatbot_easy_integration_get_trusted_tags_array();
	echo wp_kses( $response, $trustedtags );
	echo '</p>';
	die();
}

/**
 * Log search
 */
function ai_chatbot_easy_integration_log_search_rest() {

	check_ajax_referer( 'wp_rest', '_wpnonce' );

	if ( ! isset( $_GET['keyword'] ) ) {
		return;
	} else {
		$keyword = sanitize_text_field( wp_unslash( $_GET['keyword'] ) );
		$results = ai_chatbot_easy_integration_display_log_results( $keyword );

		echo wp_kses_post( $results );
	}
}
