<?php
/**
 * CHATBOT FEATURES
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Append footer content
 */
function ai_chatbot_easy_integration_append() {
	$settings = ai_chatbot_easy_integration_get_settings();

	// exit if pro version is active or if in admin area!
	if ( is_admin() || defined( 'AI_CHATBOT_EASY_INTEGRATION_PRO_VERSION_NUM' ) ) {
		return;
	}

	$chatbot = '';

	if ( isset( $settings['display_pages'] ) && is_array( $settings['display_pages'] ) && count( $settings['display_pages'] ) > 0
	&& is_singular() && ! in_array( get_the_ID(), $settings['display_pages'] ) ) {
		return;
	}

	if ( ( ! isset( $settings['display_chatbot'] ) || 'false' == $settings['display_chatbot'] ) && isset( $settings['display_basic_chatbot'] ) && 'true' == $settings['display_basic_chatbot'] ) {

			$chatbot = ai_chatbot_easy_integration_chatwindow();

			$trustedtags = ai_chatbot_easy_integration_get_trusted_tags_array();
			echo wp_kses( $chatbot, $trustedtags );

	} elseif ( isset( $settings['display_chatbot'] ) && 'true' == $settings['display_chatbot'] ) {
		$chatbot = $settings['webchat_embed_code'];
		if ( strstr( $chatbot, 'window.watsonAssistantChatOptions' ) ) {
			echo str_replace( '=&gt;', '=>', wp_kses( $chatbot, array( 'script' => array() ) ) );
			return;
		}
	}
}

add_filter( 'wp_footer', 'ai_chatbot_easy_integration_append' );

/**
 * Register session for local chat window
 */
function ai_chatbot_easy_integration_register_session() {
	if ( ! session_id() ) {
		session_start();
	}
}
add_action( 'init', 'ai_chatbot_easy_integration_register_session' );

/**
 *  Process webhook
 */
function ai_chatbot_easy_integration_api_webhook_process() {

	$settings = ai_chatbot_easy_integration_get_settings();

	// exit if not a secure chatbot callback!
	if ( ! isset( $_REQUEST['chatbotcall'] ) || ! isset( $settings['webhook_hash'] ) ) {
		return;
	}

	if ( ! function_exists( 'wp_verify_nonce' ) || ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ) ) ) {
		// nonce not required.
	}

	// log security issues on web hook call!
	if ( $settings['webhook_hash'] !== $_REQUEST['chatbotcall'] ) {
		$response = esc_attr( __( 'Invalid call - webhook_hash does not match chatbotcall parameter.', 'ai-chatbot-easy-integration' ) );
		if ( function_exists( 'SimpleLogger' ) ) {
			SimpleLogger()->info( $response );
		}

		return;
	}

	// get the parmeters that were passed by Watson Assistant!
	// if you sanitize the whole input you will break the code. Please review the code, sanitation takes place in the lines that follow.
	$body = file_get_contents( 'php://input' );

	header( 'Content-Type: application/json; charset=utf-8' );

	$body = json_decode( $body );

	$response = esc_attr( __( 'I didn\'t understand. Could you try asking it a different way.', 'ai-chatbot-easy-integration' ) );

	if ( isset( $_POST ) && isset( $settings['log_chat_messages'] ) && 'true' === $settings['log_chat_messages'] ) {

		// process pre webhook!
		if ( '' !== $body && isset( $body->payload->input->text ) ) {

			$message = esc_attr( $body->payload->input->text );

			$smsnumber = '';

			if ( isset( $body->payload->context->global->system->user_id ) ) {
				$userid = esc_attr( $body->payload->context->global->system->user_id );
			} elseif ( isset( $body->payload->user_id ) ) {
				$userid = esc_attr( $body->payload->user_id );
			} else {
				$userid = '';
			}

			if ( isset( $body->payload->context->global->session_id ) ) {
				$sessionid = esc_attr( $body->payload->context->global->session_id );
			} else {
				$sessionid = '';
			}

			if ( '' !== $message && '' !== $userid ) {
				ai_chatbot_easy_integration_insert_log_message( $message, $sessionid, $smsnumber, $userid, '', 'from_user' );
			} elseif ( function_exists( 'SimpleLogger' ) ) {
					SimpleLogger()->info( 'No response received from Watson.' );
			}
		}

		// process post webhook!
		if ( '' !== $body && isset( $body->payload->output->generic[0]->text ) ) {

			$message = esc_attr( $body->payload->output->generic[0]->text );

			if ( isset( $body->payload->context->global->session_id ) ) {
				$sessionid = esc_attr( $body->payload->context->global->session_id );
			} else {
				$sessionid = '';
			}

			if ( isset( $body->payload->context->global->system->user_id ) ) {
				$userid = esc_attr( $body->payload->context->global->system->user_id );
			} elseif ( isset( $body->payload->user_id ) ) {
				$userid = esc_attr( $body->payload->user_id );
			} else {
				$userid = '';
			}

			if ( '' !== $message ) {

				ai_chatbot_easy_integration_insert_log_message( $message, $sessionid, '', $userid, '', 'from_chatbot' );
			} elseif ( function_exists( 'SimpleLogger' ) ) {
					SimpleLogger()->info( 'No response received from Watson.' );
			}

			die();
		}
	}

	// process specific requests!
	if ( '' !== $body && isset( $body->callType ) ) {

		$calltype = esc_attr( $body->callType );

		// insert user defined calls!
		$response = ai_chatbot_easy_integration_custom_calls( $calltype, $body );

		if ( '' === $response ) {
			$response = esc_attr( __( 'I didn\'t understand. Try asking it a different way.', 'ai-chatbot-easy-integration' ) );
		}
	}

	// sanitation takes place as the response is formatted! Escaping it at this point will damage the code.
	if ( ! strstr( $response, 'Response' ) ) {
		echo '{"Response":[{"message":"' . wp_kses_post( $response ) . '"}]}';
	} else {
		echo wp_kses_post( $response );
	}

	die();
}
add_action( 'init', 'ai_chatbot_easy_integration_api_webhook_process' );

