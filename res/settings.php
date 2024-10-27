<?php
/**
 * DEFINE PLUGIN SETTING
 **/

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/***
 * Add admin menu pages
 ***/
function ai_chatbot_easy_integration_add_menu_links() {
	$settings = ai_chatbot_easy_integration_get_settings();

	add_options_page( __( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ), __( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ), $settings['access_capability'], 'ai-chatbot-easy-integration', 'ai_chatbot_easy_integration_admin_interface_render' );

		add_menu_page( __( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ), __( 'AI Chatbot', 'ai-chatbot-easy-integration' ), $settings['access_capability'], 'ai-chatbot-easy-integration-log', 'ai_chatbot_easy_integration_log_results', 'dashicons-format-chat', 10 );

		add_submenu_page( 'ai-chatbot-easy-integration-log', __( 'Settings', 'ai-chatbot-easy-integration' ), __( 'Settings', 'ai-chatbot-easy-integration' ), 'manage_options', 'ai-chatbot-easy-integration', 'ai_chatbot_easy_integration_log_results' );

		add_submenu_page( 'ai-chatbot-easy-integration-log', __( 'Log', 'ai-chatbot-easy-integration' ), __( 'Chat Log', 'ai-chatbot-easy-integration' ), $settings['access_capability'], 'ai-chatbot-easy-integration-log', 'ai_chatbot_easy_integration_log_results' );

		add_submenu_page( 'ai-chatbot-easy-integration-log', __( 'Reports', 'ai-chatbot-easy-integration' ), __( 'Reports', 'ai-chatbot-easy-integration' ), $settings['access_capability'], 'ai-chatbot-easy-integration-reports', 'ai_chatbot_easy_integration_reports' );
}
add_action( 'admin_menu', 'ai_chatbot_easy_integration_add_menu_links' );

/**
 * Register Settings
 **/
function ai_chatbot_easy_integration_register_settings() {

	register_setting(
		'ai_chatbot_easy_integration_settings_group',
		'ai_chatbot_easy_integration_settings',
		'ai_chatbot_easy_integration_validater_and_sanitizer'
	);

	add_settings_section(
		'ai_chatbot_easy_integration_general_settings_section',
		'',
		'ai_chatbot_easy_integration_general_settings_section_callback',
		'ai-chatbot-easy-integration'
	);

	add_settings_field(
		'ai_chatbot_easy_integration_general_settings_field',
		__( 'General Settings', 'ai-chatbot-easy-integration' ),
		'ai_chatbot_easy_integration_general_settings_field_callback',
		'ai-chatbot-easy-integration',
		'ai_chatbot_easy_integration_general_settings_section'
	);
}
add_action( 'admin_init', 'ai_chatbot_easy_integration_register_settings' );

/**
 * Validate and sanitize user input before its saved to database
 *
 * @param  mixed $settings array of settings.
 */
function ai_chatbot_easy_integration_validater_and_sanitizer( $settings ) {
	foreach ( $settings as $key => $value ) {
		if ( 'daily_log_email' === $key && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			$settings[ $key ] = '';
		} elseif ( 'open_ai_search' == $key && 'true' != $value ) {
			$settings[ $key ] = false;
		} elseif ( 'open_ai_actions' == $key && 'true' != $value ) {
			$settings[ $key ] = false;
		} elseif ( 'webchat_embed_code' === $key ) {
			$settings[ $key ] = strip_tags( $value, '<script>' );
		} elseif ( 'display_basic_chatbot' === $key && 'true' !== $value ) {
			$settings[ $key ] = false;
		} elseif ( 'display_chatbot' === $key && 'true' !== $value ) {
			$settings[ $key ] = false;
		} elseif ( 'log_chat_messages' === $key && 'true' !== $value ) {
			$settings[ $key ] = false;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $key_inner => $value_inner ) {
				$settings[ $key ][ $key_inner ] = sanitize_text_field( $value_inner );
			}
		} else {
			$settings[ $key ] = sanitize_text_field( $value );
		}
	}

	// add pro plugin fields!
	$settings = apply_filters( 'ai_chatbot_easy_integration_validater_and_sanitizer', $settings );

	return $settings;
}

/**
 * Get settings
 **/
function ai_chatbot_easy_integration_get_settings() {

	$settings = get_option( 'ai_chatbot_easy_integration_settings' );

	if ( '' === $settings ) {
		$settings = ai_chatbot_easy_integration_set_default_settings();
	}

	return $settings;
}

