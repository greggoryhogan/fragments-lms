<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * FLMS Course manager class
 */
class FLMS_Shortcodes {
	/**
	 * The Constructor.
	 */
	public function __construct() {
		add_action( 'init', array($this, 'register_shortcodes') );
	}

	public function get_flms_shortcodes() {
		$shortcodes = array(
			'course-name' => array(
				'description' => 'Shows the course name for the certificate',
			),
			'course-completion-date' => array(
				'description' => 'Show date user completed course',
				'atts' => array(
					'before' => 'Text before the date',
					'after' => 'Text after the date',	
				)
			),
			'course-list' => array(
				'description' => 'Lists all available courses',
				'atts' => array(
					'layout' => 'list or grid',
				)
			),
			'iframe' => array(
				'description' => 'Embed an iframe',
				'atts' => array(
					'src' => 'URL to the embedded iframe',
					'aspect-ratio' => 'widescreen, fullscreen, cinematic-widescreen, thirtyfivemm, instagram, square, vertical'
				)
			)
		);
		return $shortcodes;
	}

	public function register_shortcodes() {
		$shortcodes = $this->get_flms_shortcodes();
		foreach($shortcodes as $shortcode => $value) {
			add_shortcode( "flms-$shortcode", array($this, str_replace('-','_',$shortcode)) );
			$prefix = get_flms_whitelabel_prefix();
            if($prefix != '') {
                add_shortcode( "$prefix-$shortcode", array($this, str_replace('-','_',$shortcode)) );
            }
		}
		
		if(flms_is_module_active('course_taxonomies')) {
        	$taxonomies = new FLMS_Module_Course_Taxonomies();
			$taxonomies->register_shortcodes();
		} 
		if(flms_is_module_active('course_credits')) {
        	$taxonomies = new FLMS_Module_Course_Credits();
			$taxonomies->register_shortcodes();
		} 
		if(flms_is_module_active('course_numbers')) {
        	$numbers = new FLMS_Module_Course_Numbers();
			$numbers->register_shortcodes();
		} 
		if(flms_is_module_active('groups')) {
        	$groups = new FLMS_Module_Groups();
			$groups->register_shortcodes();
		} 
		if(flms_is_module_active('course_certificates')) {
        	$certificates = new FLMS_Module_Course_Certificates();
			$certificates->register_shortcodes();
		} 
		if(flms_is_module_active('course_metadata')) {
        	$meta = new FLMS_Module_Course_Metadata();
			$shortcodes = $meta->register_shortcodes();
		}

    }

	public function get_all_flms_shortcodes() {
		$shortcodes = $this->get_flms_shortcodes();
		if(flms_is_module_active('course_certificates')) {
        	$certificates = new FLMS_Module_Course_Certificates();
			$shortcodes = array_merge($shortcodes, $certificates->get_shortcodes());
		} 
		if(flms_is_module_active('course_taxonomies')) {
        	$taxonomies = new FLMS_Module_Course_Taxonomies();
			$shortcodes = array_merge($shortcodes, $taxonomies->get_shortcodes());
		} 
		if(flms_is_module_active('course_credits')) {
        	$credits = new FLMS_Module_Course_Credits();
			$shortcodes = array_merge($shortcodes, $credits->get_shortcodes());
		} 
		if(flms_is_module_active('course_numbers')) {
        	$numbers = new FLMS_Module_Course_Numbers();
			$shortcodes = array_merge($shortcodes, $numbers->get_shortcodes());
		} 
		if(flms_is_module_active('course_metadata')) {
        	$meta = new FLMS_Module_Course_Metadata();
			$shortcodes = array_merge($shortcodes, $meta->get_shortcodes());
		} 
		return $shortcodes;
	}

	public function display_shortcode_references() {
		$shortcodes = $this->get_all_flms_shortcodes();
		$prefix = get_flms_whitelabel_prefix();
        if($prefix == '') {
			$prefix = 'flms';
		}
		foreach($shortcodes as $shortcode_name => $shortcode_data) {
			echo '<div>';
				echo '<div>['.$prefix.'-'.$shortcode_name.']</div>';
				if(is_array($shortcode_data)) {
					if(isset($shortcode_data['description'])) {
						echo '<p class="description">'.$shortcode_data['description'].'</p>';    
					}
					if(isset($shortcode_data['atts'])) {
						echo '<div>Shortcode options:</div>';
						foreach($shortcode_data['atts'] as $k => $v) {
							echo '<p class="description"><strong>'.$k.':</strong> '.$v.'</p>';
						}
					   
					} else {
						echo 'No shortcode options';
					}
				} else {
					echo '<p class="description">'.$shortcode_data.'</p>';
				}
			echo '</div>';
		}
	}