/**
 * Handle custom watson calls
 *
 * @param  mixed $calltype the type of call to make.
 * @param  mixed $body object containing the searchterm or phrase entered.
 */
function ai_chatbot_easy_integration_custom_calls( $calltype, $body ) {
	$response = '';

	if ( '' == $response ) {
		$response = apply_filters( 'ai_chatbot_easy_integration_custom_calls', $response, $calltype, $body );
	}

	return $response;
}

/**
 * Save message
 *
 * @param  mixed $message the message that was received.
 * @param  mixed $sessionid the sessionid.
 * @param  mixed $smsnumber SMS number if applicable.
 * @param  mixed $userid the users id.
 * @param  mixed $agent assigned agent id.
 * @param  mixed $from who posted the message, bot or user.
 * @return void
 */
function ai_chatbot_easy_integration_insert_log_message( $message, $sessionid, $smsnumber, $userid, $agent, $from ) {
	global $wpdb;

	if ( '' !== $sessionid ) {
		$session = $sessionid;
	} elseif ( '' !== $userid ) {
		$session = $userid;
	} else {
		$session = '';
	}

	if ( '' === $session ) {
		return;
	}

	if ( ! ai_chatbot_easy_integration_check_log_message( $userid, $session ) ) {
		$wpdb->query( $wpdb->prepare( 'INSERT INTO  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs (topic, entrydate, sessionid,smsnumber,userid,agentassigned,sessionstatus) values(%s, now(), %s,%s,%s,%s,%s)', $message, $sessionid, $smsnumber, $userid, $agent, 'active' ) );
	} elseif ( '' !== $userid ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs SET lastaction = %s, sessionid=%s WHERE userid=%s', '', $sessionid, $userid ) );
	}

	// update messages!
	ai_chatbot_easy_integration_update_messages( $session, $message, $from );
}

/**
 * Update message values
 *
 * @param  mixed $session the sessionid.
 * @param  mixed $value the message value.
 * @param  mixed $from who posted the message, bot or user.
 * @return void
 */
function ai_chatbot_easy_integration_update_messages( $session, $value, $from ) {
	global $wpdb;
		$value = $value . ':|:' . time() . ':|:' . $from;

		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs where sessionid = %s or userid = %s', $session, $session ), ARRAY_A );
	if ( count( $results ) ) {
		foreach ( $results as $row ) {
				$messages = unserialize( $row['messages'] );
		}
	}
	if ( isset( $messages ) && ! is_array( $messages ) ) {
		$messages = array( $value );
	} else {
		$messages[] = $value;
	}

	$message = serialize( $messages );

	// update messages!
	$wpdb->query( $wpdb->prepare( 'UPDATE  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs SET messages=%s WHERE userid=%s or sessionid = %s', $message, $session, $session ) );
}

