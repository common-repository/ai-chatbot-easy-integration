<?php
/**
 * Plugin Name: AI Chatbot Easy Integration
 * Plugin URI: https://aichatboteasyintegration.com
 * Description: This plugin allows you to easily add a chatbot powered by IBM Watson Assistant to your website.
 * Author: AlumniOnline Web Services LLC
 * Author URI: https://www.alumnionlineservices.com
 * Version: 1.0.16
 * Text Domain: ai-chatbot-easy-integration
 * License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define constants
 *
 * @since 1.0
 */
if ( ! defined( 'AI_CHATBOT_EASY_INTEGRATION_VERSION_NUM' ) ) {
	define( 'AI_CHATBOT_EASY_INTEGRATION_VERSION_NUM', '1.0.16 BETA' ); // Plugin version constant!
}
if ( ! defined( 'AI_CHATBOT_EASY_INTEGRATION' ) ) {
	define( 'AI_CHATBOT_EASY_INTEGRATION', trim( dirname( plugin_basename( __FILE__ ) ), '/' ) ); // Name of the plugin folder.
}
if ( ! defined( 'AI_CHATBOT_EASY_INTEGRATION_DIR' ) ) {
	define( 'AI_CHATBOT_EASY_INTEGRATION_DIR', plugin_dir_path( __FILE__ ) ); // Plugin directory absolute path with the trailing slash. Useful for using with includes.
}
if ( ! defined( 'AI_CHATBOT_EASY_INTEGRATION_URL' ) ) {
	define( 'AI_CHATBOT_EASY_INTEGRATION_URL', plugin_dir_url( __FILE__ ) ); // URL to the plugin folder with the trailing slash. Useful for referencing src eg.
}


// include additional files!
require AI_CHATBOT_EASY_INTEGRATION_DIR . 'res/rest.php';
require AI_CHATBOT_EASY_INTEGRATION_DIR . 'res/settings.php';
require AI_CHATBOT_EASY_INTEGRATION_DIR . 'res/chatbot-features.php';
require AI_CHATBOT_EASY_INTEGRATION_DIR . 'res/openai.php';


// Register activation hook!
register_activation_hook( __FILE__, 'ai_chatbot_easy_integration_activate_plugin' );

// Register deactivation hook!
register_deactivation_hook( __FILE__, 'ai_chatbot_easy_integration_deactivate_plugin' );

// Register uninstall hook!
register_uninstall_hook( __FILE__, 'ai_chatbot_easy_integration_uninstall_plugin' );


/**
 *  Schedule cron jobs
 */
function ai_chatbot_easy_integration_setup_daily_cron() {
	if ( ! wp_next_scheduled( 'ai_chatbot_easy_integration_daily_cron_hook' ) ) {
		wp_schedule_event( time(), 'hourly', 'ai_chatbot_easy_integration_daily_cron_hook' );
	}
}
add_action( 'admin_init', 'ai_chatbot_easy_integration_setup_daily_cron' );
add_action( 'ai_chatbot_easy_integration_daily_cron_hook', 'ai_chatbot_easy_integration_daily_cron', 10, 2 );

/******************************************************
Run daily cron
 *******************************************************/
function ai_chatbot_easy_integration_daily_cron() {
	$settings = ai_chatbot_easy_integration_get_settings();

	if ( ! isset( $settings['purge_frequency'] ) ) {
		$settings['purge_frequency'] = 7;
	}
	ai_chatbot_easy_integration_purge_log( $settings['purge_frequency'] );

	if ( '' === $settings['daily_log_email'] ) {
		return;
	}

	$message = ai_chatbot_easy_integration_display_email_log( $settings['chat_log_frequency'] );

	if ( '' === $message ) {
		return;
	}

	$to_email    = $settings['daily_log_email'];
	$reply_email = esc_attr( get_bloginfo( 'admin_email' ) );
	$subject     = __( 'ChatBot Log for ', 'ai-chatbot-easy-integration' ) . ' ' . esc_attr( get_bloginfo( 'name' ) );

	$mailheaders  = "MIME-Version: 1.0 \r\n";
	$mailheaders .= "Content-type: text/html; charset=\"UTF-8\" \r\n";
	$mailheaders .= "Reply-To: $reply_email\n";

	wp_mail( $to_email, $subject, $message, $mailheaders );
}

