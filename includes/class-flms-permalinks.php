<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//required for verify nonce for some reason
require_once( ABSPATH . 'wp-includes/pluggable.php' );

/**
 * FLMS_Permalinks class
 */
class FLMS_Permalinks {

    public $post_types = array();
	/**
	 * Hook in sections
	 */
	public function __construct() {
		$this->settings_init();
		$this->settings_save();
	}

	/**
	 * Init our settings.
	 */
	public function settings_init() {
		$this->add_permalinks_to_options( 'flms-permalinks', __( 'Fragment LMS permalinks', 'fmls' ), array( $this, 'settings' ), 'permalink' );
	}

    /**
     * Add our settings to the permalinks page
     */
    public function add_permalinks_to_options( $id, $title, $callback, $page, $args = array() ) {
        global $wp_settings_sections;
        $defaults = array(
            'id'             => $id,
            'title'          => $title,
            'callback'       => $callback,
            'before_section' => '',
            'after_section'  => '',
            'section_class'  => '',
        );
        $section = wp_parse_args( $args, $defaults );
        $wp_settings_sections[ $page ][ $id ] = $section;
    }
	

	/**
	 * Show the settings.
	 */
	public function settings() {
		/* translators: %s: Home URL */
		echo wp_kses_post( wpautop( sprintf( __( 'If you like, you may enter custom permlainks for each section of the courseware. For example <code>%scourse/your-course</code>.', 'fmls' ), esc_url( home_url( '/' ) ) ) ) );
        global $flms_settings;
        $post_types = flms_get_plugin_post_types();
        ?>
        <table class="form-table wc-permalink-structure">
			<tbody>
                <?php foreach($post_types as $post_type) { 
                    $field_value = '';
                    $post_type_name = $post_type['permalink'];
                    $key = "{$post_type_name}_permalink";
                    if(isset( $flms_settings["custom_post_types"][$key] )) {
                        $field_value = $flms_settings["custom_post_types"][$key];
                    } else {
                        $field_value = $post_type['permalink'];
                    }
                    ?>
                    <tr>
                        <th><label>
                            <?php esc_html_e( ucwords(str_replace('-',' ',$post_type_name)).'s', 'flms' ); ?>
                        </label></th>
                        <td>
                            <input name="<?php echo $post_type_name; ?>_permalink" type="text" value="<?php echo $field_value; ?>" />
                        </td>
                    </tr>
                <?php } ?>
			</tbody>
		</table>
		<?php wp_nonce_field( 'flms-permalinks', 'flms-permalinks-nonce' ); 
	}

	/**
	 * Save the settings into our global settings field
	 */
	public function settings_save() {
		if ( ! is_admin() ) {
			return;
		}
        if ( isset( $_POST['flms-permalinks-nonce']) && wp_verify_nonce( wp_unslash( $_POST['flms-permalinks-nonce'] ), 'flms-permalinks' ) ) { // WPCS: input var ok, sanitization ok.
            global $flms_settings;
            $this->post_types = flms_get_plugin_post_types();
            foreach($this->post_types as $post_type) { 
                $post_type_name = $post_type['permalink'];
                if ( isset( $_POST["{$post_type_name}_permalink"])) { 
                    $post_type_value = $_POST["{$post_type_name}_permalink"];   
                    $flms_settings["custom_post_types"]["{$post_type_name}_permalink"] = $post_type_value;
                    $flms_settings["post_type_references"]["{$post_type_value}"] = $post_type['internal_permalink'];
                }
            }
            update_option('flms_settings',$flms_settings);
        }
	}
}
new FLMS_Permalinks();