/**
 * Only save one log message per user
 *
 * @param  mixed $userid the user id.
 * @param  mixed $sessionid the sessionid.
 */
function ai_chatbot_easy_integration_check_log_message( $userid, $sessionid ) {
	global $wpdb;
	$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs where userid = %s or smsnumber = %s or sessionid = %s or userid = %s or smsnumber = %s or sessionid = %s', $userid, $userid, $userid, $sessionid, $sessionid, $sessionid ) );
	if ( count( $results ) > 0 ) {
		return 1;
	} else {
		return 0;
	}
}

/**
 * Display reports
 **/
function ai_chatbot_easy_integration_reports() {
	echo '<h1>';
	esc_attr_e( 'Reports', 'ai-chatbot-easy-integration' );
	echo '<h1>';
	echo '<p>';
	esc_attr_e( 'The reports section is still being developed. Check back after each release to find new reporting features.', 'ai-chatbot-easy-integration' );
	echo '</p>';
	echo '<p>';
	$url = wp_nonce_url( '/wp-admin/admin.php?page=ai-chatbot-easy-integration-log&export_chat_log=1' );
	echo '<a href="' . esc_url( $url ) . '" class="ai_chatbot_easy_integration-btn">';
	esc_attr_e( 'EXPORT CHAT LOG', 'ai-chatbot-easy-integration' );
	echo '</a>';
	echo '</p>';

	echo '<h2>';
	esc_attr_e( 'Top Topics', 'ai-chatbot-easy-integration' );
	echo '</h2>';
	echo '<p>';
	esc_attr_e( 'These are the most common topics that your chatbot received.', 'ai-chatbot-easy-integration' );
	echo '</p>';
	ai_chatbot_easy_integration_report_generation( 'toptopics' );

	echo '<h2>';
	esc_attr_e( 'Unanswered Topics', 'ai-chatbot-easy-integration' );
	echo '</h2>';
	echo '<p>';
	esc_attr_e( 'Unanswered topics are questions that your chatbot did not know how to answer.', 'ai-chatbot-easy-integration' );
	/*echo ' <a href="https://www.aichatboteasyintegration.com/how-to/training-watson-assistant-chatbot-to-correctly-answer-questions/" target="_blank" title="' . esc_attr( __( 'opens a new tab', 'ai-chatbot-easy-integration' ) ) . '">';
	esc_attr_e( 'LEARN HOW TO TRAIN YOUR CHATBOT', 'ai-chatbot-easy-integration' );
	echo '</a>';*/
	echo '</p>';
	ai_chatbot_easy_integration_report_generation( 'unansweredtopics' );
}

/**
 * Generate report
 *
 * @param  mixed $report the type of report to generate.
 * @return void
 */
function ai_chatbot_easy_integration_report_generation( $report ) {
	global $wpdb;

	if ( 'toptopics' === $report ) {
		$results = $wpdb->get_results( 'SELECT topic, count(topic) as number FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs group by topic order by count(topic) desc limit 1', ARRAY_A );

		if ( count( $results ) > 0 ) {
			echo '<p>';
			foreach ( $results as $row ) {
					echo esc_attr( $row['topic'] ) . ' (' . esc_attr( $row['number'] ) . ')<br>';
			}
			echo '</p>';
		}
	}

	if ( 'unansweredtopics' === $report ) {
		$results = $wpdb->get_results( 'SELECT messages FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs', ARRAY_A );

		if ( count( $results ) > 0 ) {
			echo '<p>';
			foreach ( $results as $row ) {

				$messages = unserialize( $row['messages'] );

				if ( is_array( $messages ) ) {
					foreach ( $messages as $key => $value ) {
						if ( strstr( $value, 'know how to answer that?' ) ) {
							$messageparts = explode( ':|:', $messages[ $key - 1 ] );
							echo esc_attr( $messageparts[0] ) . '<br><a>';
						}
					}
				}
			}
			echo '</p>';
		}
	}
}
/***
Display chat log
 ****/
