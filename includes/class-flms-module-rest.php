<?php
class FLMS_Module_REST {

    //To check endpoints, visit https://yoursite.com/wp-json
    
    public function __construct() {
    
    }
    
    function register_rest_endpoints() {
        $GLOBALS['default_user_id'] = get_current_user_id();
        //Add cron processes to both post and get methods
        //webkul for reference - https://webkul.com/blog/add-custom-rest-api-route-wordpress/
        register_rest_route( 'flms/v1',
        '/complete/(?P<post_id>[\d]+)/(?P<version>[\d]+)/(?P<user_id>[\d]+)/', 
            array(
                'methods' => 'GET',
                'callback' => array($this, 'complete_flms_content'),
                'args'     => array(
                    'post_id' => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_numeric( $param );
                        },
                    ),
                    'version' => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_numeric( $param );
                        },
                    ),
                    'user_id' => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_numeric( $param );
                        },
                    ),
                    'key' => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return $this->test_key($param);
                        },
                    ),
                ),
                'permission_callback' => '__return_true', //TMP, not checking for permissions right now
            )
        );

        /*
        register_rest_route(
			"{$this->endpoint}/{$this->version}", // Namespace
			'get-course-version/(?P<course_id>[a-zA-Z0-9-]+)/(?P<version>[a-zA-Z0-9-]+)', // Route with parameters
			array(
				'methods'  => 'GET',
				'callback' => array($this,'get_fmls_course_version_content'),
			)
		);*/
    } 

    /*
    * Callback for $_GET rest request
    */ 
    public function complete_flms_content($request) {
        $params = $request->get_params();
        $post_id = $params['post_id'];
        $post_type = get_post_type($post_id);
        if($post_type == 'flms-courses') {
            $course_id = 0;
            //TODO: Write course progress function to complete a course
        } else {
            $course_id = flms_get_course_id($post_id);
        }
        if($course_id > 0) {
            $course_version = $params['version'];
            $user_id = $params['user_id'];
            if($user_id == 0) {
                $user_id = $GLOBALS['default_user_id'];
            }
            //https://bhfe.local/wp-json/flms/v1/complete/34935/1/1/?key=cjmim76sx3w
            $course_progress = new FLMS_Course_Progress();
            $course_progress->update_user_activity($post_id, $user_id, $course_id, $course_version);
            $response = new WP_REST_Response(
                array(
                    'status' => 200,
                    'result' => 'Progress for '.$post_id.' updated for user '.$user_id .' in course '.$course_id .' version '.$course_version,
                )
            );
        } else {
            $response = new WP_REST_Response(
                array(
                    'status' => 400,
                    'result' => "Could not find course for post $post_id",
                )
            );
        }
        if(isset($params['redirect'])) {
            //For when sending from a link
            //https://bhfe.local/wp-json/flms/v1/complete/1270/1/1/?key=asdf1234&redirect=https://google.com
            wp_redirect($params['redirect']);
            exit;
        } else {
            return $response;
        }
    }

    public function test_key($param) {
        if($param == '') {
            return false;
        }
        global $flms_settings;
        if (defined("FLMS_REST_AUTH_TOKEN")) {
            $token = FLMS_REST_AUTH_TOKEN; 
        } else if(isset($flms_settings['rest']['auth_token'])) {
            $token = $flms_settings['rest']['auth_token'];
        }
        if($param != $token) {
            //TODO, build option for setting key
            return false;
        }
        return true;
    }

    /**
	 * Get version content
	 * @return json
	 */
	/*public function get_fmls_course_version_content($request) {
		global $wpdb;
		$course_id = $request->get_param('course_id');
		$version = $request->get_param('version');
		$versions = maybe_unserialize(get_post_meta($course_id,'flms_version_content',true));
		if(is_array($versions)) {
			foreach($versions as $k => $v) {
				$this_version = str_replace('v','',$k);
				if($this_version == $version) {
					$response = new WP_REST_Response(
						array(
							'versioned_content' => $v["post_content"]
						)
					);
					$response->set_status(200);
					return $response;
				}
			}
			$response = new WP_REST_Response(
				array(
					'versioned_content' => '',
					'response' => 'No version found.'
				)
			);
			$response->set_status(400);
			return $response;
		} else {
			$response = new WP_REST_Response(
				array(
					'versioned_content' => '',
					'response' => 'This course has no versions.'
				)
			);
			$response->set_status(400);
			return $response;
		}
	} */

    public function get_settings_fields() {
        $fields = array(
            array(
                'label' => 'Auth Token',
                'key' => 'auth_token',
                'description' => 'The auth token used when sending REST requests. This token can be overriden by defining &ldquo;FLMS_REST_AUTH_TOKEN&rdquo; in wp-config.php',
                'type' => 'text',
                'default' => $this->get_default_token(),
            ),
        );
        return $fields;
    }

    public function get_default_token() {
        $characters = '23456789abcdefghjklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ'; //no 0/o, 1/i
		$group_code = '';
		for ($i = 0; $i < 11; $i++) {
			$index = rand(0, strlen($characters) - 1);
			$group_code .= $characters[$index];
		}
		$group_code = strtolower($group_code);
        return $group_code;
    }
}
new FLMS_Module_REST();