/**
 * Plugin activatation todo list
 *
 * This function runs when user activates the plugin. Used in register_activation_hook in the main plugin file.
 *
 * @since 1.0
 *
 * @param  mixed $network_wide used to define nertwork wide.
 * @return void
 */
function ai_chatbot_easy_integration_activate_plugin( $network_wide = false ) {
	global $wpdb;

	if ( is_multisite() ) {

		$blog_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->prefix . 'blogs' );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			$settings = ai_chatbot_easy_integration_get_settings();
			ai_chatbot_easy_integration_create_tables();
			restore_current_blog();
		}
	} else {
		$settings = ai_chatbot_easy_integration_get_settings();
		ai_chatbot_easy_integration_create_tables();
	}
}

/**
 * Redirect to settings page
 *
 * @param  mixed $plugin the plugin path.
 * @return void
 */
function ai_chatbot_easy_integration_activation_redirect( $plugin ) {
	if ( 'ai-chatbot-easy-integration/ai-chatbot-easy-integration.php' === $plugin ) {
		$url = esc_url( get_site_url() . '/wp-admin/admin.php?page=ai-chatbot-easy-integration' );
		wp_safe_redirect( $url );
		exit;
	}
}
add_action( 'activated_plugin', 'ai_chatbot_easy_integration_activation_redirect' );

/**
 * Print direct link to plugin settings in plugins list in admin
 *
 * @param  mixed $links the links.
 */
function ai_chatbot_easy_integration_settings_link( $links ) {
	return array_merge(
		array(
			'settings' => '<a href="' . admin_url( 'options-general.php?page=ai-chatbot-easy-integration' ) . '">' . __( 'Settings', 'ai-chatbot-easy-integration' ) . '</a>',
		),
		$links
	);
}
add_filter( 'plugin_action_links_' . AI_CHATBOT_EASY_INTEGRATION . '/ai-chatbot-easy-integration.php', 'ai_chatbot_easy_integration_settings_link' );



/**
 * Create database table
 */
function ai_chatbot_easy_integration_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name1     = $wpdb->prefix . 'ai_chatbot_easy_integration_logs';

	$sql = "CREATE TABLE $table_name1 (
		id int(11) NOT NULL AUTO_INCREMENT, 
        topic text NOT NULL, 
		messages text NOT NULL, 
		entrydate datetime NOT NULL,
		livechat_starttime datetime NOT NULL,
		sessionid text NOT NULL,
		smsnumber text NOT NULL,
		twilionumber text NOT NULL,
		sessionstatus text NOT NULL,
		userid text NOT NULL,
		username text NOT NULL,
		email text NOT NULL,
		phone text NOT NULL,
		agentassigned text NOT NULL,
		notes text NOT NULL,
		lastaction text NOT NULL,
		errors text NOT NULL,
		options text NOT NULL,
		ticketid int(11) NOT NULL,
		lognoticestatus int(11) NOT NULL,
		notificationstatus int(11) NOT NULL,
		initiatetransfer int(11) NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );
}

/**
 * DEACTIVATE
 *
 * @return void
 */
function ai_chatbot_easy_integration_deactivate_plugin() {

	// deactivate pro plugin!
	if ( is_plugin_active( 'ai-chatbot-easy-integration/ai-chatbot-easy-integration-pro.php' ) && is_plugin_active( 'ai-chatbot-easy-integration-pro/ai-chatbot-easy-integration-pro.php' ) ) {
		deactivate_plugins( 'ai-chatbot-easy-integration-pro/ai-chatbot-easy-integration-pro.php' );
	}

	wp_clear_scheduled_hook( 'ai_chatbot_easy_integration_daily_cron_hook' );
}

/**
 * Uninstall
 */