function ai_chatbot_easy_integration_log_results() {
	global $ai_chatbot_easy_integration_notice;

	// display chat bot.
	ai_chatbot_easy_integration_append();

	// add pro plugin content!
	apply_filters( 'ai_chatbot_easy_integration_log_results', '' );
	echo '<div class="ai_chatbot_easy_integration_logo"><a href="https://www.aichatboteasyintegration.com/"><img src="' . esc_url( AI_CHATBOT_EASY_INTEGRATION_URL ) . 'logo.png" alt="' . esc_attr( __( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ) ) . '"></a>';

	echo '<p class="ai_chatbot_easy_integration_logo_btns">';
	$url = wp_nonce_url( '/wp-admin/admin.php?page=ai-chatbot-easy-integration-log&purgelog=1' );
	echo '<a href="' . esc_attr( $url ) . '" class="ai_chatbot_easy_integration-btn">';
	esc_attr_e( 'CLEAR LOG', 'ai-chatbot-easy-integration' );
	echo '</a>';
	echo '<a href="#" class="ai_chatbot_easy_integration-btn ai_chatbot_easy_integration-showchat" aria-expanded="false" title="' . esc_attr( __( 'opens a dialog', 'ai-chatbot-easy-integration' ) ) . '" role="button">';
	esc_attr_e( 'SHOW CHAT WINDOW', 'ai-chatbot-easy-integration' );
	echo '</a>';
	echo '<a href="/wp-admin/admin.php?page=ai-chatbot-easy-integration-reports" class="ai_chatbot_easy_integration-btn">';
	esc_attr_e( 'REPORTS', 'ai-chatbot-easy-integration' );
	echo '</a>';

	echo '</p>';
	echo '</div>';
	echo '<form id="ai-chatbot-easy-integration-searchform" aria-label="' . esc_attr( __( 'Search for Message Content', 'ai-chatbot-easy-integration' ) ) . '">
<label for="ai-chatbot-easy-integration-keyword">' . esc_attr( __( 'Search: ', 'ai-chatbot-easy-integration' ) ) . '<input type="text" name="keyword" id="ai-chatbot-easy-integration-keyword"></label><input type="submit" value="' . esc_attr( __( 'Find', 'ai-chatbot-easy-integration' ) ) . '" id="ai-chatbot-easy-integration-keyword-search">
</form>';

	if ( isset( $ai_chatbot_easy_integration_notice ) && '' !== $ai_chatbot_easy_integration_notice ) {
		echo '<p class="ai_chatbot_easy_integration-success">';
		echo esc_attr( $ai_chatbot_easy_integration_notice );
		echo '</p>';
	}

	echo '<div class="ai-chatbot-easy-integration-chatwindowmarketing">';
	esc_attr_e( 'Live chat is a pro version feature. Upgrade to the pro version to activate live chat options.', 'ai-chatbot-easy-integration' );
	echo '</div>';

	ai_chatbot_easy_integration_display_log_results();
}
/**
 * Log results
 *
 * @param  mixed $keyword a keyword to filter results.
 * @return void
 */