	public function course_list($atts) {
		do_action('flms_before_course_list_shortcode');
		global $flms_settings;
		
		$layout = 'list';
		if(isset($flms_settings['design']['course_display'])) {
			$layout = $flms_settings['design']['course_display'];
		}
		if($layout == 'list') {
			$columns = 1;
		} else {
			$columns = apply_filters('flms_course_list_columns', 3);
		}
		$default_atts = array(
            'layout' => $layout,
			'columns' => $columns,
			'taxonomy' => '',
			'taxonomy-name' => '',
			'credit-type' => ''
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-course-credit' );
		global $flms_settings, $wpdb;
		wp_enqueue_style( 'select2');
		wp_enqueue_style( 'flms-all-courses');
		wp_enqueue_script( 'flms-all-courses');
		wp_localize_script( 'flms-all-courses', 'flms_all_courses', array(
			'primary_color' => $flms_settings['design']['primary_color'],
			'background_color' => $flms_settings['design']['background_color'],
			'permalink' => get_permalink(),
		));
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		$args = array(
			'post_type' => 'flms-courses',
            'post_status' => 'publish',
			'orderby'   => apply_filters('flms_courses_sort_order', 'title'),
			'order'     => 'ASC',
			'posts_per_page' => apply_filters('flms_courses_per_page', 18),
			'paged' => $paged,
			'fields' => 'ids'
		);
		if($atts['taxonomy'] != '') {
			$taxonomy = $atts['taxonomy'];
			if(isset($atts['taxonomy-name']) && !isset($_GET[$taxonomy])) {
				$tax_name = $atts['taxonomy-name'];
				$term = get_term_by('name',$tax_name,$taxonomy);
				if($term !== false) {
					$_GET[$taxonomy] = $term->term_id;
				}
			}
		}
		if($atts['credit-type'] != '') {
			$_GET['credit_type'] = array(sanitize_text_field($atts['credit-type']));
		}

		$search_course_numbers = apply_filters('flms_allow_course_numbers_in_search', true);
		
		$status_string = "`meta_key`='course_status' AND `meta_value`='publish'";
		
		$table = FLMS_COURSE_QUERY_TABLE;
		
		if(!empty($_GET)) {
			//print_r($_GET);
			$sql_query = '';
			//parse get
			$min_credits = 0;
			if(isset($_GET['min_credits'])) {
				$min_credits = $_GET['min_credits'];
			}
			$max_credits = -1;
			if(isset($_GET['max_credits'])) {
				$max_credits = $_GET['max_credits'];
			}
			
			$minmax = $wpdb->prepare("SELECT meta_value FROM $table WHERE meta_key=%s or meta_key=%s", 'min_credits', 'max_credits'); // IN (" . implode(',', $credit_keys) . ")"
			$minmaxresults = $wpdb->get_col( $minmax ); 
			if(!empty($minmaxresults)) {
				$min = trim(min($minmaxresults));
				$max = trim(max($minmaxresults));
				if($min == $min_credits) {
					$min_credits = -1;
				}
				if($max == $max_credits) {
					$max_credits = -1;
				}
			}
			$credit_filters = array();
			$has_credit_filters = false;
			if(isset($_GET['credit_type'])) {
				$credit_filters = array_filter($_GET['credit_type']);
				if(!empty($credit_filters)) {
					$has_credit_filters = true;
				} 
			}
			if(!$has_credit_filters) {
				$course_credits = new FLMS_Module_Course_Credits();
				$credits_array = $course_credits->get_course_credits_fields(true,true);
				foreach($credits_array as $credit) {
					$key = $credit['key'];
					$credit_filters[] = $key;
				}
			}
			//print_r($credit_filters);
			$credit_strings = array();
			foreach($credit_filters as $credit_type) {
				$string = '';
				if($min_credits == -1 && $max_credits == -1) {
					$string .= "`meta_key`='$credit_type'";
				} else if($min_credits > -1 && $max_credits == -1) {
					//just search for min values
					$string .= "`meta_key`='$credit_type' AND `meta_value` >= $min_credits";
				} else if ($max_credits > -1 && $min_credits == -1) {
					//just search max values
					$string .= "`meta_key`='$credit_type' AND `meta_value` <= $max_credits";
				} else {
					$string .= "`meta_key`='$credit_type' AND `meta_value` >= $min_credits AND `meta_value` <= $max_credits";
					//$string .= "`meta_key`='$credit_type'";
				}
				
				$credit_strings[] = $string;
			}

			if(count($credit_strings) > 1) {
				$credit_query_string = '('.implode(') OR (', $credit_strings).')';
			} else {
				$credit_query_string = implode('', $credit_strings);
			}
			
			/*$query_strings = array(
				"course_id IN (SELECT course_id FROM $table WHERE $status_string)",
				"course_id IN (SELECT course_id FROM $table WHERE $credit_query_string)"
			);*/

			$query_strings = array(
				'course_status' => "$status_string",
				'course_credits' => "$credit_query_string"
			);
			
			if(isset($_GET['course-term'])) {
				$course_term = $_GET['course-term'];
				$search_fields = array('post_content','course_preview');
				if(flms_is_module_active('course_tabs')) {
					$course_tabs = new FLMS_Module_Course_Tabs();
					$tabs = $course_tabs->get_course_tab_fields(true, true, true);
					if(!empty($tabs)) {
						foreach($tabs as $k => $v) {
							$search_fields[] = 'course_tab_'.$v['key'];
						}
					}
				}
				if($course_term != '') {
					$search_term = str_replace('#','',$course_term);
					$term_string = "(`meta_key` IN ('".implode("','",$search_fields)."') AND `meta_value` LIKE '%$search_term%')";
					$default = "course_id IN (SELECT course_id FROM $table WHERE (`meta_key`='course_name' AND `meta_value` REGEXP '$course_term') OR $term_string)";
					//$search_course_numbers = false;
					if($search_course_numbers) {
						if(flms_is_module_active('course_numbers')) {
							global $wpdb;
							$course_credits = new FLMS_Module_Course_Credits();
							$credits_array = $course_credits->get_course_credits_fields(true,true);
							$credit_filters = array();
							foreach($credits_array as $credit) {
								$key = $credit['key'];
								$credit_filters[] = $key;
							}
							$credit_strings = array();
							$string = "`meta_key`='course_number_global' AND `meta_value` = '$search_term'";
							$credit_strings[] = $string;
							foreach($credit_filters as $credit_type) {
								$string = "`meta_key`='course_number_$credit_type' AND `meta_value` = '$search_term'";
								$credit_strings[] = $string;
							}
							$credit_query_string = '('.implode(') OR (', $credit_strings).')';
							$default = "course_id IN (SELECT course_id FROM $table WHERE (`meta_key`='course_name' AND `meta_value` REGEXP '$course_term') OR $term_string OR $credit_query_string)";
						}

					}
					$query_strings['course_term'] = $default;

					
				}
			} 
			//$query_string = implode(' AND ', $query_strings);
			
			$course_ids = array();
			foreach($query_strings as $query_type => $query_string) {
				$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $query_string";	
				$sql_query = apply_filters('flms_course_search_sql_query', $sql_query, $query_type);
				//echo $sql_query.'<br><br>';
				//$sql = $wpdb->prepare( $sql_query );
				//$results = $wpdb->get_results( $sql );
				$results = $wpdb->get_col( $sql_query ); 
				if(!empty($results)) {
					//print_r($results);
					$course_ids[] = $results;
				} else {
					$course_ids[] = array(0); // Something not found so we need to break it
				}
			}
			//echo '<pre>'.print_r($course_ids,true).'</pre>';
			if(!empty($course_ids)) {
				$course_ids = call_user_func_array('array_intersect', $course_ids);
			}
			if(empty($course_ids)) {
				$course_ids = array(0);
			}
			//print_r($course_ids);
			//search courses for post_content and course_preview
			/*if(isset($_GET['course-term'])) {
				$search_term = stripslashes(sanitize_text_field($_GET['course-term']));
				if($search_term != '') {
					$content_string = "`meta_key` IN ('post_content','course_preview') AND `meta_value` LIKE '%$search_term%'";
					$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $content_string";	
					$results = $wpdb->get_col( $sql_query ); 
					if(!empty($results)) {
						$course_ids = array_merge($course_ids, $results);
					}
				}
			}*/

			$args['post__in'] = $course_ids;

			/*$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $query_string";
			//filter to course ids based on criteria
			if($sql_query != '') {
				$sql = $wpdb->prepare( $sql_query );
				//$results = $wpdb->get_results( $sql );
				$results = $wpdb->get_col( $sql ); 
				if(empty($results)) {
					$results = array(0);
				}
				$args['post__in'] = $results;
			}*/

			//check for search term
			if(isset($_GET['course-term'])) {
				/*$query_string = "";
				$course_term = $_GET['course-term'];
				$sql_query = "SELECT DISTINCT course_id FROM $table WHERE meta_key='course_name' AND meta_value LIKE '%$course_term%'";
				$sql = $wpdb->prepare( $sql_query );
				//$results = $wpdb->get_results( $sql );
				$newresults = $wpdb->get_col( $sql ); 
				//$args['post__in'] = array_merge($args['post__in'], $newresults);*/

				$search_term = stripslashes(sanitize_text_field($_GET['course-term']));
				if($search_term != '') {
					//$args['s'] = $search_term;
					if($search_course_numbers) {
						//add_filter( 'posts_where', array($this, 'flms_course_search_allow_course_numbers'), 10, 2 );
					}
					/*$args['meta_query'] = array(
						array(
							'key' => 'flms_version_content',
							'value' => $search_term,
							'compare' => 'LIKE',
						),
					);*/
				}
			}

			//check for course taxonomies
			if(flms_is_module_active('course_taxonomies')) {
				//$course_taxonomies = new FLMS_Module_Course_Taxonomies();
				if(isset($flms_settings['course_taxonomies'])) {
					$tax_queries = array();
					foreach($flms_settings['course_taxonomies'] as $taxonomy_name => $options) {
						if($options['filter-status'] == 'show') {
							if(isset($_GET[$taxonomy_name])) {
								$tax_value = apply_filters('flms_course_search_tax_term', absint($_GET[$taxonomy_name]), sanitize_text_field( $taxonomy_name ));
								if($tax_value > 0 || is_array($tax_value)) {
									$tax_queries[] = array(
										'taxonomy' => sanitize_text_field($taxonomy_name),
										'field' => 'term_id',
										'terms' => $tax_value
									);
								}
									
							}
						}
					}
					if(!empty($tax_queries)) {
						if(count($tax_queries) > 1) {
							$args['tax_query'] = array(
								'relation' => 'AND',
								$tax_queries
							);
						} else {
							$args['tax_query'] = $tax_queries;
						}
						
					}
				}
			}

		} else {
			$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $status_string";
			
			//filter to course ids based on criteria
			if($sql_query != '') {
				$results = $wpdb->get_col( $sql_query ); 
				if(empty($results)) {
					//echo '<pre>'.print_r($results,true).'</pre>';
					$results = array(0);
				}
				$args['post__in'] = $results;
			}
		}
		
		//echo '<pre>'.print_r($args,true).'</pre>';
		$args = apply_filters('flms_course_list_args', $args);
		$courses_ouput = '';

		

		$courses_ouput .= apply_filters('flms_before_course_list_output','');
		//show filters
		$show = apply_filters('flms_show_course_filters', true);
		if($show) {
			$courses_ouput .= apply_filters('flms_before_course_filters','');
			$courses_ouput .= flms_course_filters($_GET);
			$courses_ouput .= apply_filters('flms_after_course_filters','');
		}
		$courses_ouput .= apply_filters('flms_before_course_list','');
		$course_query = new WP_Query( $args );
		if($course_query->have_posts()) {
			$courses_ouput .= '<div class="flms-course-list columns-'.$atts['columns'].'">';
				while($course_query->have_posts()) {
					$course_query->the_post();
					$courses_ouput .= flms_my_courses_output(get_the_ID(),$atts['layout']);
				}
			$courses_ouput .= '</div>';
			$courses_ouput .= '<div class="flms-course-pagination">';
				$big = 999999999; // need an unlikely integer
				$courses_ouput .= paginate_links(
					array(
						'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
						'format' => '?paged=%#%',
						'current' => max(
							1,
							get_query_var('paged')
						),
						'total' => $course_query->max_num_pages //$q is your custom query
					)
				);
			$courses_ouput .= '</div>';
		} else {
			$courses_ouput .= '<div class="flms-course-pagination nothing-found flms-alert flms-primary flms-secondary-bg flms-secondary-border flms-flex">';
				$courses_ouput .= apply_filters('flms_no_courses_found','Nothing found.');
			$courses_ouput .= '</div>';
		}
		$courses_ouput .= apply_filters('flms_after_course_list','');
		$courses_ouput .= apply_filters('flms_after_course_list_output','');
		if($search_course_numbers) {
			remove_filter( 'posts_where', array($this, 'flms_course_search_allow_course_numbers'), 10, 2 );
		}
		return $courses_ouput;
	}

	public function flms_course_search_allow_course_numbers( $where, $query ) {
		$search_term = stripslashes(sanitize_text_field($query->get( 's' )));
		if($search_term != '') {
			$search_term = str_replace('#','',$search_term);
			if(flms_is_module_active('course_numbers')) {
				global $wpdb;
				$course_credits = new FLMS_Module_Course_Credits();
				$credits_array = $course_credits->get_course_credits_fields(true,true);
				$credit_filters = array();
				foreach($credits_array as $credit) {
					$key = $credit['key'];
					$credit_filters[] = $key;
				}
				$credit_strings = array();
				$string = "`meta_key`='course_number_global' AND `meta_value` = '$search_term'";
				$credit_strings[] = $string;
				foreach($credit_filters as $credit_type) {
					$string = "`meta_key`='course_number_$credit_type' AND `meta_value` = '$search_term'";
					$credit_strings[] = $string;
				}
				$credit_query_string = '('.implode(') OR (', $credit_strings).')';
				$table = FLMS_COURSE_QUERY_TABLE;
				$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $credit_query_string";
				if($sql_query != '') {
					$results = $wpdb->get_col( $sql_query ); 
					if(!empty($results)) {
						$id_list = implode( ',', array_map( 'intval', $results ) );
						$where .= " OR {$wpdb->posts}.ID IN ($id_list)";
					}
				}				
			} 
			
		}
		return $where;
	}

	public function course_name() {
		global $post, $flms_active_version;
		$course_version = $flms_active_version;
		$course = new FLMS_Course($post->ID, $course_version);
		return $course->get_course_version_name($course_version);
	}

	public function course_completion_date($atts) {
		$default_atts = array(
            'before' => '',
            'after' => '',
			'font-size' => ''
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-course-credit' );
		global $post, $flms_active_version, $wp, $wpdb;
		$query_vars = $wp->query_vars;
		$completion_date = '';
		if(isset($query_vars['certificate-entry-id'])) {
			$certificate_id = absint($query_vars['certificate-entry-id']);
			$table = FLMS_ACTIVITY_TABLE;
			$sql_query = $wpdb->prepare("SELECT completion_date FROM $table WHERE id=%d", $certificate_id);
			$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
			if(!empty($results)) {
				foreach($results as $result) {
					$date_format = get_option( 'date_format' );
					$completion_date = $result['completion_date'];
					$date = date($date_format, strtotime($completion_date));
					$return = '';
					if($atts['font-size'] != '') {
						$return .= '<span style="font-size: '.$atts['font-size'].'">';
					}
					$return .= $atts['before'] . $date . $atts['after'];
					if($atts['font-size'] != '') {
						$return .= '</span>';
					}
					return $return;
					break;
				}
			}
			return $query_vars['certificate-entry-id'];
		}
		/*$completed_courses = flms_get_user_completed_courses();
		$post_id = $post->ID;
		//echo '<pre>'.print_r($completed_courses,true).'</pre>';
		if ($key = array_keys($completed_courses, ['course_id' => $post_id, 'course_version' => $flms_active_version]) !== false) {
			
			if(isset($completed_courses[$key]['completion_date'])) {
				$date_format = get_option( 'date_format' );
				$date = date($date_format, strtotime($completed_courses[$key]['completion_date']));
				return $atts['before'] . $date . $atts['after'];
			}
		} */
		return '';
	}

	public function iframe($atts) {
		$default_atts = array(
            'src' => '',
			'aspect-ratio' => 'widescreen'
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-iframe' );
		if($atts['src'] != '') {
			$iframe = '<div id="flms-content-video" class="flms-video '.$atts['aspect-ratio'].'">';
			$iframe .= '<iframe type="text/html" src="'.$atts['src'].'"></iframe>';
			$iframe .= '</div>';
			return $iframe;
		}
		return '';
	}
}
new FLMS_Shortcodes();