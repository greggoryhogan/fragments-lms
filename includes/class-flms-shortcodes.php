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
			'orderby'   => 'title',
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
		if(!empty($_GET)) {
			//print_r($_GET);
			$table = FLMS_COURSE_QUERY_TABLE;
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
			$table = FLMS_COURSE_QUERY_TABLE;
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
				}
				
				$credit_strings[] = $string;
			}
			if(count($credit_strings) > 1) {
				$credit_query_string = '('.implode(') OR (', $credit_strings).')';
				
			} else {
				$credit_query_string = implode('', $credit_strings);
			}
			$sql_query = "SELECT DISTINCT course_id FROM $table WHERE $credit_query_string";
				
				//echo $credit_query_string;
			/*} else {
				
				//Search for all courses with the min/max values
				if($min_credits > -1 && $max_credits == -1) {
					//just search for min values
					$sql_query = "SELECT DISTINCT course_id FROM $table WHERE `meta_key`='min_credits' AND `meta_value` >= $min_credits";
				} else if ($max_credits > -1 && $min_credits == -1) {
					//just search max values
					$sql_query = "SELECT DISTINCT course_id FROM $table WHERE `meta_key`='max_credits' AND `meta_value` <= $max_credits";
				} else {
					
					//search both min and max credits
					$sql_query = "
					SELECT DISTINCT lle.course_id
					FROM $table lle
					JOIN $table llc  ON lle.course_id = llc.course_id
					WHERE lle.meta_key = 'min_credits'
					AND lle.meta_value >= $min_credits
					AND llc.meta_key = 'max_credits'
					AND llc.meta_value <= $max_credits";
				}
			}*/
			//filter to course ids based on criteria
			if($sql_query != '') {
				//echo $sql_query;
				$results = $wpdb->get_col( $sql_query ); 
				if(!empty($results)) {
					//echo '<pre>'.print_r($results,true).'</pre>';
					$args['post__in'] = $results;
				}
			}

			//check for search term
			if(isset($_GET['course-term'])) {
				$search_term = stripslashes(sanitize_text_field($_GET['course-term']));
				if($search_term != '') {
					$args['s'] = $search_term;
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
								$tax_value = absint($_GET[$taxonomy_name]);
								if($tax_value > 0) {
									$tax_queries[] = array(
										'taxonomy' => $taxonomy_name,
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
			//echo 'empty';
		}
		//echo '<pre>'.print_r($args,true).'</pre>';
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
		return $courses_ouput;
	}

	public function course_name() {
		global $post;
		return get_the_title($post->ID);
	}

	public function course_completion_date($atts) {
		$default_atts = array(
            'before' => '',
            'after' => ''
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-course-credit' );
		global $post, $flms_active_version;
		$completed_courses = flms_get_user_completed_courses();
		$post_id = $post->ID;
		//echo '<pre>'.print_r($completed_courses,true).'</pre>';
		if ($key = array_keys($completed_courses, ['course_id' => $post_id, 'course_version' => $flms_active_version]) !== false) {
			
			if(isset($completed_courses[$key]['completion_date'])) {
				$date_format = get_option( 'date_format' );
				$date = date($date_format, strtotime($completed_courses[$key]['completion_date']));
				return $atts['before'] . $date . $atts['after'];
			}
		} 
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