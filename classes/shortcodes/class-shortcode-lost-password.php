<?php
class MPT_Shortcode_Lost_Password extends MPT_Shortcode {

	/**
	 * All about the registration shortcode
	 */
	public function __construct( ) {
		add_shortcode( 'member-lost-password', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'init' ), 12 );
	}

	public static function shortcode( ) {
		// User logged-in ?
		if ( mpt_is_member_logged_in() ) {
			return '<!-- Members logged-in, impossible to reset password. -->';
		}
		
		if ( isset( $_GET['mpt-action'] ) && $_GET['mpt-action'] == 'lost-password' ) {
			return parent::load_template( 'member-lost-password-step-2' );
		} else {
			// Get user_login
			$user_login = isset( $_POST['user_login'] ) ? stripslashes( $_POST['user_login'] ) : '';

			// Default message
			if ( !isset( $_POST ) ) {
				parent::set_message( 'info', __( 'Please enter your username or email address. You will receive a link to create a new password via email.' ), 'notice' );
			}

			return parent::load_template( 'member-lost-password-step-1' );
		}
	}

	public static function init( ) {
		// Ask link reset
		self::check_step_1( );

		// Check link reset and form new password
		self::check_step_2_url( );
		self::check_step_2_form( );
	}

	/**
	 * Check POST data for email
	 *
	 * @return void
	 * @author Benjamin Niess
	 */
	public static function check_step_1( ) {
		if ( isset( $_POST['mptlostpwd_s1'] ) ) {
			// Cleanup data
			$_POST['mptlostpwd_s1'] = stripslashes_deep( $_POST['mptlostpwd_s1'] );
			
			// Check _NONCE
			$nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
			if ( !wp_verify_nonce( $nonce, 'mptlostpwd_s1' ) ) {
				parent::set_message( 'check-nonce', 'Security check failed', 'error' );
				return false;
			}

			// Empty values ?
			if ( empty( $_POST['mptlostpwd_s1']['username'] ) ) {
				parent::set_message( 'check_step_1', __( 'Invalid username or e-mail.', 'mpt' ), 'error' );
				return false;
			}

			// Try find user
			$user = new MPT_User( );

			// Test if @
			if ( strpos( $_POST['mptlostpwd_s1']['username'], '@' ) !== false ) {
				$user->fill_by( 'email', $_POST['mptlostpwd_s1']['username'] );
			} else {
				$user->fill_by( 'username', $_POST['mptlostpwd_s1']['username'] );
			}

			// No response for email and username, go out
			if ( !$user->exists( ) ) {
				parent::set_message( 'check_step_1', __( 'No user with this value.', 'mpt' ), 'error' );
				return false;
			}

			// Send reset link
			$user->reset_password_link( );

			parent::set_message( 'check_step_1', __( "You are going to receive an email with a reset link.", 'mpt' ), 'success' );
			return true;
		}

		return false;
	}

	/**
	 * Check if user click on reset link, verify key/id on DB
	 *
	 * @author Benjamin Niess
	 */
	public static function check_step_2_url( ) {
		if ( !isset( $_GET['mpt-action'] ) || $_GET['mpt-action'] != 'lost-password' ) {
			return false;
		}

		if ( !isset( $_GET['id'] ) || !isset( $_GET['key'] ) || empty( $_GET['id'] ) || empty( $_GET['key'] ) ) {
			wp_die( __( 'The link you clicked seems to be broken. Please contact the administrator of the site', 'mpt' ) );
		}
		
		// Try load user with this activation_key
		$user = new MPT_User( );
		$user->fill_by( 'user_activation_key', $_GET['key'] );
		if ( !$user->exists() || ($user->exists() && $user->id != $_GET['id']) ) {
			wp_die(__('Cheatin&#8217; uh?', 'mpt'));
		}
		
		return true;
	}
	
	/**
	 * Check form new password
	 */
	public static function check_step_2_form( ) {
		if ( isset( $_POST['mptlostpwd_s2'] ) ) {
			// Check _NONCE
			$nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
			if ( !wp_verify_nonce( $nonce, 'mptlostpwd_s2' ) ) {
				parent::set_message( 'check-nonce', 'Security check failed', 'error' );
				return false;
			}
			
			// Check if passwords are the same
			if ( $_POST['mptlostpwd_s2']['password'] != $_POST['mptlostpwd_s2']['password_confirmation'] ) {
				parent::set_message( 'check_step_2', __( 'The two passwords you entered don\'t match.', 'mpt' ), 'error' );
				return false;
			}
			
			// Check password complexity
			if( strlen($_POST['mptlostpwd_s2']['password']) < 6 ) { // TODO: Hooks and function for test password security
				parent::set_message( 'check_step_2', __('You password need to be at least 6 characters long', 'mpt'), 'error' );
				return false;
			}
			
			// Try load user with this activation_key
			$user = new MPT_User( );
			$user->fill_by( 'user_activation_key', $_GET['key'] );
			if ( !$user->exists() || ($user->exists() && $user->id != $_GET['id']) ) {
				wp_die(__('Cheatin&#8217; uh?', 'mpt'));
			}
			
			// reset the user password
			$user->set_password($_POST['mptlostpwd_s2']['password']);
			
			// Redirect to login page
			// TODO: Dynamic pages
			wp_redirect( home_url('/') );
			exit();
		}
	}

}