function ai_chatbot_easy_integration_display_log_results( $keyword = '' ) {
	global $wpdb;

	if ( '' === $keyword ) {
		$results = $wpdb->get_results( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs order by id desc', ARRAY_A );
	} else {
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs where messages like %s order by id desc', '%' . $keyword . '%' ), ARRAY_A );
	}

	if ( '' === $keyword ) {
		echo '<div id="ai_chatbot_easy_integration_log" arai-live="polite" aria-label="' . esc_attr( __( 'chat log', 'ai-chatbot-easy-integration' ) ) . '">';
	}

	foreach ( $results as $row ) {

		echo '<div class="ai_chatbot_easy_integration_log_entry">';
		if ( function_exists( 'ai_chatbot_easy_integration_pro_check_create_ticket_btn' ) && '' !== $row['sessionid'] ) {
			ai_chatbot_easy_integration_pro_check_create_ticket_btn( esc_attr( $row['sessionid'] ) );
		}
		echo esc_attr( __( 'DATE: ', 'ai-chatbot-easy-integration' ) );
		echo esc_attr( $row['entrydate'] );
		echo '<br>';
		echo esc_attr( __( 'TOPIC: ', 'ai-chatbot-easy-integration' ) );
		echo esc_attr( $row['topic'] );
		if ( '' !== $row['username'] ) {
			echo '<br>';
			echo esc_attr( __( 'NAME: ', 'ai-chatbot-easy-integration' ) );
			echo esc_attr( $row['username'] );
		}
		if ( '' !== $row['phone'] ) {
			echo '<br>';
			echo esc_attr( __( 'PHONE: ', 'ai-chatbot-easy-integration' ) );
			echo esc_attr( $row['phone'] );
		}
		if ( '' !== $row['email'] ) {
			echo '<br>';
			echo esc_attr( __( 'EMAIL: ', 'ai-chatbot-easy-integration' ) );
			echo esc_attr( $row['email'] );
		}
		echo '<p>';
		if ( '' !== $row['notes'] ) {
			echo '<a href="#" role="button" class="ai-chatbot-easy-integration-shownotes ai-chatbot-easy-integration-show-btn" data-id="chatnotes' . esc_attr( $row['id'] ) . '"  aria-expanded="false">';
			echo esc_attr( __( 'VIEW CHAT NOTES', 'ai-chatbot-easy-integration' ) );
			echo '</a> ';
		}
		if ( '' !== $row['messages'] ) {
			echo ' <a href="#" role="button" class="ai-chatbot-easy-integration-showlog ai-chatbot-easy-integration-show-btn" data-id="chatlog' . esc_attr( $row['id'] ) . '"  aria-expanded="false">';
			echo esc_attr( __( 'VIEW CHAT TRANSCRIPT', 'ai-chatbot-easy-integration' ) );
			echo '</a>';
		}
		echo '</p>';

		if ( '' !== $row['notes'] ) {
			echo '<div id="chatnotes' . esc_attr( $row['id'] ) . '" class="ai-chatbot-easy-integration-full-chatnotes hidden">';

			if ( '' !== $row['errors'] ) {
				esc_attr( __( 'An error occured was detected during an SMS call: ', 'ai-chatbot-easy-integration' ) ) . esc_attr( $row['errors'] );
			}

			echo '<p>' . esc_attr( $row['notes'] ) . '</p>';
			echo '</div>';
		}

		if ( '' !== $row['messages'] ) {
			echo '<div id="chatlog' . esc_attr( $row['id'] ) . '" class="ai-chatbot-easy-integration-full-chatlog hidden">';

			if ( '' !== $row['errors'] ) {
				esc_attr( __( 'An error occured was detected during an SMS call: ', 'ai-chatbot-easy-integration' ) ) . esc_attr( $row['errors'] );
			}

			$messages = unserialize( $row['messages'] );

			if ( is_array( $messages ) ) {
				$messages = array_reverse( $messages );
				foreach ( $messages as $key => $value ) {

					if ( strstr( $value, ':|:' ) ) {
						$messageparts = explode( ':|:', $value );

						if ( is_numeric( $messageparts[1] ) ) {
								$date = wp_date( 'Y-m-d H:i:s', $messageparts[1] );
						}
						$messagecontent = strip_tags( htmlspecialchars_decode( stripslashes( $messageparts[0] ) ), '<br>' );
						$messagecontent = str_replace( $keyword, '<span class="ai-chatbot-keywordmatches">' . esc_attr( $keyword ) . '</span>', wp_kses_post( $messagecontent ) );
						echo '<p>' . esc_attr( strtoupper( str_replace( '_', ' ', $messageparts[2] ) ) ) . '<br> ' . esc_attr( $date ) . '<br> ' . wp_kses_post( $messagecontent ) . '</p>';
					}
				}
			}
			echo '</div>';
		}

		if ( '' === $keyword ) {
			echo '</div>';
		}
	}
}


/**
 * Display chat log
 *
 * @param  mixed $frequency email frequency.
 */
function ai_chatbot_easy_integration_display_email_log( $frequency ) {
	global $wpdb;

	$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs where lognoticestatus = %d AND entrydate >= NOW() - INTERVAL %d HOUR order by id desc', 0, $frequency ), ARRAY_A );

	$log = '';
	if ( is_array( $results ) && count( $results ) > 0 ) {
		$log .= '<p>';
		$log .= esc_attr( __( 'The following chat messages were received by Watson Assistant over the past ', 'ai-chatbot-easy-integration' ) );
		$log .= esc_attr( $frequency );
		$log .= esc_attr( __( ' hours:', 'ai-chatbot-easy-integration' ) );
		$log .= '</p>';

		foreach ( $results as $row ) {
			$log .= '<div class="ai_chatbot_easy_integration_log_entry">';
			$log .= '<p>';
			$log .= esc_attr( __( 'DATE: ', 'ai-chatbot-easy-integration' ) );
			$log .= esc_attr( $row['entrydate'] );
			$log .= '</p>';
			$log .= '<p>';
			$log .= esc_attr( __( 'Topic: ', 'ai-chatbot-easy-integration' ) );
			$log .= esc_attr( $row['topic'] );
			$log .= '</p>';
			$log .= '</div>';
		}

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs set lognoticestatus = %d where id  = %d ', 1, $row['id'] ) );
	}
	return $log;
}

/**
 * Clear log
 *
 * @param  mixed $frequency purge frequency.
 * @return void
 */
function ai_chatbot_easy_integration_purge_log( $frequency = '' ) {
	global $ai_chatbot_easy_integration_notice;

	if ( ! isset( $_SERVER['REQUEST_URI'] ) && ! wp_doing_cron() ) {
		return;
	} else {
		$requesturi             = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$_SERVER['REQUEST_URI'] = str_replace( 'purgelog', '', $requesturi );

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ) ) ) {
			return;
		}

		if ( ! is_admin() || ! strstr( $requesturi, 'page=ai-chatbot-easy-integration' ) || ! isset( $_REQUEST['purgelog'] ) ) {
			return;
		}
	}

	global $wpdb;
	if ( '' === $frequency ) {
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs' );

		// purge files!
		if ( function_exists( 'ai_chatbot_easy_integration_pro_purge_files' ) ) {
			ai_chatbot_easy_integration_pro_purge_files();
		}
	} elseif ( '0' !== $frequency ) {

		// purge files!
		if ( function_exists( 'ai_chatbot_easy_integration_pro_purge_files' ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT sessionid FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs where entrydate <= NOW() - INTERVAL %d DAY', $frequency ), ARRAY_A );
			foreach ( $results as $row ) {
				ai_chatbot_easy_integration_pro_purge_files( $row['sessionid'] );
			}
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs WHERE entrydate <= NOW() - INTERVAL %d DAY', $frequency ) );
	}

	// log activity!
	if ( function_exists( 'SimpleLogger' ) ) {
		$notice  = __( 'Log purge ran with frequency set to: ', 'ai-chatbot-easy-integration' );
		$notice .= esc_attr( $frequency );
		SimpleLogger()->info( $notice );
	}

	$ai_chatbot_easy_integration_notice = __( 'LOG CLEARED', 'ai-chatbot-easy-integration' );
}
add_action( 'admin_init', 'ai_chatbot_easy_integration_purge_log' );

/*****************************************
 *  Process webhook
 */
function ai_chatbot_easy_integration_export_logs() {

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	} else {
		$requesturi = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ) ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['export_chat_log'] ) || ! strstr( $requesturi, 'page=ai-chatbot-easy-integration-log' ) ) {
			return;
		}
	}

	// output headers so that the file is downloaded rather than displayed!
	header( 'Content-type: text/csv' );
	header( 'Content-Disposition: attachment; filename=csvexport.csv' );

	// do not cache the file!
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	// create a file pointer connected to the output stream!
	$file = fopen( 'php://output', 'w' );

	global $wpdb;
		$results = $wpdb->get_results( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs' );

		$results = $wpdb->get_results( 'SELECT * FROM  ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs', ARRAY_A );

	if ( 0 === count( $results ) ) {
		return;
	}

	fputcsv( $file, array( 'sms number', 'status', 'userid', 'name', 'email', 'phone', 'agent assigned', 'agents notes', 'from', 'date', 'message' ) );

	// output each row of the data!
	foreach ( $results as $row ) {

		if ( is_numeric( $row['agentassigned'] ) ) {
			$available_agent = get_user_by( 'ID', $row['agentassigned'] );
			if ( '' !== $available_agent->user_login ) {
				$nickname = $available_agent->user_login;
			}
		}

		$messages = unserialize( $row['messages'] );

		if ( is_array( $messages ) ) {
			$messages = array_reverse( $messages );
			foreach ( $messages as $key => $value ) {
				if ( strstr( $value, ':|:' ) ) {
					$messageparts = explode( ':|:', $value );

					$date = wp_date( 'Y-m-d H:i:s', $messageparts[1] );

					$data[] = array(
						$row['smsnumber'],
						$row['status'],
						$row['userid'],
						$row['username'],
						$row['email'],
						$row['phone'],
						$nickname,
						$row['notes'],
						esc_attr( strtoupper( str_replace( '_', ' ', $messageparts[2] ) ) ),
						esc_attr( $date ),
						strip_tags( htmlspecialchars_decode( stripslashes( $messageparts[0] ) ) ),

					);
				}
			}
		}
	}
	// output each row of the data!
	foreach ( $data as $row2 ) {
		fputcsv( $file, $row2 );
	}
	unset( $data );

	exit();
}
add_action( 'init', 'ai_chatbot_easy_integration_export_logs' );

