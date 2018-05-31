<?php
/*
Plugin Name: Stackoverflow Stats
Plugin URI: https://tobier.de/stackoverflow
Description: Displays your Stackoverflow statistics. This Plugin gets every day your stackoverflow data from there api and saves it to a database.
Version: 1.0
Author: Tobias Keller
Author URI: https://tobier.de
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: stackoverflowStats
Domain Path: /languages
*/

register_activation_hook( __FILE__, 'onActivation' );
register_deactivation_hook(__FILE__, 'ondeactivation');
register_uninstall_hook(__FILE__, 'onUninstall');

$startStackoverflow = new stackoverflowStats();
class stackoverflowStats {
	public function __construct() {
		add_action('getStackoverflowDataCron', array($this, 'getProfileData'));
		add_action('admin_menu', array($this, 'createBackendMenu'));
		add_shortcode( 'stackoverflowStats', array($this, 'shortCodeTemplate') );
	}

	/*
	 * Add Site to Backend Menu
	 * */
	public function createBackendMenu(){
		add_menu_page( 'Stackoverflow', 'Stackoverflow', 'manage_options', 'stackoverflowStats', array($this, 'settingsPage') );
	}

	/*
	 * Backend Settings Page Template
	 * */
	public function settingsPage(){

		if (isset($_POST['so_save'])) {
			$retrievedNonce = $_REQUEST['_wpnonce'];
			if (wp_verify_nonce($retrievedNonce, 'saveStackoverflowSettings' )) {
				update_option('stackoverflowUser', esc_html($_POST['so_id']));
			}
		}

		if (isset($_POST['getData'])) {
			$retrievedNonce = $_REQUEST['_wpnonce'];
			if (wp_verify_nonce($retrievedNonce, 'getStackoverflowData' )) {
				$this->getProfileData();
			}
		}

		$getSOdata = get_option('StackoverflowData');
		?>
		<div class="wrap">
			<h3><?php _e('Stackoverflow Statistics Settings', 'stackoverflowStats'); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field('saveStackoverflowSettings'); ?>
				<label><?php _e('Your Stackoverflow ID', 'stackoverflowStats'); ?></label><br>
				<input type="text" name="so_id" value="<?php echo get_option("stackoverflowUser"); ?>" placeholder="Your Stackoverflow ID">
				<button type="submit" class="button-primary" name="so_save" value="save"><?php _e('Save', 'stackoverflowStats'); ?></button>
			</form>

			<?php
			if($getSOdata){ ?>
				<p><?php _e('Last Update: ', 'stackoverflowStats'); echo esc_html($getSOdata["lastFetch"]); ?> </p>
			<?php }
			else { ?>
				<p><?php _e('Last Data-update: No-Data', 'stackoverflowStats'); ?> </p>
			<?php }

			if (get_option("stackoverflowUser")){
				?>
				<form method="post" action="">
					<?php wp_nonce_field('getStackoverflowData'); ?>
					<button type="submit" class="button-primary" name="getData" value="data"><?php _e('Get data from Stackoverflow', 'stackoverflowStats'); ?></button>
				</form>
				<?php
			}
			?>
		</div>
		<?php
	}

	/*
	 * Shortcode output
	 * */
	public function shortCodeTemplate(){
		$myData = get_option('StackoverflowData');
		if(!$myData){
			return "Stackoverflow - No Data";
		}

		?>
		<div id="%1$s" class="card mb-3 border-dark widget %2$s">
          <div class="card-header text-white bg-tobi">
	          <img src="<?php echo plugin_dir_url( __FILE__ ) . 'img/so-icon.png'; ?>" width="24" alt="stackoverflow" />
          Stackoverflow
          </div>
          <div class="card-body p-0">
	          <table class="table table-striped">
        	    <tbody>
                    <tr>
                        <th class="first">Reputation:</th>
                        <th><?php echo esc_html($myData["reputation"]); ?></th>
                    </tr>
                    <tr>
                        <th class="first">Gold Badges:</th>
                        <th><?php echo esc_html($myData["goldBadges"]); ?></th>
                    </tr>
                    <tr>
                        <th class="first">Silver Badges:</th>
                        <th><?php echo esc_html($myData["silverBadges"]); ?></th>
                    </tr>
                    <tr>
                        <th class="first">Bronze Badges:</th>
                        <th><?php echo esc_html($myData["bronzeBadges"]); ?></th>
                    </tr>
                </tbody>
            </table>
            <small style="padding-left: 15px;">Visit me on <a href="<?php echo esc_html($myData["profileLink"]); ?>" target="_blank">Stackoverflow</a></small>
        </div>
        </div>
		<?php
	}

	/*
	 * Calls the stackexchange api to get the data
	 * */
	public function getProfileData(){
		// Get stackoverflow id
		$userId = get_option('stackoverflowUser');

		// Get data from api
		$url ='https://api.stackexchange.com/2.2/users/' . $userId . '?site=stackoverflow';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		// Set so curl_exec returns the result instead of outputting it.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Get the response and close the channel.
		$response = json_decode(curl_exec($ch));
		curl_close($ch);

		$profileData = array(
			'profileLink'   => $response->items["0"]->link,
			'reputation'    => $response->items["0"]->reputation,
			'goldBadges'    => $response->items["0"]->badge_counts->gold,
			'silverBadges'  => $response->items["0"]->badge_counts->silver,
			'bronzeBadges'  => $response->items["0"]->badge_counts->bronze,
			'lastFetch' => date('d-m-Y H:m:s')
		);
		// Update data
		update_option('StackoverflowData', $profileData);
	}

}

/*
* add options on activation
* */
function onActivation(){
	add_option( 'stackoverflowUser', '', '', 'yes' );
	add_option( 'StackoverflowData', '', '', 'yes' );

	if (! wp_next_scheduled ( 'getStackoverflowDataCron' )) {
		wp_schedule_event(time(), 'daily', 'getStackoverflowDataCron');
	}
}

/*
 * delete options on uninstall
 * */
function onUninstall(){
	delete_option( 'stackoverflowUser' );
	delete_option( 'StackoverflowData' );
}

/*
 * remove cron on deactivation*/
function ondeactivation() {
	wp_clear_scheduled_hook('getStackoverflowDataCron');
}