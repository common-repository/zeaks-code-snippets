<?php
/*
Plugin Name: Zeaks Snippets
Plugin URI: http://zeaks.org/zeaks-code-snippets-plugin/
Description: Highlights code snippets use [code] [/code] Based on Simple Code Snippets by http://dannyvankooten.com
Version: 1.0
Author: Scott Dixon 
Author URI: http://zeaks.org
License: GPL2
*/
function pluginUrl() {
	//Try to use WP API if possible, introduced in WP 2.6
	if (function_exists('plugins_url')) return trailingslashit(plugins_url(basename(dirname(__FILE__))));

	//Try to find manually... can't work if wp-content was renamed or is redirected
	$path = dirname(__FILE__);
	$path = str_replace("\\","/",$path);
	$path = trailingslashit(get_bloginfo('wpurl')) . trailingslashit(substr($path,strpos($path,"wp-content/")));
	return $path;
}

// Necessary to display the shortcode in comments
	add_filter('comment_text', 'do_shortcode');

// Start syntax highlighter
class zeaks_Snippets {

	function __construct()
	{
		remove_filter('the_content','wpautop');
		add_filter('the_content','wpautop',99);
		add_action('wp_print_styles',array(&$this,'add_stylesheet'));
		add_shortcode('code', array(&$this,'replace_code'));
		add_filter('the_excerpt_rss',array(&$this,'strip_shortcodes'));
		add_filter('the_content_rss',array(&$this,'strip_shortcodes'));
		add_filter('the_excerpt',array(&$this,'strip_shortcodes'),1);
		add_filter('the_content',array(&$this,'strip_shortcodes'),99);

	}
	
	function replace_code($atts,$content)
	{
		if(version_compare(PHP_VERSION,'5.2.3')== -1) {
			$content ='<pre class="snippet-code">'.htmlspecialchars($content,ENT_NOQUOTES,'UTF-8').'</pre>';
		} else {
			$content ='<pre class="snippet-code">'.htmlspecialchars($content,ENT_NOQUOTES,'UTF-8',false).'</pre>';
		}
		
		return $content;
	}
	
	function add_stylesheet()
	{
		/*wp_enqueue_style('snippet_style', pluginUrl() . 'css/snippet-style.css');*/
	}
	
	function strip_shortcodes($content)
	{
		$content=str_replace('[code]','<pre>',$content);
		$content=str_replace('[/code]','</pre>',$content);
		return $content;
	}

}
$zeaks_Snippets = new zeaks_Snippets();


class Zeaks_WP_Options{
	var $options = array();
	var $defaults = array();
	
	/*
	 * Constructor
	 *
	 * Fired at Wordpress after_setup_theme (see add_action at the end
	 * of the class), registers the theme capabilities
	 * $this->options is used to store all the theme options, while
	 * $this->defaults holds their default values.
	 *
	 */
	 