/**
 * Set default settings
 **/
function ai_chatbot_easy_integration_set_default_settings() {

	$defaults = array(
		'contact_form'    => '',
		'webchat_embed_code'    => '',
		'wordpress_search'      => true,
		'log_chat_messages'     => true,
		'display_basic_chatbot' => false,
		'display_chatbot'       => false,
		'access_capability'     => 'manage_options',
		'daily_log_email'       => '',
		'chat_log_frequency'    => 24,
		'purge_frequency'       => 7,
		'web_search_results'    => 3,
		'display_pages'         => '',
		'openai_api_key'        => '',
		'open_ai_search'        => false,
		'open_ai_actions' => false,
		'chat_bot_greeting'     => 'Hello!',
		'webhook_hash'          => md5( wp_rand() ),
	);

	update_option( 'ai_chatbot_easy_integration_settings', $defaults );

	return $defaults;
}

/**
 * Callback function for General Settings section
 **/
function ai_chatbot_easy_integration_general_settings_section_callback() {
}

/***
 * Callback function for General Settings field
 **/
function ai_chatbot_easy_integration_general_settings_field_callback() {

	$settings = ai_chatbot_easy_integration_get_settings();

	echo '<input  type="hidden" value="' . esc_attr( $settings['webhook_hash'] ) . '" name="ai_chatbot_easy_integration_settings[webhook_hash]" value="' . esc_attr( $settings['webhook_hash'] ) . '">';

	if ( ! defined( 'AI_CHATBOT_EASY_INTEGRATION_PRO_VERSION_NUM' ) ) {
	echo '<input  type="hidden" value="' . esc_attr( $settings['wordpress_search'] ) . '" name="ai_chatbot_easy_integration_settings[wordpress_search]" value="' . esc_attr( $settings['wordpress_search'] ) . '">';
	}
	?>
<div class="marketing_code ai-chatbot-easy-integration-instructions" style="display:none"> 
	<button class="ai_chatbot_easy_integration_close_instructions" title="<?php esc_attr_e( 'Close', 'ai-chatbot-easy-integration' ); ?>"><i class="far fa-window-close" aria-hidden="true"></i></button>
<strong>
	<?php esc_attr_e( 'Basic Instructions:', 'ai-chatbot-easy-integration' ); ?>
</strong>
	<ol>
	<li><?php esc_attr_e( 'First determine the type of Chatbot you will be using. You may choose our basic chat feature or Watson Assistant. The basic chatbot can be setup in minutes and used on any website. It includes built in FAQs and will search your website for answers, if available, it will fallback to ChatGPT/OpenAI if it does not know how to answer or OpenAI can be set as the default response. ChatGPT requires the purchase of API credits. Watson Assistant involves more setup but provides a more complete chatbot experience. Watson Assistant includes a free version that will work for most users.', 'ai-chatbot-easy-integration' ); ?></li>
	<li>
	<?php esc_attr_e( 'If you will be using the basic chatbot, check the "Display basic chatbot on your website" option under the "Basic Chat" tab on this page.', 'ai-chatbot-easy-integration' ); ?>
	</li>
		<li>
	<?php esc_attr_e( 'Create any desired FAQ under the FAQ Post tab of the WordPress Dashbaord. Include the "most requested" tag to have them returned by the chatbot. All other FAQ will be displayed in the FAQ archive.', 'ai-chatbot-easy-integration' ); ?>
	</li>
	<li><a href="https://www.aichatboteasyintegration.com/how-to/how-to-generate-an-open-ai-api-key/" target="_blank" title="<?php esc_attr_e( 'opens a new tab', 'ai-chatbot-easy-integration' ); ?>"><?php esc_attr_e( 'If you will be using ChatGPT/OpenAI, create an OpenAI API account, generate an API key and enter the API key under the "ChatGPT/OpenAI" on this page', 'ai-chatbot-easy-integration' ); ?></a>.</li>
	<li>
	<?php esc_attr_e( 'Skip to step 10 below if you are not using Watson Assistant.', 'ai-chatbot-easy-integration' ); ?>
	</li>
<li><a href="https://www.aichatboteasyintegration.com/how-to/creating-a-watson-assistant-chatbot/" target="_blank" title="<?php esc_attr_e( 'opens a new tab', 'ai-chatbot-easy-integration' ); ?>"><?php esc_attr_e( 'If you will be using Watson Assitant and you haven\'t already done so, create a Watson Assistant Chatbot', 'ai-chatbot-easy-integration' ); ?></a>.</li>
<li><a href="https://www.aichatboteasyintegration.com/getting-started/locating-the-watson-assistant-embed-code/" target="_blank" title="<?php esc_attr_e( 'opens a new tab', 'ai-chatbot-easy-integration' ); ?>"><?php esc_attr_e( 'Under the "Embed Code" tab on this page, enter your Watson Assistant embed code.', 'ai-chatbot-easy-integration' ); ?></a></li>
<li><a href="https://www.aichatboteasyintegration.com/getting-started/configuring-a-premessage-web-hook/" target="_blank" title="<?php esc_attr_e( 'opens a new tab', 'ai-chatbot-easy-integration' ); ?>"><?php esc_attr_e( 'Copy your Webhook URL and configure a pre-message and post-message webhook on the IBM Watson Assistant website.', 'ai-chatbot-easy-integration' ); ?></a></li>
<li><?php esc_attr_e( 'Under the "Embed Code" tab on this page, enable the "Display Watson Assistant Chatbot on public website" option.', 'ai-chatbot-easy-integration' ); ?></li>
<li><?php esc_attr_e( 'Under the "Chat Logs" tab on this page, enter an email address to receive chat log reports.', 'ai-chatbot-easy-integration' ); ?></li>
<li><a href="/"><?php esc_attr_e( 'Go to your website to see how it will look to the public.', 'ai-chatbot-easy-integration' ); ?></a></li>

</ol>
</div>

<a href="#" class="ai-chatbot-easy-integration-settings-section" role="button" data-id="ai-chatbot-easy-integration-basicchat" aria-expanded="false">
	<?php esc_attr_e( 'Basic Chat', 'ai-chatbot-easy-integration' ); ?>
</a>
<div class="ai-chatbot-easy-integration-fields hidden ai-chatbot-easy-integration-basicchat" >
<p class="description">

	<?php esc_attr_e( 'Basic chat is a simple chatbot that can be setup in minutes and used on any website. It will search your website for answers to users questions and if available, will fallback to ChatGPT/OpenAI if it does not know how to answer.', 'ai-chatbot-easy-integration' ); ?>
</p>
<p class="description">
	<label for="display_basic_chatbot">	<input  type="checkbox" value="true" id="display_basic_chatbot" name="ai_chatbot_easy_integration_settings[display_basic_chatbot]" 
	<?php
	if ( isset( $settings['display_basic_chatbot'] ) && ( ! empty( $settings['display_basic_chatbot'] ) ) ) {
		echo ' checked';}
	?>
	><?php esc_attr_e( 'Display basic chatbot on public website.', 'ai-chatbot-easy-integration' ); ?>
		</label>
	</p>
<p class="description">
<label for="chat_bot_greeting"><?php esc_attr_e( 'Chatbot Header Text:', 'ai-chatbot-easy-integration' ); ?>  
	<?php
	echo '<input type="text" name="ai_chatbot_easy_integration_settings[chat_bot_greeting]" id="chat_bot_greeting" class="regular-text" value="';
	if ( isset( $settings['chat_bot_greeting'] ) && ( ! empty( $settings['chat_bot_greeting'] ) ) ) {
		echo esc_attr( $settings['chat_bot_greeting'] );
	}
	echo '">';
	?>
</label></p> 	

<p class="description">
<label for="contact_form"><?php esc_attr_e( 'URL to Contact Form:', 'ai-chatbot-easy-integration' ); ?>  
	<?php
	echo '<input type="text" name="ai_chatbot_easy_integration_settings[contact_form]" id="contact_form" class="regular-text" value="';
	if ( isset( $settings['contact_form'] ) && ( ! empty( $settings['contact_form'] ) ) ) {
		echo esc_attr( $settings['contact_form'] );
	}
	echo '">';
	?>
</label></p> 	
</div>

<a href="#" class="ai-chatbot-easy-integration-settings-section" role="button" data-id="ai-chatbot-easy-integration-embedcode" aria-expanded="false">
	<?php esc_attr_e( 'IBM Watson Assistant ', 'ai-chatbot-easy-integration' ); ?>
</a>
<div class="ai-chatbot-easy-integration-fields hidden ai-chatbot-easy-integration-embedcode" >
<p class="description">
	<label for="display_chatbot">	<input  type="checkbox" value="true" id="display_chatbot" name="ai_chatbot_easy_integration_settings[display_chatbot]" 
	<?php
	if ( isset( $settings['display_chatbot'] ) && ( ! empty( $settings['display_chatbot'] ) ) ) {
		echo ' checked';}
	?>
	><?php esc_attr_e( 'Display Watson Assistant Chatbot on public website. When enabled, this replaces the basic chatbot.', 'ai-chatbot-easy-integration' ); ?>
		</label>
	</p>
	<p class="description">
	<?php esc_attr_e( 'WebHook URL: ', 'ai-chatbot-easy-integration' ); ?><br>
	<?php echo esc_url( get_site_url() ); ?>/?chatbotcall=<?php echo esc_attr( $settings['webhook_hash'] ); ?> 
</p>

<p class="description">
	<label for="webchat_embed_code"><?php esc_attr_e( 'Paste the embed code for IBM Watson Assistant here:', 'ai-chatbot-easy-integration' ); ?><br>
		<?php
		echo '<textarea  cols="45" rows="20" name="ai_chatbot_easy_integration_settings[webchat_embed_code]" id="webchat_embed_code" class="regular-text">';
		if ( isset( $settings['webchat_embed_code'] ) && ( ! empty( $settings['webchat_embed_code'] ) ) ) {
			echo esc_attr( $settings['webchat_embed_code'] );}
			echo '</textarea>';
		?>
		
		</label>
</p>
</div>
<a href="#" class="ai-chatbot-easy-integration-settings-section" role="button" data-id="ai-chatbot-easy-integration-openai" aria-expanded="false">
		<?php esc_attr_e( 'ChatGPT/OpenAI', 'ai-chatbot-easy-integration' ); ?>
</a>
<div class="ai-chatbot-easy-integration-openai ai-chatbot-easy-integration-fields hidden">

<p class="description">
	<?php esc_attr_e( 'AI Chatbot Easy Integration interphases with OpenAI to leverage the power of ChatGPT. OpenAI is a paid service that requires the purchase of query credits. Create an OpenAI account, generate a secret key and enter it below to enable support for OpenAI. ', 'ai-chatbot-easy-integration' ); ?>  <a href="https://platform.openai.com/overview"><?php esc_attr_e( 'Generate an Open AI Key', 'ai-chatbot-easy-integration' ); ?></a><br><br>
		<label for="openai_api_key"><?php esc_attr_e( 'OpenAI Secret Key:', 'ai-chatbot-easy-integration' ); ?>  
												<?php
												echo '<input type="text" name="ai_chatbot_easy_integration_settings[openai_api_key]" id="openai_api_key" class="regular-text" value="';
												if ( isset( $settings['openai_api_key'] ) && ( ! empty( $settings['openai_api_key'] ) ) ) {
													echo esc_attr( $settings['openai_api_key'] );
												}
												echo '">';
												?>
		</label></p> 
		<p class="description">
		<label for="open_ai_actions"><input type="checkbox" id="open_ai_actions" value="true"
				name="ai_chatbot_easy_integration_settings[open_ai_actions]" 
				<?php
				if ( isset( $settings['open_ai_actions'] ) && ( ! empty( $settings['open_ai_actions'] ) ) ) {
					echo ' checked';
				}
				?>
			>
			<?php esc_html_e( 'Include OpenAI complex capabilities in the basic chatbot.', 'ai-chatbot-easy-integration' ); ?>
		</label>
	</p>
		<p class="description">
		<label for="open_ai_search"><input type="checkbox" id="open_ai_search" value="true"
				name="ai_chatbot_easy_integration_settings[open_ai_search]" 
				<?php
				if ( isset( $settings['open_ai_search'] ) && ( ! empty( $settings['open_ai_search'] ) ) ) {
					echo ' checked';
				}
				?>
			>
			<?php esc_html_e( 'Always use Open AI to respond to requests. When disabled website search will be used first, if no match is found, an Open AI response will be generated.', 'ai-chatbot-easy-integration' ); ?>
		</label>
	</p>
		
</div>
<a href="#" class="ai-chatbot-easy-integration-settings-section" role="button" data-id="ai-chatbot-easy-integration-placement" aria-expanded="false">
	<?php esc_attr_e( 'Chatbot Placement', 'ai-chatbot-easy-integration' ); ?>
</a>
<div class="ai-chatbot-easy-integration-fields hidden ai-chatbot-easy-integration-placement" >
<p class="description">
	<label for="webchat_embed_code"><?php esc_attr_e( 'Select one or more pages, to display the chatbot only on selected pages:', 'ai-chatbot-easy-integration' ); ?><br>
	<select name="ai_chatbot_easy_integration_settings[display_pages][]" size="5" multiple>
	<?php
	$args  = array(
		'post_status' => 'publish',
		'post_type'   => 'page',
	);
	$pages = get_pages( $args );

	foreach ( $pages as $page ) {
		echo '<option value="' . esc_attr( $page->ID ) . '"';
		if ( isset( $settings['display_pages'] ) && is_array( $settings['display_pages'] ) && in_array( $page->ID, $settings['display_pages'] ) ) {
			echo ' selected ';
		}
		echo '>';
		echo esc_attr( $page->post_title );
		echo '</option>';
	}
	?>
</select>
</label>
</p>
</div>
<a href="#" class="ai-chatbot-easy-integration-settings-section" role="button" data-id="ai-chatbot-easy-integration-chatlogs" aria-expanded="false">
		<?php esc_attr_e( 'Chat Logs', 'ai-chatbot-easy-integration' ); ?>
</a>
<div class="ai-chatbot-easy-integration-chatlogs ai-chatbot-easy-integration-fields hidden">
<p class="description">
		<label for="access_capability"><?php esc_attr_e( 'Capability required to view Chatlog:', 'ai-chatbot-easy-integration' ); ?>  
												<?php
												echo '<input type="text" name="ai_chatbot_easy_integration_settings[access_capability]" id="access_capability" class="regular-text" value="';
												if ( isset( $settings['access_capability'] ) && ( ! empty( $settings['access_capability'] ) ) ) {
													echo esc_attr( $settings['access_capability'] );
												}
												echo '">';
												?>
		</label><br><a href="https://wordpress.org/documentation/article/roles-and-capabilities/"><?php esc_attr_e( 'Learn about Roles and Capabilities', 'ai-chatbot-easy-integration' ); ?></a></p> 	
<p class="description">
	<label for="log_chat_messages"> 
	<?php
	echo '<input  type="checkbox" id="log_chat_messages" value="true" name="ai_chatbot_easy_integration_settings[log_chat_messages]"';
	if ( isset( $settings['log_chat_messages'] ) && ( ! empty( $settings['log_chat_messages'] ) ) ) {
		echo ' checked';}
		echo '>';
	?>
	<?php esc_attr_e( 'Log chat messages (a pre-message webhook must be configured in Watson Assistant. Your webhook url is provided above.).', 'ai-chatbot-easy-integration' ); ?>
		</label>
	</p>

	<p class="description">
		<label for="daily_log_email"><?php esc_attr_e( 'Email Address to send chat log reports:', 'ai-chatbot-easy-integration' ); ?> 
												<?php
												echo '<input type="text" name="ai_chatbot_easy_integration_settings[daily_log_email]" id="daily_log_email" class="regular-text" value="';
												if ( isset( $settings['daily_log_email'] ) && ( ! empty( $settings['daily_log_email'] ) ) ) {
													echo esc_attr( $settings['daily_log_email'] );}
													echo '">';
												?>
		</label></p>	

	</div>
		
	<?php
	// add pro plugin fields!
	apply_filters( 'ai_chatbot_easy_integration_general_settings_field_callback', '' );
	?>
	
	<?php
}

/**
 * Admin interface renderer
 *
 * @since 1.0
 */
function ai_chatbot_easy_integration_admin_interface_render() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
		
	<div class="ai-chatbot-options-wrap">
	<div class="ai_chatbot_easy_integration_logo"><a href="https://www.aichatboteasyintegration.com/"><img src="<?php echo esc_url( AI_CHATBOT_EASY_INTEGRATION_URL ); ?>logo.png" alt="<?php esc_attr_e( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ); ?>"></a>
</div>	
		<h1><?php esc_attr_e( 'AI Chatbot Easy Integration', 'ai-chatbot-easy-integration' ); ?></h1>
		
		<form action="options.php" method="post" class="ai-chatbot-options-form">		
			<?php

			settings_fields( 'ai_chatbot_easy_integration_settings_group' );

			do_settings_sections( 'ai-chatbot-easy-integration' );

			submit_button( __( 'Save Settings', 'ai-chatbot-easy-integration' ) );
			?>
		</form>
	</div>
	<?php
}
?>