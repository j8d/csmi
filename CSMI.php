<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/*
Plugin Name:    Country Specific Menu Items
Description:    Control the visibility of individual menu items based on a user's country.
Author:         Ryan Stutzman
Version:        1.0.3
Author URI:     http://stutzman.asia/
*/

/* Prevent plugin activation and send a notice if other SMI plugin is active. */
add_action( 'admin_notices', 'csmi_admin_notice' );
add_action( 'network_admin_notices', 'csmi_admin_notice' ); // also show message on multisite
function csmi_admin_notice() {
	if ( class_exists ( 'City_Specific_Menu_Items' ) ) {
		global $pagenow;
		if ( $pagenow == 'plugins.php' ) {
			deactivate_plugins ( 'location-specific-menu-items-by-country/CSMI.php' );
			if ( current_user_can( 'install_plugins' ) ) {
				echo '<div id="error" class="error notice is-dismissible"><p>Error. Please deactivate Country Specific Menu Items first and try again.</div>';
			}
		}
	}
}

/* Ask user to download GeoIP database files. */
add_action( 'admin_notices', 'csmi_dl_admin_notice' );
add_action( 'network_admin_notices', 'csmi_dl_admin_notice' ); // also show message on multisite
function csmi_dl_admin_notice() {
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir[ 'basedir' ] .'/geoip';
	if ( !file_exists( $dir ) ) {
	    wp_mkdir_p( $dir );
	}	
	$localfilev4 = $dir . '/GeoIPv4.dat';
	$localfilev6 = $dir . '/GeoIPv6.dat';
	$ignorefile = $dir . '/ignore.txt';
	$ctx = stream_context_create( array( 'http' => array( 'timeout' => 120 ) ) ); 
	if ( !file_exists( $localfilev4 ) && !file_exists( $ignorefile ) ) {
		if ( current_user_can( 'install_plugins' ) ) {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($){
			    $("#download").click(
			        function () {
			            $('#download-div').html("<p><img src='<?php echo plugin_dir_url( __FILE__ ) . 'assets/resources/spinner.gif';?>' alt='Please Wait...'/></p><p>Please wait while the Geolite database files download. Typically takes 10-15 seconds.</p>");
			        }
			    );
			 });
			</script>
			<?php echo
			'<div class="notice notice-warning is-dismissible" id="download-div"><p>Important: The CSMI plugin uses Maxmind Geolite databases for better speed and accuracy. Click "Download" to install these files now.</p>
			<p><form action="" method="get">
			<input type="submit" class="button" id="download" name="download" value="Download" />
			<input type="submit" id="ignore" name="ignore" value="ignore" style="border: 0; background-color: transparent; color: grey; font-size: x-small; vertical-align: bottom; text-align: right;"/>
			</p></div>';
			if ($_GET){
    			if ( isset( $_GET[ 'download' ] ) ) {
       				$newfilev4 = file_get_contents( "https://sourceforge.net/projects/geoipupdate/files/GeoIPv4.dat/download", 0, $ctx );
					file_put_contents( $dir . '/GeoIPv4.dat', $newfilev4 );
					if ( !file_exists( $localfilev6 ) ) {
						$newfilev6 = file_get_contents( "https://sourceforge.net/projects/geoipupdate/files/GeoIPv6.dat/download", 0, $ctx );
						file_put_contents( $dir . '/GeoIPv6.dat', $newfilev6 );
						echo '<meta http-equiv="refresh" content="0">';
						?>
					    <div class="notice notice-success is-dismissible">
					        <p><?php echo "Success!"; ?></p>
					    </div>
					    <?php
					}
				} elseif ( isset( $_GET[ 'ignore' ] ) ) {
					$ignorefile = fopen( $dir . "/ignore.txt", "w" );
					fclose( $ignorefile );
					echo '<meta http-equiv="refresh" content="0">';
					?>
				    <div class="notice notice-success is-dismissible">
				        <p><?php echo "Success!"; ?></p>
				    </div>
				    <?php
				}
			}
		}
	}
}

