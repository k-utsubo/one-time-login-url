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
 * [--expire-date=<datestring>]
 * : Delete existing tokens at expire-date. if not seted ,token will delete immediately.
 * ---
 * default: 
 * ---
 *
 * [--from-date=<datestring>]
 * : Validity period start date time. 
 * ---
 * default: 
 * ---
 *
 * [--to-date=<datestring>]
 * : Validity period end date time. 
 * ---
 * default: 
 * ---
 *
 * [--redirect=<url>]
 * : After login , you will go this redirect page. if not seted, redirect to admin page.
 * ---
 * default: 
 * ---
 *
 * ## EXAMPLES
 *
 *     # Generate two one-time login URLs.
 *     $ wp user one-time-login-url testuser --count=2
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_url_token=ebe62e3
 *     http://wpdev.test/wp-login.php?user_id=2&one_time_login_url_token=eb41c77
 *
 *     $ wp user one-time-login-url testuser --from-date="2019-01-01T00:00:00"
 *     # this url is invalid by from-date, after from-date you can access this url. 
 *
 *     $ wp user one-time-login-url testuser --expire-date="2019-03-03" --to-date="2019-03-03"
 *     # token is expired at "2019-03-03" ,you can access many times up to "2019-03-03"
 *
 *     $ wp user one-time-login-url testuser --redirect=/?page_id=8
 */
function one_time_login_url_wp_cli_command( $args, $assoc_args ) {

	$fetcher = new WP_CLI\Fetchers\User;
	$user = $fetcher->get_check( $args[0] );
	//$delay_delete = WP_CLI\Utils\get_flag_value( $assoc_args, 'delay-delete' );
	$expire_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'expire-date' );
	$to_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'to-date' );
	$from_date = WP_CLI\Utils\get_flag_value( $assoc_args, 'from-date' );
	$redirect = WP_CLI\Utils\get_flag_value( $assoc_args, 'redirect' );
	$count = (int) $assoc_args['count'];
	$tokens = $new_tokens = array();

	echo  "to_date=".$to_date.",from_date=".$from_date.",expire_date=".$expire_date.",redirect=".$redirect."\n";
	if ( $to_date ) {
		if ( strlen($to_date)<=10){
			$to_date=$to_date."T23:59:59";
		}
	}else{
		$to_date = "2038-01-18T23:59:59";
	}
	echo "to_date=".$to_date."\n";
	if ( !strtotime($to_date) ){
		wp_die( "Invalid option : to-date\n" );
	}

	if ( $from_date ){
		if ( strlen($from_date)<=10){
			$from_date=$from_date."T00:00:00";
		}
	}else{
		$from_date = "2000-01-01T00:00:00";
	}
	if ( !strtotime($from_date) ){
		wp_die( "Invalid option : from-date\n" );
	}

	if ( $expire_date ) {
		if ( strlen($expire_date)<=10){
			$expire_date=$from_date."T23:59:59";
		}
		if ( ! strtotime($expire_date) ){
			wp_die( "Invalid option : expire-date\n" );
		}
//		$tokens = get_user_meta( $user->ID, 'one_time_login_url_token', true );
//		$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
//		wp_schedule_single_event( strtotime($expire_date) , 'one_time_login_url_cleanup_expired_tokens', array( $user->ID, $tokens ) );
	}

//	if ( $delay_delete ) {
//		$tokens = get_user_meta( $user->ID, 'one_time_login_token', true );
//		$tokens = is_string( $tokens ) ? array( $tokens ) : $tokens;
//		wp_schedule_single_event( time() + ( 15 * MINUTE_IN_SECONDS ), 'one_time_login_cleanup_expired_tokens', array( $user->ID, $tokens ) );
//	}

	for ( $i = 0; $i < $count; $i++ ) {
		$password = wp_generate_password();
		$token = sha1( $password );
		$tokens[] = array("password"=>$token,"from_date"=>strtotime($from_date),"to_date"=>strtotime($to_date),"expire_date"=>strtotime($expire_date),"redirect"=>$redirect);
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
	_log("expired_tokens,user_id=".$user_id);
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
	$time=time();
	foreach ( $tokens as $i => $token ) {
		_log("expire_date=".$token["expire_date"]);
		_log("expire_date=".strftime('%Y-%m-%d %H:%M:%S',$token["expire_date"]));
		_log("time=".strftime('%Y-%m-%d %H:%M:%S',$time));
		if($token["expire_date"]<$time){
			_log("unset1");
			unset($tokens[ $i ]);
			continue;
		}
		if($token["to_date"]<$time){
			continue;
		}

		if ( hash_equals( $token["password"], $_GET['one_time_login_url_token'] ) and $token["from_date"]<=$time and $time<=$token["to_date"]) {
			$is_valid = true;
			if( $token["expire_date"]==0){
				_log("unset2");
				unset( $tokens[ $i ] );
			}
			break;
		}
	}

	update_user_meta( $user->ID, 'one_time_login_url_token', $tokens );

	if ( ! $is_valid ) {
		wp_die( $error );
	}

	_log("tokens=".var_dump($tokens));
	do_action( 'one_time_login_url_logged_in', $user );
	//update_user_meta( $user->ID, 'one_time_login_url_token', $tokens );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );

	_log("admin_url=". admin_url());
	_log("site_url=". site_url());
	_log("redirect=".$token["redirect"]);
	if ( $token["redirect"] ){
		wp_safe_redirect( site_url().$token["redirect"] );
	}else{
		wp_safe_redirect( admin_url() );
	}
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