	 function __construct() {

		// Default options, lower-level ones are added during first run
		
		$this->defaults = array(
			'color-scheme' => 'default',
		);
	
		// Load options (calls get_option())
		$this->load_options();
		
		/*
		 * Actions
		 *
		 * Registers registers admin settings, fires
		 * a firstrun during admin init, registers a plugin deactivation hook,
		 * adds the menu options, fires a welcome notice.
		 *
		 */
		add_action( 'admin_init', array( &$this, 'register_admin_settings' ) );
		add_action( 'admin_init', array( &$this, 'firstrun' ) );
		add_action( 'switch_theme', array( &$this, 'deactivate' ) );
		add_action( 'admin_menu', array( &$this, 'add_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'color_scheme_scripts' ) );
		
	}
	
	/*
	 * Load Options
	 *
	 * Fired during theme setup, loads all the options into $options
	 * array accessible from all other functions.
	 *
	 */
	function load_options(){
		$this->options = (array) get_option( 'ZSoptions-options' );
		$this->options = array_merge( $this->defaults, $this->options );
	}
	
	/*
	 * Save Options
	 *
	 * Calls the update_option and saves the current $options
	 * array. Call this after modifying the values of $this->options.
	 *
	 */
	function update_options() {
		return update_option( 'ZSoptions-options', $this->options );
	}
	
	/*
	 * Theme Deactivation
	 *
	 * Remove all the options after theme deactivation. This includes
	 * color scheme and all the rest, let's be nice and keep the database clean
	 *
	 */
	function deactivate() {
		delete_option( 'ZSoptions-options' );
	}
	
	/*
	 * First Run
	 *
	 * This method is fired on every call, which is why it checks the 
	 * $options array to see if the plugin was activated to make sure this
	 * runs only once. Populates the $options array with defaults and a few
	 * mandatory options.
	 *
	 */
	function firstrun() {
		if( ! isset( $this->options['activated'] ) || ! $this->options['activated'] ) {
			$this->options = $this->defaults;
			
			// Mandatory options during first run
			$this->options['options-visited'] = false;
			$this->options['activated'] = true;
			
			// Update the options.
			$this->update_options();
		}
	}
	
	/*
	 * Valid Color Schemes
	 *
	 * This function returns an array of available color schemes, where
	 * an array key is the value used in the database and the HTML layout,
	 * and value is used for captions. The function is used for plugin settins
	 * page as well as options validation. Default is blue.
	 *
	 */
	function get_valid_color_schemes() {
		$color_schemes = array(
			'default' => array(
				'name' => __( 'Default', 'ZSoptions' ),
				'preview' => pluginUrl() . 'colors/default/preview-default.png'
			),
			'dark' => array(
				'name' => __( 'Dark', 'ZSoptions' ),
				'preview' => pluginUrl() . 'colors/dark/preview-dark.png'
			),						
			'black' => array(
				'name' => __( 'Black', 'ZSoptions' ),
				'preview' => pluginUrl() . 'colors/black/preview-black.png'
			),
			'zeaks' => array(
				'name' => __( 'Zeaks.org', 'ZSoptions' ),
				'preview' => pluginUrl() . 'colors/zeaks/preview-zeaks.png'
			),
			'custom' => array(
				'name' => __( 'custom', 'ZSoptions' ),
				'preview' => pluginUrl() . 'colors/custom/preview-custom.png'
			)
		);
		
		return apply_filters( 'ZSoptions_color_schemes', $color_schemes );
	}
	
	/*
	 * Color Schemes Head
	 *
	 * Enqueue any scripts or style necessary to display the chosen color scheme.
	 *
	 */
	function color_scheme_scripts() {
		if ( isset( $this->options['color-scheme'] ) ) { 
			if ( $this->options['color-scheme'] == 'default' ) {
				wp_enqueue_style( 'ZSoptions-default', pluginUrl() . 'colors/default/default.css', array(), null );

			} elseif ( $this->options['color-scheme'] == 'dark' ) {
				wp_enqueue_style( 'ZSoptions-darker', pluginUrl() . 'colors/dark/dark.css', array(), null );
			
			} elseif ( $this->options['color-scheme'] == 'black' ) {
				wp_enqueue_style( 'ZSoptions-custom1', pluginUrl() . 'colors/black/black.css', array(), null );

			} elseif ( $this->options['color-scheme'] == 'zeaks' ) {
				wp_enqueue_style( 'ZSoptions-zeaks', pluginUrl() . 'colors/zeaks/zeaks.css', array(), null );

			} elseif ( $this->options['color-scheme'] == 'custom' ) {
				wp_enqueue_style( 'ZSoptions-custom', pluginUrl() . 'colors/custom/custom.css', array(), null );		
			}			
			do_action( 'ZSoptions_enqueue_color_scheme', $this->options['color-scheme'] );
			} else {
			wp_enqueue_style( 'ZSoptions-default', pluginUrl() . '/colors/default/default.css', array(), null );
		}
	}
	
	/*
	 * Register Settings
	 *
	 * Fired during admin_init, this function registers the settings used
	 * in the Theme options section, as well as attaches a validator to
	 * clean up the icoming data.
	 *
	 */
	function register_admin_settings() {
		register_setting( 'ZSoptions-options', 'ZSoptions-options', array( &$this, 'validate_options' ) );
		
		// Settings fields and sections
		add_settings_section( 'section_general', __( 'General Settings', 'ZSoptions' ), array( &$this, 'section_general' ), 'ZSoptions-options' );
		add_settings_field( 'color-scheme', __( 'Color Scheme', 'ZSoptions' ), array( &$this, 'setting_color_scheme' ), 'ZSoptions-options', 'section_general' );
		
		do_action( 'ZSoptions_admin_settings' );
	}

	/*
	 * Options Validation
	 *
	 * This function is used to validate the incoming options, mostly from
	 * the Theme Options admin page. We make sure that the 'activated' array
	 * is untouched and then verify the rest of the options.
	 *
	 */
	function validate_options($options) {
		// Mandatory.
		$options['activated'] = true;
	
		// Theme options.
		$options['color-scheme'] = array_key_exists( $options['color-scheme'], $this->get_valid_color_schemes() ) ? $options['color-scheme'] : 'default';
		
		return $options;
	}
	
	/*
	 * Add Menu Options
	 *
	 * Registers a PluginOptions page that appears under the Settings
	 * menu in the WordPress dashboard. 
	 *
	 */
	function add_admin_options() {
		add_options_page( __( 'Snippet Options', 'ZSoptions' ), __('Snippet Options', 'ZSoptions' ), 'edit_theme_options', 'ZSoptions-settings', array( &$this, 'theme_options' ) );
	}	
	/*
	 * Plugin Options
	 *
	 * This is the function that renders the Plugin Options page under
	 * the Settings menu in the admin section. Upon visiting this the
	 * first time we make sure that a state (options-visited) is saved
	 * to our options array.
	 *
	 * The rest is handled by Settings API and some HTML magic.
	 *
	 */
	function theme_options() {
	
		if ( ! isset( $this->options['options-visited'] ) || ! $this->options['options-visited'] ) {
			$this->options['options-visited'] = true;
			$this->update_options();
		}
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br></div>
	<h2><?php _e( 'Snippet Options', 'ZSoptions' ); ?></h2>
	
	<form method="post" action="options.php">
		<?php wp_nonce_field( 'update-options' ); ?>
		<?php settings_fields( 'ZSoptions-options' ); ?>
		<?php do_settings_sections( 'ZSoptions-options' ); ?>
		<p class="submit">
			<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'ZSoptions'); ?>" />
		</p >
	</form>
</div>
<?php
	}

	/*
	 * Settings: General Section
	 *
	 * Used via the Settings API to output the description of the
	 * general settings under Plugin Options in Settings.
	 *
	 */
	function section_general() {
		_e( 'Select the color scheme for your snippets.', 'ZSoptions' );
	}
	
	/*
	 * Settings: Color Scheme
	 *
	 * Outputs a select box with available color schemes for the plugin
	 * Options page, as well as sets the selected color scheme as defined
	 * ib $options.
	 *
	 */
	function setting_color_scheme() {
	?>
		<?php
			$color_schemes = $this->get_valid_color_schemes();
			foreach ( $color_schemes as $value => $scheme ):
		?>
		<div class="mg-color-scheme-item" style="float: left; margin-right: 14px; margin-bottom: 18px;">
			<input <?php checked( $value == $this->options['color-scheme'] ); ?> type="radio" name="ZSoptions-options[color-scheme]" id="ZSoptions-color-scheme-<?php echo $value; ?>" value="<?php echo $value; ?>" />
			<label for="ZSoptions-color-scheme-<?php echo $value; ?>" style="margin-top: 4px; float: left; clear: both;">
				<img src="<?php echo $scheme['preview']; ?>" /><br />
				<span class="description" style="margin-top: 8px; float: left;"><?php echo $scheme['name']; ?></span>
			</label>
		</div>
		<?php
			endforeach;
		?>
		<br class="clear" />
		<span class="description"><?php _e( 'Wrap code in [code] [/code] tags. Custom stylesheets are included in the head section after all the theme stylesheets are loaded.', 'ZSoptions' ); ?></span>
		<?php
	}	

};

// Initialize the above class after setup
add_action( 'after_setup_theme', create_function( '', 'global $ZSoptions; $ZSoptions = new Zeaks_WP_Options();' ) );