/* Add new cron interval of 30 days. */
add_filter( 'cron_schedules', 'csmi_cron_add_intervals' ); 
function csmi_cron_add_intervals( $schedules ) {
    $schedules[ 'thirtydays' ] = array(
        'interval' => 2592000,
        'display' => __( '30 Days' )
    );
    return $schedules;
}

/* Run csmi_geoip_update once every 30 days. */
add_action( 'csmi_udate_geoip_database', 'csmi_geoip_update' );
if ( !wp_next_scheduled( 'csmi_udate_geoip_database' ) ) {
    wp_schedule_event( time(), 'thirtydays', 'csmi_udate_geoip_database' );
}

/* Upate GeoIP database files. */
function csmi_geoip_update() {
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir[ 'basedir' ] . '/geoip';
	if ( !file_exists( $dir ) ) {
	    wp_mkdir_p( $dir );
	}	
	$localfilev4 = $dir . '/GeoIPv4.dat';
	$localfilev6 = $dir . '/GeoIPv6.dat';
	$ctx = stream_context_create( array( 'http' => array( 'timeout' => 120 ) ) ); 
	if ( file_exists( $localfilev4 ) ) {
		rename( $dir . '/GeoIPv4.dat', $dir . '/OLD_GeoIPv4.dat' );
		$newfilev4 = file_get_contents( "https://sourceforge.net/projects/geoipupdate/files/GeoIPv4.dat/download", 0, $ctx );
		file_put_contents( $dir . '/GeoIPv4.dat', $newfilev4 );
		unlink( $dir . '/OLD_GeoIPv4.dat');
	}
	if ( file_exists( $localfilev6 ) ) {
		rename( $dir . '/GeoIPv6.dat', $dir . '/OLD_GeoIPv6.dat' );
		$newfilev6 = file_get_contents( "https://sourceforge.net/projects/geoipupdate/files/GeoIPv6.dat/download", 0, $ctx );
		file_put_contents( $dir . '/GeoIPv6.dat', $newfilev6 );
		unlink( $dir . '/OLD_GeoIPv6.dat' );
	}
}

add_action( 'init', 'csmi_start_session' );
function csmi_start_session( ) {
    if( !session_id( ) ) {
        session_start( );
    }
}

/* Get one and only one instance of the class */
class Country_Specific_Menu_Items {
	private static $instance = null;
	public static function get_instance() {
		return null == self::$instance ? self::$instance = new self : self::$instance;
	}