/**
 * Display chat window
 */
function ai_chatbot_easy_integration_chatwindow() {
	$settings = ai_chatbot_easy_integration_get_settings();

	if ( ! isset( $settings['chat_bot_greeting'] ) ) {
		$settings['chat_bot_greeting'] = __( 'Hello!', 'ai-chatbot-easy-integration' );
	}

	$code = '<button class="ai_chatbot_easy_integration_open_button" title="' . __( 'Opens Dialog', 'ai-chatbot-easy-integration' ) . '" ><i class="fas fa-comment" aria-hidden="true"></i><span class="screen-reader-text">' . __( 'Open Chat', 'ai-chatbot-easy-integration' ) . '</span></button>
	
	<div class="ai_chatbot_easy_integration_form_popup" id="ai_chatbot_easy_integration_chatdialog" role="dialog" aria-label="' . __( 'Chat Dialog', 'ai-chatbot-easy-integration' ) . '" aria-modal="true">
	<a href="https://www.aichatboteasyintegration.com/"><img class="ai_chatbot_easy_integration_icon" src="' . esc_url( AI_CHATBOT_EASY_INTEGRATION_URL ) . 'logo-header.png" alt="' . __( 'AI ChatBot Easy Integration', 'ai-chatbot-easy-integration' ) . '"></a><h2>' . esc_attr( $settings['chat_bot_greeting'] ) . '</h2>
	<button type="button" class="ai_chatbot_easy_integration_cancel " title="' . __( 'Close', 'ai-chatbot-easy-integration' ) . '"><i class="far fa-window-close" aria-hidden="true"></i></button>
	<form action="#" class="ai_chatbot_easy_integration_form_container">
	<input type="hidden" name="sessionid" id="ai_chatbot_easy_integration_sessionid" value="' . esc_attr( session_id() ) . '">
	<div id="ai_chatbot_easy_integration_chat_status_message" aria-live="polite" class="screen-reader-text"></div>
	<div id="ai_chatbot_easy_integration_chat_dialog_container" ><p>' . __( 'Hello! How can I assist you today?', 'ai-chatbot-easy-integration' ) . '</p></div>
	  <h2 class="screen-reader-text">' . __( 'Chat', 'ai-chatbot-easy-integration' ) . '</h2>
  
	
	  <input name="msg" type="text" id="ai_chatbot_easy_integration_msg" required aria-label="' . __( 'Message', 'ai-chatbot-easy-integration' ) . '" placeholder="' . __( 'Enter a question here...', 'ai-chatbot-easy-integration' ) . '"> ';

	if ( isset( $settings['open_ai_actions'] ) && 'true' == $settings['open_ai_actions'] ) {
		$code .= '<select name="ai_chatbot_easy_integration_openai_action" id="ai_chatbot_easy_integration_openai_action" aria-label="' . __( 'Choose an openAI Action', 'ai-chatbot-easy-integration' ) . '">
	  <option value="">' . __( 'Choose an OpenAI Ability', 'ai-chatbot-easy-integration' ) . '</option>
	  <option value="texttospeech">' . __( 'Text to Speech (include text in message box)', 'ai-chatbot-easy-integration' ) . '</option>
	  <option value="generations">' . __( 'Generate an image (include image description in message box)', 'ai-chatbot-easy-integration' ) . '</option>
	  <option value="vision">' . __( 'What is in this Image (include url in message box)', 'ai-chatbot-easy-integration' ) . '</option>
	  <option value="moderation">' . __( 'Moderate this text for threatning or harmful speech (include text in message box)', 'ai-chatbot-easy-integration' ) . '</option>
	  </select>';
	}

		$code .= '<button class="btn ai_chatbot_easy_integration_send" data-faq="false" data-agent="">' . __( 'Send', 'ai-chatbot-easy-integration' ) . '</button>';

		$query = new WP_Query(
			array(
				'post_type' => 'faq-post',
			)
		);

	if ( '' != $settings['contact_form'] ) {
		$code .= ' <a href="' . esc_url( $settings['contact_form'] ) . '" class="btn ai_chatbot_easy_integration_contact">' . __( 'Leave Message', 'ai-chatbot-easy-integration' ) . '</a>';
	}

	if ( count( $query->posts ) > 0 ) {
		$code .= ' <button class="btn ai_chatbot_easy_integration_send ai_chatbot_easy_integration_faq" data-faq="true" data-agent="">' . __( 'View FAQs', 'ai-chatbot-easy-integration' ) . '</button>';
	}

	$code  = apply_filters( 'ai_chatbot_easy_integration_chatwindow', $code );
	$code .= '</form>
  </div>
';

	return $code;
}

