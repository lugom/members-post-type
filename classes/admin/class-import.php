<?php
class MPT_Admin_Import {
	private static $_rapport_arr = array();
	const option_name = "mpt_last_import_report";
	
	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__ , 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__ , 'admin_init' ) );
	}
	
	public static function admin_menu() {
		$hook = add_submenu_page('edit.php?post_type=member', 'Import / Export members', 'Import / Export members', 'manage_options', 'member-import-export', array( __CLASS__, 'page' ));
		add_action( 'admin_head-'.$hook, array( __CLASS__ , 'admin_head' ) );
	}
	
	public static function admin_head() {
		wp_enqueue_style ( MPT_CPT_NAME . '-post', MPT_URL . 'assets/css/admin.css', array( ), MPT_VERSION, 'all' );
	}
	
	public static function page() {
		$report = get_option( self::option_name );
		
		include (MPT_DIR . 'views/admin/page-import-export.php');
	}
	
	public static function admin_init( ) {
		if( isset( $_POST['mpt_action'] ) && $_POST['mpt_action'] == 'mpt_import_action' ) {
			self::admin_init_import( );
		}
		
		return false;
	}
	
	public static function admin_init_import() {
		// Check the nonce
		check_admin_referer('import-members');
		
		// If we have a file
		if( !isset( $_FILES['csv-file'] ) ) {
			return false;
		}
		
		self::$_rapport_arr = array(
			'report_date' => time(),
			'ignore_line' => array(),
			'import_status' => array(),
		);
		$csv = self::load_csv( $_FILES['csv-file']['tmp_name'], true );
		self::insert_members($csv);
		
		// Save last report
		return update_option( self::option_name, self::$_rapport_arr );
	}
	
	/**
	 * Load a CSV file.
	 * 
	 * @param string $file path to the csv file.
	 * @param bool $has_header ignore the first line if it's the header.
	 * @return array an array containing all the line.
	 */
	private static function load_csv( $file, $has_header = false ) {
		$csv = array();
		$current_line = 1; // use to track current line of the CSV file in case of error
		
		$handle = fopen($file ,'r');
		while ( ($data = fgetcsv($handle) ) !== FALSE ) {
			// If the first line is the header, ignore it.
			if( $has_header ) {
				$has_header = false;
				continue;
			}
			$tmp = explode(";", $data[0]);
			
			// If the email of the username are empty, abord and continue with the next line.
			if( empty($tmp[0]) || empty($tmp[3]) ) {
				self::$_rapport_arr['ignore_line'][] = array( 'line' => $current_line, 'content' => utf8_encode($data[0]), 'operation' => __('missing email and/or username', 'mpt'), 'status' => 'error' );
				$current_line++;
				continue;
			}
			
			$csv[] = array (
				'email' => $tmp[0],
				'lastname' => utf8_encode($tmp[1]),
				'firstname' => utf8_encode($tmp[2]),
				'username' => utf8_encode($tmp[3]),
				'counter' => utf8_encode($tmp[4]),
				'lastvisit' => utf8_encode($tmp[5]),
			);
			
			$current_line++;
		}
		fclose($handle);
		
		return $csv;
	}
	
	/**
	 * Insert/update the members.
	 * 
	 * @param array $csv an array containing the CSV line.
	 */
	public static function insert_members( $csv ) {
		if( empty( $csv ) ) {
			return false;
		}
		
		foreach( $csv as $member ) {
			
			$tmp_member = new MPT_Member();
			$tmp_member->fill_by('email', $member['email']);
			
			if( $tmp_member->exists() ) {
				$tmp_member->set_meta_value('last_name', $member['lastname']);
				$tmp_member->set_meta_value('first_name', $member['firstname']);
				$tmp_member->set_meta_value('username', $member['username']);
				$tmp_member->set_meta_value('_counter_sign_on', $member['counter']);
				$tmp_member->set_meta_value('_last_sign_on_date', $member['lastvisit']);
				$tmp_member->regenerate_post_title();
				
				self::$_rapport_arr['import_status'][] = array( 'member' => $member['email'], 'operation' => 'updated', 'status' => 'success' );
			} else {
				$args = array();
				$args['password'] 	= wp_generate_password( 8 );
				$args['username'] 	= sanitize_text_field( $member['username'] );
				$args['email'] 		= sanitize_email( $member['email'] );
				$args['first_name'] = sanitize_text_field( $member['firstname'] );
				$args['last_name'] 	= sanitize_text_field( $member['lastname'] );
				
				// insert member
				$member_id = MPT_Member_Utility::insert_member( $args );
				
				$tmp_member = new MPT_Member();
				$tmp_member->fill_by('id', $member_id);
				if( $tmp_member->exists() ) {
					
					// Send a mail to the new registered user.
					$message  = sprintf(__('Account creation for [%s] :', 'mpt'), get_bloginfo( 'name' )) . "\r\n";
					$message .= sprintf(__('Name: %s %s', 'mpt'), $args['last_name'], $args['first_name']) . "\r\n";
					$message .= sprintf(__('Username: %s', 'mpt'), $args['username']) . "\r\n";
					$message .= sprintf(__('Password: %s', 'mpt'), $args['password']) . "\r\n";
					$message .= mpt_get_login_permalink() . "\r\n";
					@wp_mail($args['email'], sprintf(__('[%s] Your username and password', 'mpt'), get_bloginfo( 'name' )), $message);
					
					self::$_rapport_arr['import_status'][] = array( 'member' => $member['email'], 'operation' => 'created', 'status' => 'success' );
				} else {
					self::$_rapport_arr['import_status'][] = array( 'member' => $member['email'], 'operation' => 'created', 'status' => 'error' );
				}
			}
		}
		
		return true;
	}
}