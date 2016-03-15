<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
/*
Plugin Name:    Country Specific Menu Items
Description:    Control the visibility of individual menu items based on a user's country.
Author:         Ryan Stutzman
Version:        1.0.2
Author URI:     http://stutzman.asia/
*/

/* Prevent plugin activation and send a notice if other SMI plugin is active. */
add_action( 'admin_notices', 'csmi_admin_notice' );
add_action( 'network_admin_notices', 'csmi_admin_notice' ); // also show message on multisite
function csmi_admin_notice() {
	if ( class_exists ( 'City_Specific_Menu_Items' ) ){
		global $pagenow;
		if ( $pagenow == 'plugins.php' ){
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

add_action('init','csmi_start_session');
function csmi_start_session() {
    if(!session_id()) {
        session_start();
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
	    ob_start(); ?>
			<p class="field-visibility description description-wide">
				<label for="edit-menu-item-visibility-<?php echo $item_id; ?>">
					<?php echo 'Set Visibility' ?>
					<script type="text/javascript">
					    jQuery(document).ready(function($){ 
					        $(".chzn-select").chosen();
					    });
					</script>
					<select name="menu-item-visibility[<?php echo $item_id; ?>][]" id="edit-menu-item-visibility-<?php echo $item_id; ?>" class="chzn-select" multiple="true" data-placeholder="Select Countries">
						<option value="AF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Afghanistan</option>
						<option value="AL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Albania</option>
						<option value="DZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Algeria</option>
						<option value="AS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >American Samoa</option>
						<option value="AD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Andorra</option>
						<option value="AO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Angola</option>
						<option value="AI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Anguilla</option>
						<option value="AQ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AQ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Antarctica</option>
						<option value="AG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Antigua and Barbuda</option>
						<option value="AR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Argentina</option>
						<option value="AM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Armenia</option>
						<option value="AW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Aruba</option>
						<option value="AU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Australia</option>
						<option value="AT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Austria</option>
						<option value="AZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Azerbaijan</option>
						<option value="BS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bahamas</option>
						<option value="BH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bahrain</option>
						<option value="BD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bangladesh</option>
						<option value="BB" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BB", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Barbados</option>
						<option value="BY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Belarus</option>
						<option value="BE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Belgium</option>
						<option value="BZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Belize</option>
						<option value="BJ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BJ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Benin</option>
						<option value="BM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bermuda</option>
						<option value="BT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bhutan</option>
						<option value="BO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bolivia</option>
						<option value="BA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bosnia and Herzegowina</option>
						<option value="BW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Botswana</option>
						<option value="BV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bouvet Island</option>
						<option value="BR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Brazil</option>
						<option value="IO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >British Indian Ocean Territory</option>
						<option value="BN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Brunei Darussalam</option>
						<option value="BG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Bulgaria</option>
						<option value="BF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Burkina Faso</option>
						<option value="BI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "BI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Burundi</option>
						<option value="KH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cambodia</option>
						<option value="CM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cameroon</option>
						<option value="CA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Canada</option>
						<option value="CV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cape Verde</option>
						<option value="KY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cayman Islands</option>
						<option value="CF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Central African Republic</option>
						<option value="TD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Chad</option>
						<option value="CL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Chile</option>
						<option value="CN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >China</option>
						<option value="CX" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CX", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Christmas Island</option>
						<option value="CC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cocos (Keeling) Islands</option>
						<option value="CO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Colombia</option>
						<option value="KM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Comoros</option>
						<option value="CG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Congo</option>
						<option value="CD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Congo, the Democratic Republic of the</option>
						<option value="CK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cook Islands</option>
						<option value="CR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Costa Rica</option>
						<option value="CI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cote d'Ivoire</option>
						<option value="HR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Croatia (Hrvatska)</option>
						<option value="CU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cuba</option>
						<option value="CY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Cyprus</option>
						<option value="CZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Czech Republic</option>
						<option value="DK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Denmark</option>
						<option value="DJ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DJ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Djibouti</option>
						<option value="DM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Dominica</option>
						<option value="DO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Dominican Republic</option>
						<option value="TP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >East Timor</option>
						<option value="EC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "EC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Ecuador</option>
						<option value="EG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "EG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Egypt</option>
						<option value="SV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >El Salvador</option>
						<option value="GQ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GQ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Equatorial Guinea</option>
						<option value="ER" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ER", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Eritrea</option>
						<option value="EE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "EE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Estonia</option>
						<option value="ET" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ET", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Ethiopia</option>
						<option value="FK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Falkland Islands (Malvinas)</option>
						<option value="FO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Faroe Islands</option>
						<option value="FJ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FJ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Fiji</option>
						<option value="FI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Finland</option>
						<option value="FR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >France</option>
						<option value="FX" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FX", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >France, Metropolitan</option>
						<option value="GF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >French Guiana</option>
						<option value="PF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >French Polynesia</option>
						<option value="TF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >French Southern Territories</option>
						<option value="GA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Gabon</option>
						<option value="GM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Gambia</option>
						<option value="GE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Georgia</option>
						<option value="DE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "DE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Germany</option>
						<option value="GH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Ghana</option>
						<option value="GI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Gibraltar</option>
						<option value="GR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Greece</option>
						<option value="GL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Greenland</option>
						<option value="GD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Grenada</option>
						<option value="GP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guadeloupe</option>
						<option value="GU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guam</option>
						<option value="GT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guatemala</option>
						<option value="GN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guinea</option>
						<option value="GW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guinea-Bissau</option>
						<option value="GY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Guyana</option>
						<option value="HT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Haiti</option>
						<option value="HM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Heard and Mc Donald Islands</option>
						<option value="VA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Holy See (Vatican City State)</option>
						<option value="HN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Honduras</option>
						<option value="HK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Hong Kong</option>
						<option value="HU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "HU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Hungary</option>
						<option value="IS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Iceland</option>
						<option value="IN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >India</option>
						<option value="ID" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ID", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Indonesia</option>
						<option value="IR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Iran (Islamic Republic of)</option>
						<option value="IQ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IQ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Iraq</option>
						<option value="IE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Ireland</option>
						<option value="IL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Israel</option>
						<option value="IT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "IT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Italy</option>
						<option value="JM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "JM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Jamaica</option>
						<option value="JP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "JP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Japan</option>
						<option value="JO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "JO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Jordan</option>
						<option value="KZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Kazakhstan</option>
						<option value="KE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Kenya</option>
						<option value="KI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Kiribati</option>
						<option value="KP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Korea, Democratic People's Republic of</option>
						<option value="KR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Korea, Republic of</option>
						<option value="KW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Kuwait</option>
						<option value="KG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Kyrgyzstan</option>
						<option value="LA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Lao People's Democratic Republic</option>
						<option value="LV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Latvia</option>
						<option value="LB" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LB", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Lebanon</option>
						<option value="LS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Lesotho</option>
						<option value="LR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Liberia</option>
						<option value="LY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Libyan Arab Jamahiriya</option>
						<option value="LI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Liechtenstein</option>
						<option value="LT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Lithuania</option>
						<option value="LU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Luxembourg</option>
						<option value="MO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Macau</option>
						<option value="MK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Macedonia, The Former Yugoslav Republic of</option>
						<option value="MG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Madagascar</option>
						<option value="MW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Malawi</option>
						<option value="MY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Malaysia</option>
						<option value="MV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Maldives</option>
						<option value="ML" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ML", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mali</option>
						<option value="MT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Malta</option>
						<option value="MH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Marshall Islands</option>
						<option value="MQ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MQ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Martinique</option>
						<option value="MR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mauritania</option>
						<option value="MU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mauritius</option>
						<option value="YT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "YT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mayotte</option>
						<option value="MX" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MX", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mexico</option>
						<option value="FM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "FM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Micronesia, Federated States of</option>
						<option value="MD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Moldova, Republic of</option>
						<option value="MC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Monaco</option>
						<option value="MN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mongolia</option>
						<option value="MS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Montserrat</option>
						<option value="MA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Morocco</option>
						<option value="MZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Mozambique</option>
						<option value="MM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Myanmar</option>
						<option value="NA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Namibia</option>
						<option value="NR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Nauru</option>
						<option value="NP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Nepal</option>
						<option value="NL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Netherlands</option>
						<option value="AN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Netherlands Antilles</option>
						<option value="NC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >New Caledonia</option>
						<option value="NZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >New Zealand</option>
						<option value="NI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Nicaragua</option>
						<option value="NE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Niger</option>
						<option value="NG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Nigeria</option>
						<option value="NU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Niue</option>
						<option value="NF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Norfolk Island</option>
						<option value="MP" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "MP", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Northern Mariana Islands</option>
						<option value="NO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "NO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Norway</option>
						<option value="OM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "OM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Oman</option>
						<option value="PK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Pakistan</option>
						<option value="PW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Palau</option>
						<option value="PA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Panama</option>
						<option value="PG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Papua New Guinea</option>
						<option value="PY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Paraguay</option>
						<option value="PE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Peru</option>
						<option value="PH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Philippines</option>
						<option value="PN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Pitcairn</option>
						<option value="PL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Poland</option>
						<option value="PT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Portugal</option>
						<option value="PR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Puerto Rico</option>
						<option value="QA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "QA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Qatar</option>
						<option value="RE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "RE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Reunion</option>
						<option value="RO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "RO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Romania</option>
						<option value="RU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "RU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Russian Federation</option>
						<option value="RW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "RW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Rwanda</option>
						<option value="KN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "KN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Saint Kitts and Nevis </option>
						<option value="LC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Saint LUCIA</option>
						<option value="VC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Saint Vincent and the Grenadines</option>
						<option value="WS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "WS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Samoa</option>
						<option value="SM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >San Marino</option>
						<option value="ST" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ST", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Sao Tome and Principe </option>
						<option value="SA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Saudi Arabia</option>
						<option value="SN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Senegal</option>
						<option value="SC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Seychelles</option>
						<option value="SL" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SL", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Sierra Leone</option>
						<option value="SG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Singapore</option>
						<option value="SK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Slovakia (Slovak Republic)</option>
						<option value="SI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Slovenia</option>
						<option value="SB" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SB", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Solomon Islands</option>
						<option value="SO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Somalia</option>
						<option value="ZA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ZA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >South Africa</option>
						<option value="GS" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GS", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >South Georgia and the South Sandwich Islands</option>
						<option value="ES" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ES", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Spain</option>
						<option value="LK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "LK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Sri Lanka</option>
						<option value="SH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >St. Helena</option>
						<option value="PM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "PM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >St. Pierre and Miquelon</option>
						<option value="SD" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SD", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Sudan</option>
						<option value="SR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Suriname</option>
						<option value="SJ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SJ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Svalbard and Jan Mayen Islands</option>
						<option value="SZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Swaziland</option>
						<option value="SE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Sweden</option>
						<option value="CH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "CH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Switzerland</option>
						<option value="SY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "SY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Syrian Arab Republic</option>
						<option value="TW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Taiwan, Province of China</option>
						<option value="TJ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TJ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tajikistan</option>
						<option value="TZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tanzania, United Republic of</option>
						<option value="TH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Thailand</option>
						<option value="TG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Togo</option>
						<option value="TK" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TK", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tokelau</option>
						<option value="TO" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TO", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tonga</option>
						<option value="TT" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TT", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Trinidad and Tobago</option>
						<option value="TN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tunisia</option>
						<option value="TR" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TR", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Turkey</option>
						<option value="TM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Turkmenistan</option>
						<option value="TC" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TC", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Turks and Caicos Islands</option>
						<option value="TV" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "TV", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Tuvalu</option>
						<option value="UG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "UG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Uganda</option>
						<option value="UA" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "UA", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Ukraine</option>
						<option value="AE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "AE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >United Arab Emirates</option>
						<option value="GB" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "GB", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >United Kingdom</option>
						<option value="US" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "US", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >United States</option>
						<option value="UM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "UM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >United States Minor Outlying Islands</option>
						<option value="UY" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "UY", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Uruguay</option>
						<option value="UZ" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "UZ", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Uzbekistan</option>
						<option value="VU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Vanuatu</option>
						<option value="VE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Venezuela</option>
						<option value="VN" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VN", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Viet Nam</option>
						<option value="VG" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VG", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Virgin Islands (British)</option>
						<option value="VI" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "VI", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Virgin Islands (U.S.)</option>
						<option value="WF" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "WF", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Wallis and Futuna Islands</option>
						<option value="EH" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "EH", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Western Sahara</option>
						<option value="YE" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "YE", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Yemen</option>
						<option value="YU" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "YU", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Yugoslavia</option>
						<option value="ZM" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ZM", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Zambia</option>
						<option value="ZW" <?php $val = get_post_meta( $item_id, 'locations', true ); if ( is_array( $val ) ) { if ( in_array( "ZW", $val ) ) { echo "selected='selected'"; } else { echo ""; } } ?> >Zimbabwe</option>
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
			if ( !isset( $new_meta_value ) ) {
				delete_post_meta( $menu_item_db_id, 'locations', $meta_value );
			}
			elseif ( $meta_value !== $new_meta_value ) {
				update_post_meta( $menu_item_db_id, 'locations', $new_meta_value );
			}
		}
	}

/* Put visibility settings in the database. */
	function csmi_update_visibility( $menu_id, $menu_item_db_id, $args ) {
		$meta_value = get_post_meta( $menu_item_db_id, 'hide_show', true );
		if ( isset( $_POST[ 'menu-item-show-hide' ][ $menu_item_db_id ] ) ) {
			$new_meta_value = $_POST[ 'menu-item-show-hide' ][ $menu_item_db_id ];
			if ( '' == $new_meta_value ) {
				delete_post_meta( $menu_item_db_id, 'hide_show', $meta_value );
			}
			elseif ( $meta_value !== $new_meta_value ) {
				update_post_meta( $menu_item_db_id, 'hide_show', $new_meta_value );
			}
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
				if ( is_readable ( $GeoIPv4_file ) ) {
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
				if ( is_readable ( $GeoIPv6_file ) ) {
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
				if ( !$visible || isset( $hidden_items[$item_parent] ) ) { // also hide the children of hidden items
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