	function __construct() {
		if( is_admin() ) {
			add_filter( 'wp_edit_nav_menu_walker', array( $this, 'csmi_edit_nav_menu_walker' ) );
			add_filter( 'wp_nav_menu_item_custom_fields', array( $this, 'csmi_settings' ), 12, 2 );
			add_action( 'wp_update_nav_menu_item', array( $this, 'csmi_update_locations' ), 10, 3 );
			add_action( 'wp_update_nav_menu_item', array( $this, 'csmi_update_visibility' ), 10, 3 );
			add_action( 'delete_post', array( $this,'csmi_remove_visibility_meta' ), 1, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'csmi_load_admin_script' ), PHP_INT_MAX );
		} else {
			add_filter( 'wp_get_nav_menu_items', array( $this, 'csmi_set_visibility' ), 10, 3 );
			add_action( 'init', array( $this, 'csmi_clear_gantry_menu_cache' ) );
		}
	}

	function csmi_load_admin_script() {
		// Deregister and dequeue other potentially conflicting stylesheets
	    wp_dequeue_style( 'chosencss' );
	    wp_deregister_style( 'chosencss' );
    	// Add stylesheets and javascript
		wp_register_style( 'chosencss', plugins_url( 'assets/resources/chosen.min.css', __FILE__ ), true, '', 'all' );
		wp_register_script( 'chosenjs', plugins_url( 'assets/resources/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), '', true );
		wp_enqueue_style( 'chosencss' );
		wp_enqueue_script( 'chosenjs' );
	}

	function csmi_edit_nav_menu_walker( $walker ) {
		require_once( dirname( __FILE__ ) . '/includes/walker-nav-menu-edit.php' );
		return 'CSMI_Walker_Nav_Menu_Edit';
	}

/* Show settings for each menu item in Menus admin page. */
	function csmi_settings( $fields, $item_id ) {
		require( dirname( __FILE__ ) . '/includes/countries.php' );
	    ob_start(); ?>
			<p class="field-visibility description description-wide">
				<label for="edit-menu-item-visibility-<?php echo $item_id; ?>">
					<?php echo 'Set Visibility' ?>
					<script type="text/javascript">
					    jQuery(document).ready(function($){ 
					        $(".chzn-select").chosen();
					    });
					</script>
					<select name="menu-item-visibility[<?php echo $item_id; ?>][]" id="edit-menu-item-visibility-<?php echo $item_id; ?>" class="chzn-select" multiple="true">
					<?php
					$vals = get_post_meta( $item_id, 'locations', true );
					foreach($countries as $key => $value) { 
					?>
						<option value="<?php echo $key;?>"<?php echo is_array( $vals ) && in_array( $key, $vals ) ? "selected='selected'" : ''; ?>> <?php echo $value;?> </option>
					<?php
					}
					?>
					</select>
			</p>
			<p class="field-visibility description description-wide">
					<input
					type="radio"
					id="edit-menu-item-visibility-<?php echo $item_id;?>"
					name="menu-item-show-hide[<?php echo $item_id; ?>]" 
					value="hide" <?php checked( get_post_meta( $item_id, 'hide_show', true ), 'hide', true ); ?>
					/>Hide from these countries.</br>
					<input
					type="radio"
					id="edit-menu-item-visibility-<?php echo $item_id; ?>"
					name="menu-item-show-hide[<?php echo $item_id; ?>]"
					value="show" <?php checked( get_post_meta( $item_id, 'hide_show', true ), 'show', true ); ?>
					/>Only show to these countries.</br>
				</label>
			</p>
		<?php
	    $fields[] = ob_get_clean();
	    return $fields;
	}

/* Put locations in the database. */
	function csmi_update_locations( $menu_id, $menu_item_db_id, $args ) {
		$meta_value = get_post_meta( $menu_item_db_id, 'locations', true );
		if ( isset( $_POST[ 'menu-item-visibility' ][ $menu_item_db_id ] ) ) { 
			$new_meta_value = $_POST[ 'menu-item-visibility' ][ $menu_item_db_id ];
		}
		if ( !isset($new_meta_value ) ) {
		delete_post_meta( $menu_item_db_id, 'locations', $meta_value );
		}
		elseif ( $meta_value !== $new_meta_value ) {
			update_post_meta( $menu_item_db_id, 'locations', $new_meta_value );
		}
	}

/* Put visibility settings in the database. */
	function csmi_update_visibility( $menu_id, $menu_item_db_id, $args ) {
		$meta_value = get_post_meta( $menu_item_db_id, 'hide_show', true );
		if ( isset( $_POST[ 'menu-item-show-hide' ][ $menu_item_db_id ] ) ) {
			$new_meta_value = $_POST[ 'menu-item-show-hide' ][ $menu_item_db_id ];
		}
		if ( $meta_value !== $new_meta_value ) {
			update_post_meta( $menu_item_db_id, 'hide_show', $new_meta_value );
		}
	}

/* Get user's country code from his or her IP address. */
	function csmi_get_country() {
		if( isset( $_SESSION[ 'user_country' ] ) && !current_user_can( 'manage_options' ) ) {
		    $user_country = $_SESSION[ 'user_country' ];
		} else {
			$upload_dir = wp_upload_dir();
			$dir = $upload_dir[ 'basedir' ] .'/geoip';
			$GeoIPv4_file = $dir . '/GeoIPv4.dat';
			$GeoIPv6_file = $dir . '/GeoIPv6.dat';		
			if ( !class_exists( 'GeoIP' ) ) {
				include_once( 'geoip.inc' );
			}
			if ( empty( $_SERVER[ "HTTP_X_FORWARDED_FOR" ] ) ) {
				$ip_address = $_SERVER[ "REMOTE_ADDR" ];
			} else {
				$ip_address = $_SERVER[ "HTTP_X_FORWARDED_FOR" ];
			}
			if ( !filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) === FALSE ) { 	
				if ( is_readable( $GeoIPv4_file ) ) {
					$gi = \CSMIGeoIP\geoip_open( $GeoIPv4_file, GEOIP_STANDARD );
					$user_country = \CSMIGeoIP\geoip_country_code_by_addr( $gi, $ip_address );
					\CSMIGeoIP\geoip_close( $gi );
				} else {
					$user_location = @unserialize( file_get_contents( 'http://ip-api.com/php/' . $ip_address ) );
					if ( $user_location && $user_location[ 'status' ] == 'success') {
						$user_country = $user_location[ 'countryCode' ];
					} else {
						$user_country = "Can't locate IP: " . $ip_address;
					}
				}
			} elseif ( !filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) === FALSE ) {
				if ( is_readable( $GeoIPv6_file ) ) {
					$gi = \CSMIGeoIP\geoip_open( $GeoIPv6_file, GEOIP_STANDARD );
					$user_country = \CSMIGeoIP\geoip_country_code_by_addr_v6( $gi, $ip_address );
					\CSMIGeoIP\geoip_close( $gi );
				} else {
					$user_location = @json_decode( file_get_contents( "http://www.geoplugin.net/json.gp?ip=" . $ip_address ) );
		    		if ( @strlen( trim( $user_location->geoplugin_countryCode ) ) == 2) {
		        	$user_country = @$user_location->geoplugin_countryCode;
		    		} else {
						$user_country = "Can't locate IP: " . $ip_address;
					}
				}
			} else {
				$user_country = "Can't locate IP: " . $ip_address;				
			}
		}
		$_SESSION['user_country'] = $user_country;
		return $user_country;
	}