function ai_chatbot_easy_integration_uninstall_plugin() {
	global $wpdb;

	if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			ai_chatbot_easy_integration_delete_tables();
			ai_chatbot_easy_integration_remove_options();
			wp_clear_scheduled_hook( 'ai_chatbot_easy_integration_daily_cron_hook' );

			restore_current_blog();
		}
	} else {
		ai_chatbot_easy_integration_delete_tables();
		ai_chatbot_easy_integration_remove_options();
		wp_clear_scheduled_hook( 'ai_chatbot_easy_integration_daily_cron_hook' );
	}
}

/**
 * Remove options
 */
function ai_chatbot_easy_integration_remove_options() {
	foreach ( wp_load_alloptions() as $option => $value ) {
		if ( 0 === strpos( $option, 'ai_chatbot_easy_integration_' ) ) {
			delete_option( $option );
		}
	}
}

/**
 * Remove tables
 */
function ai_chatbot_easy_integration_delete_tables() {
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'ai_chatbot_easy_integration_logs' );
}


/**
 * Upgrade processes
 **/
function ai_chatbot_easy_integration_upgrader() {

	// Get the current version of the plugin stored in the database.
	$current_ver = get_option( 'ai_chatbot_easy_integration_version', '0.0' );

	// Return if we are already on updated version.
	if ( version_compare( $current_ver, AI_CHATBOT_EASY_INTEGRATION_VERSION_NUM, '==' ) ) {
		return;
	}

	// This part will only be excuted once when a user upgrades from an older version to a newer version.

	// Finally add the current version to the database. Upgrade todo complete.
	update_option( 'ai_chatbot_easy_integration_version', AI_CHATBOT_EASY_INTEGRATION_VERSION_NUM );
}
	add_action( 'admin_init', 'ai_chatbot_easy_integration_upgrader' );


/**
 *  Enqueue Admin CSS and JS
 *
 * @param  mixed $hook the plugin name.
 * @return void
 */
function ai_chatbot_easy_integration_enqueue_css_js( $hook ) {

	if ( 'ai-chatbot-easy-integration' === $hook && ( ! isset( $_SERVER['REQUEST_URI'] ) || ! strstr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'page=ai-chatbot-easy-integration' ) ) ) {
		return;
	}

	wp_enqueue_style( 'ai-chatbot-easy-integration-admin-main-css', AI_CHATBOT_EASY_INTEGRATION_URL . 'main.css', '', AI_CHATBOT_EASY_INTEGRATION_VERSION_NUM );

	wp_enqueue_script( 'ai-chatbot-easy-integration-admin-main-js', AI_CHATBOT_EASY_INTEGRATION_URL . 'main.js', array( 'jquery' ), false, true );
	wp_localize_script(
		'ai-chatbot-easy-integration-admin-main-js',
		'ai_chatbot_easy_integration_variables',
		array(
			'resturl'             => esc_url_raw( get_rest_url() ),
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'message_log_updated' => __( 'Chat log updated at: ', 'ai-chatbot-easy-integration' ),
			'message_updated'     => __( 'Response Received', 'ai-chatbot-easy-integration' ),
			'message_sent'     => __( 'Message Sent', 'ai-chatbot-easy-integration' ),
			'you'                 => __( 'You: ', 'ai-chatbot-easy-integration' ),
			'agent'               => __( 'Agent: ', 'ai-chatbot-easy-integration' ),

		)
	);
}
add_action( 'admin_enqueue_scripts', 'ai_chatbot_easy_integration_enqueue_css_js' );
add_action( 'wp_enqueue_scripts', 'ai_chatbot_easy_integration_enqueue_css_js' );

/**
 * Get trusted tags for sanitation
 */
