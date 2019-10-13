<?php
/**
 * Plugin Name:     One Time Login URL
 * Plugin URI:      
 * Description:     Use WP-CLI to generate a one-time login URL for any user.
 * Author:          Katsuhiko Utsubo
 * Author URI:      
 * Text Domain:     one-time-login-url
 * Domain Path:     /languages
 * Version:         0.0.1
 *
 * @package         One_Time_Login_URL
 */

/**
 * Generate a one-time login URL for any user.
 *
 * ## OPTIONS
 *
 * <user>
 * : ID, email address, or user login for the user.
 *
 * [--count=<count>]
 * : Generate a specified number of login tokens.
 * ---
 * default: 1
 * ---
 *
 * [--delay-delete=<min|YYYY-mm-dd>]
 * : Delete existing tokens after <min> minutes. if <YYYY-mm-dd> is seted ,delete tokens  at YYYY-mm-dd.
 *  if no set this option, token delete immediately.
 *
 * [--from-date=<YYYY-mm-dd>]
 * : Validity period start date time. Invalidate if not set.
 *
 * ---
 * default: None
 * ---
 *
 * [--to-date=<YYYY-mm-dd>]
 * : Validity period end date time. Invalidate if not set.
 * ---
 * default: None
 * ---
 *
 * ## EXAMPLES
 *
 *     # Generate two one-time login URLs.
 *     $ wp user one-time-login-url testuser --count=2
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_url_token=ebe62e3
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_url_token=eb41c77
 *
 *     $ wp user one-time-login-url testuser --from-date="2019-01-01 00:00:00"
 *     # this url is invalid by from-date, after from-date you can access this url. 
 *
 *     $ wp user one-time-login-url testuser --delay-delete="2019-03-03" --to-date="2019-03-03"
 *     # token is valid to "2019-03-03" ,you can access many times up to "2019-03-03"
 *
 *     $ wp user one-time-login-url testuser --delete-delete=None
 *     # token never delete. (not recommend)
 */
function one_time_login_url_wp_cli_command( $args, $assoc_args ) {

	$fetcher = new WP_CLI\Fetchers\User;
	$user = $fetcher->get_check( $args[0] );
	$delay_delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delay-delete' );
	$to_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'to-date' );
	$from_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'from-date' );
	$count = (int) $assoc_args['count'];
	$tokens = $new_tokens = array();
	$todate = "2038-01-19";
	if ( $to_date ) {
		$v=strtotime($to_date);
		if ( $v ){
			$todate=$v;
		}
	}
	$fmdate = "2000-01-01";
	if ( $from_date ){
		$v=strtotime($from_date);
		if ( $v ){
			$fmdate=$v;
		}
	}
	echo "delay_delete=".$delay_delete."\n";

	$invalidate_time=0;
	if ( $delay_delete ) {
		echo "delay_delete\n";
		$invalidate_time=strtotime($delay_delete);
		echo "invalidate_time=".$invalidate_time."\n";
		if ( ! $invalidate_time ){
			$val = (int) $delay_delete;
			echo "val=".$val."\n";
			if ( $val ==0 ){
				wp_die( "Invalid option : delay-delete\n" );
			}
			$invalidate_time = time() + ( $delay_delete * MINUTE_IN_SECONDS );
		}
		$tokens = get_user_meta( $user->ID, 'one_time_login_url_token', true );
		$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
		wp_schedule_single_event( $invalidate_time , 'one_time_login_url_cleanup_expired_tokens', array( $user->ID, $tokens ) );
	}

	for ( $i = 0; $i < $count; $i++ ) {
		$password = wp_generate_password();
		$token = sha1( $password );
		$tokens[] = array("password"=>$token,"fmdate"=>strtotime($from_date),"todate"=>strtotime($to_date),"invtime"=>$invalidate_time);
		$new_tokens[] = $token;
	}

	update_user_meta( $user->ID, 'one_time_login_url_token', $tokens );
	do_action( 'one_time_login_url_created', $user );
	foreach ( $new_tokens as $token ) {
		$query_args = array(
			'user_id'              => $user->ID,
			'one_time_login_url_token' => $token,
		);
		$login_url = add_query_arg( $query_args, wp_login_url() );
		WP_CLI::log( $login_url );
	}
}

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::add_command( 'user one-time-login-url', 'one_time_login_url_wp_cli_command' );
}