/* Check menu items for their visibility settings, compare with user's location, and show or hide them accordingly. */
	function csmi_set_visibility( $items, $menu, $args ) {
		$user_country = $this->csmi_get_country();
		foreach( $items as $key => $item ) {
			$hidden_items = array();
			$item_parent = get_post_meta( $item->ID, '_menu_item_menu_item_parent', true );
			$selected_locations = get_post_meta( $item->ID, 'locations', true );
			if ( is_string( $selected_locations ) ) { $selected_locations = str_split( $selected_locations ); }
				$hide_show = get_post_meta( $item->ID, 'hide_show', true );
				if ( $hide_show == 'show' && in_array( $user_country, $selected_locations ) ) {
					$visible = true;
				}
				elseif ( $hide_show == 'show' && !in_array( $user_country, $selected_locations ) ) {
					$visible = false;
				}
				elseif ( $hide_show == 'hide' && in_array( $user_country, $selected_locations ) ) {
					$visible = false;
				}
				else {
					$visible = true;
				}
				if ( !$visible || isset( $hidden_items[ $item_parent ] ) ) { // also hide the children of hidden items
					unset( $items[ $key ] );
					$hidden_items[ $item->ID ] = '1';	
				}
		}
		return $items;
	}

/* Remove the _menu_item_visibility meta when the menu item is removed. */
	function csmi_remove_visibility_meta( $post_id ) {
		if ( is_nav_menu_item( $post_id ) ) {
			delete_post_meta( $post_id, 'locations' );
		}
	}

/* Fix for Gantry Framework */
	function csmi_clear_gantry_menu_cache() {
		if ( class_exists( 'GantryWidgetMenu' ) ) {
			GantryWidgetMenu::clearMenuCache();
		}
	}
}
Country_Specific_Menu_Items::get_instance();