/**
 * Strip common words
 *
 * @param text $phrase
 * @return void
 */
function ai_chatbot_easy_integration_strip_common_terms( $phrase ) {
	$commonwords = 'much,by,with,about,until,or,have,whom,are,can,could,will,would,did,should,may,might,who,when,why,where,a,an,and,i,it,is,do,does,for,from,go,how,the,what,but,because,while,oh,she,for,this,you,find,information,on';

	$commonwords = explode( ',', $commonwords );

	$phrase = strtolower( $phrase );

	$phrase = preg_replace( '#[[:punct:]]#', '', $phrase );

	$search = explode( ' ', $phrase );

	foreach ( $search as $value ) {
		if ( ! in_array( $value, $commonwords ) ) {
			$query[] = $value;
		}
	}
	if ( is_array( $query ) ) {
		$phrase = implode( ' ', $query );
	}

		return $phrase;
}

/**
 * WordPress search
 *
 * @param string $searchterm the keyword to search for.
 **/
function ai_chatbot_easy_integration_lookup_wordpress_search_results( $searchterm, $faq = 0 ) {
	$response = __( "I'm afraid that I don't know how to answer that? Try asking it a different way.", 'ai-chatbot-easy-integration' );

	$settings = ai_chatbot_easy_integration_get_settings();

	$number_results = $settings['web_search_results'];

	if ( '' == $number_results ) {
		$number_results = 3;
	}

	if ( 'true' == $faq ) {
		$most_requested_query = new WP_Query(
			array(
				'post_type'      => 'faq-post',
				'tag'            => 'most-requested',
				'posts_per_page' => $number_results,
			)
		);
		$query                = new WP_Query(
			array(
				'post_type' => 'faq-post',
			)
		);
	} else {
		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'posts_per_page' => $number_results,
				's'              => $searchterm,
			)
		);
	}

	if ( count( $query->posts ) > 0 ) {
		if ( 'true' == $faq ) {
			$response = '';
			if ( count( $most_requested_query->posts ) > 0 ) {
				$response = __( 'Here are a few FAQs:', 'ai-chatbot-easy-integration' ) . '<br>';
				foreach ( $query->posts as $post ) {
					$response .= esc_attr( get_the_title( $post->ID ) ) . ' - ' . esc_url( get_the_permalink( $post->ID ) ) . '.<br>';
				}
			}
			$response .= '<br><a href="' . esc_url( get_post_type_archive_link( 'faq-post' ) ) . '">' . __( 'Click here to view our FAQ archive', 'ai-chatbot-easy-integration' ) . '</a>';

		} else {
			$response = __( 'Here are a few possible topics:', 'ai-chatbot-easy-integration' ) . '<br>';

			foreach ( $query->posts as $post ) {
				$response .= esc_attr( get_the_title( $post->ID ) ) . ' - ' . esc_url( get_the_permalink( $post->ID ) ) . '.<br>';
			}
		}
	}

	return $response;
}