function ai_chatbot_easy_integration_get_trusted_tags_array() {

	$trustedtags = array(
		'button'   => array(
			'style'       => array(),
			'class'       => array(),
			'id'          => array(),
			'data-id'     => array(),
			'title'       => array(),
			'data-userid' => array(),
			'data-faq'    => array(),
			'data-agent'  => array(),
			'type'        => array(),
		),
		'p'        => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'a'        => array(
			'style'       => array(),
			'class'       => array(),
			'id'          => array(),
			'data-status' => array(),
			'role'        => array(),
			'data-offset' => array(),
			'href'        => array(),
			'target'      => array(),
		),
		'img'      => array(
			'src'    => array(),
			'alt'    => array(),
			'width'  => array(),
			'height' => array(),
			'style'  => array(),
			'class'  => array(),
			'id'     => array(),
			'usemap' => array(),
		),
		'h1'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'h2'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'h3'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'h4'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'h5'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'h6'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'input'    => array(
			'style'  => array(),
			'class'  => array(),
			'id'     => array(),
			'type'   => array(),
			'name'   => array(),
			'value'  => array(),
			'src'    => array(),
			'border' => array(),
			'title'  => array(),
			'aria-label'  => array(),
			'placeholder'  => array(),
		),

		'pre'      => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'textarea' => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
			'cols'  => array(),
			'rows'  => array(),
			'name'  => array(),
		),
		'label'    => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
			'for'   => array(),
		),
		'select'   => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
			'name'    => array(),
		),
		'option'   => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
			'name'    => array(),
			'value' => array(),
		),
		'span'     => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'i'        => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'fieldset' => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'caption'  => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'form'     => array(
			'style'  => array(),
			'class'  => array(),
			'id'     => array(),
			'action' => array(),
			'method' => array(),
			'target' => array(),
		),
		'legend'   => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'br'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'div'      => array(
			'style'      => array(),
			'class'      => array(),
			'id'         => array(),
			'aria-live'  => array(),
			'aria-label' => array(),
			'role'       => array(),
		),
		'table'    => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'th'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'td'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'tr'       => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'tbody'    => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
		'thead'    => array(
			'style' => array(),
			'class' => array(),
			'id'    => array(),
		),
	);

	return $trustedtags;
}

/**
 * Create a FAQ post type
 */
function ai_chatbot_easy_integration_faq_post_type() {
	$labels = array(
		'name'               => __( 'FAQ Posts', 'ai-chatbot-easy-integration' ),
		'singular_name'      => __( 'FAQ Post', 'ai-chatbot-easy-integration' ),
		'add_new'            => __( 'Add New FAQ Post', 'ai-chatbot-easy-integration' ),
		'add_new_item'       => __( 'Add New FAQ Post', 'ai-chatbot-easy-integration' ),
		'edit_item'          => __( 'Edit FAQ Post', 'ai-chatbot-easy-integration' ),
		'new_item'           => __( 'New FAQ Post', 'ai-chatbot-easy-integration' ),
		'all_items'          => __( 'All FAQ Posts', 'ai-chatbot-easy-integration' ),
		'view_item'          => __( 'View FAQ Posts', 'ai-chatbot-easy-integration' ),
		'search_items'       => __( 'Search FAQ Posts', 'ai-chatbot-easy-integration' ),
		'featured_image'     => __( 'Featured Image', 'ai-chatbot-easy-integration' ),
		'set_featured_image' => __( 'Add Featured Image', 'ai-chatbot-easy-integration' ),
	);
	$args   = array(
		'labels'             => $labels,
		'description'        => '',
		'public'             => true,
		'menu_position'      => 3,
		'supports'           => array( 'title', 'editor', 'author' ),
		'has_archive'        => true,
		'show_in_admin_bar'  => true,
		'show_in_nav_menus'  => true,
		'query_var'          => true,
		'publicly_queryable' => true,
		'taxonomies'         => array( 'category', 'post_tag' ),
	);
	register_post_type( 'faq-post', $args );

	ai_chatbot_easy_integration_create_terms();
}
add_action( 'init', 'ai_chatbot_easy_integration_faq_post_type' );

/**
 * Create most requested tag
 */
function ai_chatbot_easy_integration_create_terms() {
	if ( ! term_exists( 'most requested', 'post_tag' ) ) {
		wp_insert_term( 'most requested', 'post_tag' );
	}
}