/**
 * Handle cleanup process for expired one-time login tokens.
 */
function one_time_login_url_cleanup_expired_tokens( $user_id, $expired_tokens ) {
	$tokens = get_user_meta( $user_id, 'one_time_login_url_token', true );
	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
	$new_tokens = array();
	foreach ( $tokens as $token ) {
		if ( ! in_array( $token, $expired_tokens, true ) ) {
			$new_tokens[] = $token;
		}
	}
	update_user_meta( $user_id, 'one_time_login_url_token', $new_tokens );
}
add_action( 'one_time_login_url_cleanup_expired_tokens', 'one_time_login_url_cleanup_expired_tokens', 10, 2 );

/**
 * Log a request in as a user if the token is valid.
 */
function one_time_login_url_handle_token() {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow || empty( $_GET['user_id'] ) || empty( $_GET['one_time_login_url_token'] ) ) {
		return;
	}

	if ( is_user_logged_in() ) {
		$error = sprintf( __( 'Invalid one-time login token, but you are logged in as \'%s\'. <a href="%s">Go to the dashboard instead</a>?', 'one-time-login-url' ), wp_get_current_user()->user_login, admin_url() );
	} else {
		$error = sprintf( __( 'Invalid one-time login token. <a href="%s">Try signing in instead</a>?', 'one-time-login-url' ), wp_login_url() );
	}

	// Ensure any expired crons are run
	// It would be nice if WP-Cron had an API for this, but alas.
	$crons = _get_cron_array();
	if ( ! empty( $crons ) ) {
		foreach ( $crons as $time => $hooks ) {
			if ( time() < $time ) {
				continue;
			}
			foreach ( $hooks as $hook => $hook_events ) {
				if ( 'one_time_login_url_cleanup_expired_tokens' !== $hook ) {
					continue;
				}
				foreach ( $hook_events as $sig => $data ) {
					if ( ! defined( 'DOING_CRON' ) ) {
						define( 'DOING_CRON', true );
					}
					do_action_ref_array( $hook, $data['args'] );
					wp_unschedule_event( $time, $hook, $data['args'] );
				}
			}
		}
	}

	// Use a generic error message to ensure user ids can't be sniffed
	$user = get_user_by( 'id', (int) $_GET['user_id'] );
	if ( ! $user ) {
		wp_die( $error );
	}

	$tokens = get_user_meta( $user->ID, 'one_time_login_url_token', true );
	$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
	$is_valid = false;
	foreach ( $tokens as $i => $token ) {
		if($token["todate"]<time()){
			if ( $token["invtime"]==0){
				unset($tokens[ $i ]);
			}
			continue;
		}

		if ( hash_equals( $token["password"], $_GET['one_time_login_url_token'] ) and $token["fmdate"]<=time() and time()<=$token["todate"]) {
			$is_valid = true;
			if( $token["invtime"]==0){
				unset( $tokens[ $i ] );
			}
			break;
		}
	}

	if ( ! $is_valid ) {
		wp_die( $error );
	}

	do_action( 'one_time_login_url_logged_in', $user );
	update_user_meta( $user->ID, 'one_time_login_url_token', $tokens );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );
	wp_safe_redirect( admin_url() );
	exit;
}
add_action( 'init', 'one_time_login_url_handle_token' );


/**
 * administrator menu 
 * @TODO
 */
/*
function one_time_login_url_create_menu(){
	add_menu_page('OneTimeLoginUrl Plugin Setting','OneTimeLoginUrl','administrator', __FILE__, 'one_time_login_url_settings_page',plugins_url('/images/icon.png', __FILE__));
	// created function is callback function
	add_action('admin_init', 'one_time_login_url_register_settings');
}
add_action( 'admin_menu' , 'one_time_login_url_create_menu' );

// callback
function one_time_login_url_register_settings(){
	register_setting('one-time-login-url-setting-group','new_option_name');
	register_setting('one-time-login-url-setting-group','other_option_name');
	register_setting('one-time-login-url-setting-group','option_etc');
}

// load administrator view
require_once(__DIR__ . '/one-time-login-url-admin.php');
*/
