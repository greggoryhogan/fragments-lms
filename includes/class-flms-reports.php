<?php
class FLMS_Reports {
    public function __construct() {
        add_action('admin_menu', array($this,'register_reports_page'));
	}

    public function register_reports_page() {
        add_submenu_page(FLMS_PLUGIN_SLUG,'Reports','Reports','install_plugins', 'flms-reports',array($this,'flms_reports'),97);
    }

    public function flms_reports() {
        global $flms_settings;
        wp_enqueue_script(
			'flms-reports',
			FLMS_PLUGIN_URL . 'assets/js/admin-reports.js',
			array('jquery'),
			flms_get_plugin_version(),
			true
		);
		wp_localize_script( 'flms-reports', 'flms_reports', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'settings' => $flms_settings
		));

        ?><div class="wrap">
			
			<h1><?= __(FLMS_PLUGIN_NAME .' Reports', 'flms') ?></h1>
            <div class="page-reports">
                <!--<p>NOTE: THIS PAGE IS NOT CURRENTLY FUNCTIONAL.<br>SINCE THE INTRODUCTION OF EXAMS PULLING QUESTIONS VIA CATEGORY, SOME CRITICAL FUNCTIONALITY MUST BE REFACTORED.</p>-->
                <div class="flex space-between align-flex-start reports-options">
                    <form id="reports-settings" action="<?php echo admin_url('admin.php?page=flms-reports'); ?>">
                        <div>
                            <label>Report type</label>
                            <?php $report_types = array(
                                'course_progress' => 'Course Progress',
                                'answers' => 'Answers Analysis'
                            );
                            if(flms_is_module_active('course_credits')) {
                                $report_types['course_credits'] = 'Credit Reporting';
                            }
                            if(flms_is_module_active('woocommerce') && flms_is_module_active('course_taxonomy_royalties')) {
                                $report_types['royalties'] = 'Royalty Reporting';
                            }
                            echo '<select name="report-type" id="report-type">';
                            echo '<option value="0">Select a report type</option>';
                            foreach($report_types as $k => $v) {
                                echo '<option value="'.$k.'">'.$v.'</option>';
                            }
                            echo '</select>'; ?>
                        </div>
                        <div id="appended-report-data"></div>
                        <div>
                            <input type="submit" value="Generate report" class="button button-primary" />
                        </div>
                    </form>

                    <?php $saved = $this->get_saved_reports(); ?>
                    <form id="load-reports">
                        <div class="flex column" style="gap: 2px;">
                            <label style="margin-bottom: 2px;">Load saved report</label>
                            <div class="load-report-options">
                                <?php echo '<select name="load-report" id="load-report">';
                                if(is_array($saved)) {
                                    echo '<option value="-1">Select report</option>';
                                    $count = 0;
                                    foreach($saved as $save) {
                                        echo '<option value="'.$count.'">'.stripslashes($save).'</option>';
                                        $count++;
                                    }
                                } else {
                                    echo '<option value="-1">No saved reports</option>';
                                }
                                echo '</select>'; ?>
                                <input type="submit" value="Load report" class="button button-primary" />
                            </div>
                        </div>
                    </form>
                </div>
                <div id="report-results" class="is-active"><p class="awaiting-action">Select or load a report using the filtes above.</p></div>
                <div id="save-report">
                    <div id="report-data-breakdown"></div>
                    <input type="text" id="report-name" value="" />
                    <input type="hidden" id="report-data">
                    <input type="hidden" id="active-report" value="-1">
                    <button id="save_report" class="button button-primary">Save report</button>
                    <div id="save_status"></div>
                    <div id="delete_report">Delete report</div>
                </div>
                <div class="progress-loading"><div class="progress"></div></div>

                <?php
                //test data
                /*global $wpdb;
                $meta_key = 'flms_question_report_data';
                $post_type = 'flms-questions';
                $post_status = 'any';
                $posts = get_posts(
                    array(
                        'post_type' => $post_type,
                        'meta_key' => $meta_key,
                        'posts_per_page' => -1,
                    )
                );
                $meta_values = array();
                foreach( $posts as $post ) {
                    echo get_the_title($post->ID).'<br>';
                    $meta_values = get_post_meta( $post->ID, $meta_key, true );
                    print_r($meta_values);
                }
                */ ?>
         
            </div>
        </div><?php
    }

    /*public function get_report_dir() {
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $upload_dir = $upload_dir . '/flms';
        if (! is_dir($upload_dir)) {
            mkdir( $upload_dir, 0700 );
        }
        $reports_dir = $upload_dir . '/flms-reports';
        if (! is_dir($reports_dir)) {
            mkdir( $reports_dir, 0700 );
        }
        return $reports_dir;
    }*/

    public function get_saved_reports() {
        $reports = maybe_unserialize(get_option('flms_reports'));
        if(is_array($reports)) {
            $report_array = array();
            foreach($reports as $k => $v) {
                $report_array[] = $v['name'];
                
            }
            return $report_array;
        } else {
            return false;
        }
        
    }

    public function save_report($name, $data, $active_report = -1) {
        $reports = maybe_unserialize(get_option('flms_reports'));
        if(!is_array($reports)) {
            $reports = array();
        }
        $sanitized_data = array();
        $data = maybe_unserialize(stripslashes($data));
        if(is_array($data)) {
            foreach($data as $k => $v) {
                $sanitized_data[$k] = sanitize_text_field($v);
            }
        }
        if($active_report >= 0) {
            $reports[$active_report] = array(
                'name' => $name,
                'data' => $sanitized_data
            );
        } else {
            $reports[] = array(
                'name' => $name,
                'data' => $sanitized_data
            );
        }
        update_option('flms_reports',maybe_serialize($reports));
        /*$reports_dir = $this->get_report_dir();
        if ( $upload = wp_upload_bits( $_FILES['piv_card']['name'], null, file_get_contents( $_FILES['piv_card']['tmp_name'] ) ) ) {
            wp_send_json( array(
               'success' => 1,
               'error' => $upload['error'],
               'file' => $upload['url'],
            )
            );
         }*/
    }

    public function generate_report($data, $name = '') {
        global $flms_active_version;
        $report = array();
        $response = '';
        $report_information = '';
        $report_data = maybe_serialize($data);
        $type = sanitize_text_field($data['report-type']);
        switch($type) {
            case 'course_progress':
                $report_information .= '<div data-report-type="'.$type.'"><span>Report type:</span> Course Progress</div>';
                $course_id = $data['flms-course-select'];
                if($course_id == 0) {
                    $response = 'Please select a course.';
                } else {
                    //$report_information .= '<div><span>Course:</span> '.get_the_title($course_id).'</div>';
                    $version = $data['flms-version-select'];
                    if($version == -1) {
                        $response = 'Please select a version.';
                    } else {
                        $course = new FLMS_Course($course_id);
                        $version_name = $course->get_course_version_name($version);
                        $report_information .= '<div data-flms-course-select="'.$course_id.'" data-flms-version-select="'.$version.'"><span>Course:</span> '.$version_name.'</div>';
                        $status = $data['flms-course-status-select'];
                        if($status == 0) {
                            $response = 'Please select a status.';
                        } else {
                            $date_format = get_option('date_format');
                            $report_information .= '<div data-flms-course-status-select="'.$status.'"><span>Course status:</span> '.ucwords($status).'</div>';
                            $start_date = $data['date-start'];
                            $end_date = $data['date-end'];
                            $start_query_date = date('Y-m-d 00:00:00',strtotime($start_date)); //2022-09-29 19:43:24
		                    $end_query_date = date('Y-m-d 23:59:59',strtotime($end_date)); //2022-09-29 19:43:24
                            global $wpdb;
                            $table = FLMS_ACTIVITY_TABLE;
                            switch($status) {
                                case 'completed':
                                    $report_information .= '<div data-flms-date-start="'.$start_date.'"><span>Completed Start Date:</span> '.date($date_format,strtotime($start_date)).'</div>';
                                    $report_information .= '<div data-flms-date-end="'.$end_date.'"><span>Completed End Date:</span> '.date($date_format,strtotime($end_date)).'</div>';
                                    $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE course_id=%d AND course_version=%d AND customer_status=%s AND completion_date >= %s AND completion_date <= %s ORDER BY id", $course_id, $version, $status, $start_query_date, $end_query_date);

                                    

                                    //$sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND id in (" . implode(',', $entries) . ") AND completion_date >= %s AND completion_date <= %s", 'completed', $start_query_date, $end_query_date);
                    
                                    $results = $wpdb->get_results( $sql_query ); 
                                    if(!empty($results)) {
                                        $response = '';
                                        $response = '<div class="reports-header"><button id="export-report" class="button button-primary">Export</button></div>';
                                        $response .= '<table class="course-progress"><tr>';
                                            if(apply_filters('flms_course_report_show_user_id', true)) {
                                                $response .= '<th>User ID</th>';
                                            }
                                            $response .= '<th>Last name</th>';
                                            $response .= '<th>First name</th>';
                                            if(apply_filters('flms_course_report_show_user_email', true)) {
                                                $response .= '<th>Email</th>';
                                            }
                                            $response .= '<th>Completion date</th>';
                                            $response .= '</tr>';
                                        
                                            foreach($results as $result) {
                                                $user = get_user_by('id', $result->customer_id);
                                                if($user !== false) {
                                                    $response .= '<tr>';
                                                        if(apply_filters('flms_course_report_show_user_id', true)) {
                                                            $response .= '<td><a href="'.admin_url('user-edit.php?user_id='.$user->ID).'" target="_blank">'.$user->ID.'</a></td>';
                                                        }
                                                        $response .= '<td>'.$user->last_name.'</td><td>'.$user->first_name.'</td>';
                                                        if(apply_filters('flms_course_report_show_user_email', true)) {
                                                            $response .= '<td><a href="mailto:'.$user->user_email.'">'.$user->user_email.'</a></td>';
                                                        }
                                                        $response .= '<td>'.date($date_format, strtotime($result->completion_date)).'</td>';
                                                    $response .= '</tr>';
                                                }
                                            }
                                            $response .= '</table>';
                                    } else {
                                        $response = 'No results.';
                                    }
                                    break;
                            
                                case 'incomplete':
                                    $report_information .= '<div data-flms-date-start="'.$start_date.'"><span>Completed Start Date:</span> '.date($date_format,strtotime($start_date)).'</div>';
                                    $report_information .= '<div data-flms-date-end="'.$end_date.'"><span>Completed End Date:</span> '.date($date_format,strtotime($end_date)).'</div>';
                                    $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE course_id=%d AND course_version=%d AND customer_status=%s AND enroll_date >= %s AND enroll_date <= %s ORDER BY id", $course_id, $version, 'enrolled', $start_query_date, $end_query_date);

                                    

                                    //$sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND id in (" . implode(',', $entries) . ") AND completion_date >= %s AND completion_date <= %s", 'completed', $start_query_date, $end_query_date);
                    
                                    $results = $wpdb->get_results( $sql_query ); 
                                    if(!empty($results)) {
                                        $current_time = strtotime(current_time('mysql'));
                                        $response = '';
                                        $time_format = get_option('time_format');
                                        $response = '<div class="reports-header"><button id="export-report" class="button button-primary">Export</button></div>';
                                        $response .= '<table class="course-progress"><tr>';
                                                if(apply_filters('flms_course_report_show_user_id', true)) {
                                                    $response .= '<th>User ID</th>';
                                                }
                                                $response .= '<th>Last name</th><th>First name</th>';
                                                if(apply_filters('flms_course_report_show_user_email', true)) {
                                                    $response .= '<th>Email</th>';
                                                }
                                                $response .= '<th>Enroll date</th>';
                                                $response .= '<th>Progress</th>';
                                                $response .= '<th>Last active</th>';
                                            $response .= '</tr>';
                                            $steps = $course->get_all_course_steps();
                                            $steps_count = count($steps);
                                            foreach($results as $result) {
                                                $user = get_user_by('id', $result->customer_id);
                                                if($user !== false) {
                                                    $steps_completed = maybe_unserialize($result->steps_completed);
                                                    if(!is_array($steps_completed)) {
                                                        $completed = 0;
                                                    } else {
                                                        $completed = count($steps_completed);
                                                    }
                                                    if($steps_count == 0) {
                                                        $percent = '0%';
                                                    } else {
                                                        $percent = absint(100 * (absint($completed) / absint($steps_count))).'%';
                                                    }
                                                    $progress = "$percent ($completed / $steps_count steps)";
                                                    $active_timestamp = strtotime($result->last_active);
                                                    //$last_active = sprintf('%s ago', human_time_diff($active_timestamp, $current_time));
                                                    $last_active = date("$date_format, $time_format", strtotime($result->last_active));
                                                    $response .= '<tr>';
                                                        if(apply_filters('flms_course_report_show_user_id', true)) {
                                                            $response .= '<td><a href="'.admin_url('user-edit.php?user_id='.$user->ID).'" target="_blank">'.$user->ID.'</a></td>';
                                                        }
                                                        $response .= '<td>'.$user->last_name.'</td><td>'.$user->first_name.'</td>';
                                                        if(apply_filters('flms_course_report_show_user_email', true)) {
                                                            $response .= '<td><a href="mailto:'.$user->user_email.'">'.$user->user_email.'</a></td>';
                                                        }
                                                        $response .= '<td>'.date($date_format, strtotime($result->enroll_date)).'</td>';
                                                        $response .= '<td>'.$progress.'</td><td>'.$last_active.'</td>';
                                                    $response .= '</tr>';
                                                }
                                            }
                                        $response .= '</table>';
                                    } else {
                                        $response = 'No results.';
                                    }
                                    break;
                            }
                            
                        }
                    }

                }
                break;
            case 'answers':
                $report_information .= '<div data-report-type="'.$type.'"><span>Report type:</span> Answers Analysis</div>';
                $course_id = $data['flms-course-select'];
                if($course_id == 0) {
                    $response = 'Please select a course.';
                } else {
                    //$report_information .= '<div><span>Course:</span> '.get_the_title($course_id).'</div>';
                    $version = $data['flms-version-select'];
                    if($version == -1) {
                        $response = 'Please select a version.';
                    } else {
                        $course = new FLMS_Course($course_id);
                        $version_name = $course->get_course_version_name($version);
                        $report_information .= '<div data-flms-course-select="'.$course_id.'" data-flms-version-select="'.$version.'"><span>Course:</span> '.$version_name.'</div>';
                        $exam_id = $data['flms-exam-select'];
                        if($exam_id == 0) {
                            $response = 'Please select an exam.';
                        } else {
                            $report_information .= '<div data-flms-exam-select="'.$exam_id.'"><span>Exam:</span> '.get_the_title($exam_id).'</div>';
                            $exam = new FLMS_Exam($exam_id);
                            $flms_active_version = $version;
                            $questions = $exam->get_exam_question_ids();
                            //TODO: Update query for exam questions when using questions by category
                            if(!empty($questions)) {
                                $response = '<div class="reports-header"><div class="reports-key"><div class="correct">Correct</div><div class="incorrect">Incorrect</div></div><div class="toggle-table-tooltips" id="expand-report-toggle"></div></div>';
                                $response .= '<table class="answers-analysis"><tr><th>Question</th><th>Type</th><th>Answer</th><th>% Correct</th></tr>';
                                foreach($questions as $question_id) {
                                    $question = new FLMS_Question($question_id);
                                    $question_type = $question->get_question_type();
                                    //$question_data = maybe_unserialize(get_post_meta($question_id,'flms_question_answer', true ));
                                    if($question_type != 'prompt') {
                                        $question_name = get_the_title($question_id);
                                        $response .= '<tr><td data-title="Question:"><div class="flms-table-tooltip" title="'.$question_name.'"><span class="desktop-name">'.$question_name.'</span></div></td><td data-title="Type:"><div class="flms-table-tooltip" title="'.$question_type.'"><span class="desktop-name">'.$question_type.'</span></div></td><td data-title="Answers:">';
                                        $question_data = $question->get_report_data();
                                        $question_answer = $question->get_question_answer();
                                        $correct_count = 0;
                                        if($question_data === false) {
                                            $response .= '<em>No data</em>';
                                        } else {
                                            switch($question_type) {
                                                case 'essay':
                                                    $response .= '<em>What do we want to do here?</em>';
                                                    break;
                                                case 'single-choice':
                                                    $answer = $question_answer[0];
                                                    $response .= '<div class="answer-reports">';
                                                    foreach($question_data as $k => $v) {
                                                        $response .= '<div';
                                                        if($answer == $k) {
                                                            $response .= ' class="correct"';
                                                            $correct_count = $v;
                                                        }
                                                        $response .= '><span class="answer-total">'.$v.'</span><div class="flms-table-tooltip" title="'.$k.'"></div></div>';
                                                    }
                                                    $response .= '</div>';
                                                    break;
                                                case 'multiple-choice':
                                                    $response .= '<div class="answer-reports">';
                                                    $correct_count = 0;
                                                    foreach($question_data as $k => $v) {
                                                        $response .= '<div';
                                                        if(in_array($k,$question_answer)) {
                                                            $response .= ' class="correct"';
                                                            $correct_count += (int) $v;
                                                        }
                                                        $response .= '><span class="answer-total">'.$v.'</span><div class="flms-table-tooltip" title="'.$k.'"></div></div>';
                                                    }
                                                    //$response .= '</tr><tr>';
                                                    /*foreach($question_data as $k => $v) {
                                                        $response .= '<td';
                                                        if(in_array($k,$question_answer)) {
                                                            $response .= ' class="correct"';
                                                            $correct_count = $v;
                                                        }
                                                        $response .= '>'.$v.'</td>';
                                                    }*/
                                                    $response .= '</div>';
                                                    break;
                                                case 'fill-in-the-blank':
                                                    $response .= '<div class="answer-reports">';
                                                    $tmp = $question_data;
                                                    $correct_count = 0;
                                                    foreach($question_answer as $answer_option) {
                                                        $response .= '<div class="correct">';
                                                        if(isset($question_data[$answer_option])) {
                                                            $response .= '<span class="answer-total">'.$question_data[$answer_option].'</span>';
                                                            $correct_count += (int) $question_data[$answer_option];
                                                            unset($tmp[$answer_option]);
                                                        } else {
                                                            $response .= '<span class="answer-total">0</span>';
                                                        }
                                                        $response .= '<div class="flms-table-tooltip" title="'.ucfirst($answer_option).'"></div>';
                                                        $response .= '</div>';
                                                        
                                                        
                                                    }
                                                    if(!empty($tmp)) {
                                                        //$response .= print_r($tmp,true);
                                                        $others = 0;
                                                        $other_values = array();
                                                        foreach($tmp as $k => $v) {
                                                            if($k == 'flms-incorrect-answers') {
                                                                foreach($v as $i => $j) {
                                                                    $others += (int) $j;
                                                                    $other_values[] = ucwords($i);
                                                                }
                                                            }
                                                            
                                                        }
                                                        $others_text = '<ul class="other-values"><li>'.implode('</li><li>',$other_values).'</li></ul>';
                                                        $response .= '<div class="other-answer"><span class="answer-total">'.$others.'</span>Other <span class="toggle-other-answers"></span>'.$others_text.'</div>';
                                                        
                                                    }
                                                    //$response .= '</tr><tr>';
                                                    /*foreach($question_data as $k => $v) {
                                                        $response .= '<td';
                                                        if(in_array($k,$question_answer)) {
                                                            $response .= ' class="correct"';
                                                            $correct_count = $v;
                                                        }
                                                        $response .= '>'.$v.'</td>';
                                                    }*/
                                                    $response .= '</div>';
                                                    break;
                                                case 'assessment':
                                                    $response .= '<div class="answer-reports">';
                                                    foreach($question_data as $k => $v) {
                                                        $response .= '<div><span class="answer-total">'.$v.'</span>'.$k.'</div>';
                                                    }
                                                    /*$response .= '</tr><tr>';
                                                    foreach($question_data as $k => $v) {
                                                        $response .= '<td>'.$v.'</td>';
                                                    }*/
                                                    $response .= '</div>';
                                                    $correct_count = count($question_data);
                                                    break;
                                            }
                                            
                                        }
                                        
                                        $total = 0;
                                        if(is_array($question_data)) {
                                            if($question_type == 'fill-in-the-blank') {
                                                foreach($question_data as $k => $v) {
                                                    if($k == 'flms-incorrect-answers') {
                                                        foreach($v as $i => $j) {
                                                            $total += (int) $j;
                                                        }
                                                    } else {
                                                        $total += (int) $v;
                                                    }
                                                }
                                            } else {
                                                foreach($question_data as $k => $v) {
                                                    $total += (int) $v;
                                                }
                                            }
                                            if($total > 0) {
                                                $percent = number_format(100 * ($correct_count / $total),2);
                                            } else {
                                                $percent = 0;
                                            }
                                        } else {
                                            $percent = 100;
                                        }
                                        $response .= '</td><td data-title="Percent Correct: "';
                                        if(absint($percent) <= 50) {
                                            $response .= ' class="low-percentage"';
                                        } else if(absint($percent) <= 75) {
                                            $response .= ' class="medium-percentage"';
                                        } else {
                                            $response .= ' class="high-percentage"';
                                        }
                                        $response .= '>';
                                            if($question_type != 'essay' && $question_type != 'assessment' && $total > 0) {
                                                if(absint($percent) <= 50) {
                                                    $response .= '<span class="percentage">'.$percent.'%</span>';
                                                } else if(absint($percent) <= 75) {
                                                    $response .= '<span class="percentage">'.$percent.'%</span>';
                                                } else {
                                                    $response .= '<span class="percentage">'.$percent.'%</span>';
                                                }
                                                
                                                $response .= ' <span class="number-correct">('.$correct_count .' of '.$total.')</span>';
                                            } else {
                                                $response .= 'N/A';
                                            }
                                        $response .= '</td></tr>';
                                    }
                                }
                                $response .= '</table>';
                            }
                            //$response .= print_r($questions,true);    
                        }
                        
                        //$question_category = $data['flms-question-category'];
                    }
                }
                //$response .= print_r($data,true);
                break;
            case 'course_credits': 
                //$response = '<div>This report is not currently active</div>';
                //$response = print_r($data,true);
                $credit_type = $data['course-credit-select'];
                $course_credits_module = new FLMS_Module_Course_Credits();
				$label = $course_credits_module->get_credit_label($credit_type);
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];
                $date_format = get_option('date_format');
                $report_information .= '<div data-report-type="'.$type.'"><span>Report type:</span> Credit Reporting</div>';
                $report_information .= '<div data-flms-course-credit-select="'.$credit_type.'"><span>Credit Type:</span> '.$label.'</div>';
                $report_information .= '<div data-flms-date-start="'.$start_date.'"><span>Start Date:</span> '.date($date_format,strtotime($start_date)).'</div>';
                $report_information .= '<div data-flms-date-end="'.$end_date.'"><span>End Date:</span> '.date($date_format,strtotime($end_date)).'</div>';
                global $wpdb;
                $table = FLMS_REPORTING_TABLE;
                if(isset($data['reporting-fee-select'])) {
                    $reporting_fee_select = $data['reporting-fee-select'];
                    $report_fee_label = 'No';
                    if($reporting_fee_select == 1) {
                        $report_fee_label = 'Yes';
                    }
                    $report_information .= '<div data-flms-reporting-fee-select="'.$reporting_fee_select.'"><span>Accepted Reporting Fee:</span> '.$report_fee_label.'</div>';
                    if($reporting_fee_select >= 0) {
                        $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s AND accepts_reporting_fee=%d", $credit_type, $reporting_fee_select);
                    } else {
                        $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s", $credit_type);
                    }
                } else {
                    $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s", $credit_type);
                }
                $entries = $wpdb->get_col( $sql_query); 
                if(!empty($entries)) {
                    //$response .= '<pre>'.print_r($entries,true).'</pre>';
                    $course_credits = new FLMS_Module_Course_Credits();
                    $table = FLMS_ACTIVITY_TABLE;
                    $array_string = implode(',',$entries);
                    $start_query_date = date('Y-m-d 00:00:00',strtotime($start_date)); //2022-09-29 19:43:24
		            $end_query_date = date('Y-m-d 23:59:59',strtotime($end_date)); //2022-09-29 19:43:24

                    $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND id in (" . implode(',', $entries) . ") AND completion_date >= %s AND completion_date <= %s", 'completed', $start_query_date, $end_query_date);
                    $results = $wpdb->get_results( $sql_query ); 
                    if(!empty($results)) {
                        $credit_report_headers = $this->get_credit_report_headers();
                        $response = '<div class="reports-header"><button id="export-report" class="button button-primary">Export</button></div>';
                        $response .= '<table><tr>';
                            foreach($credit_report_headers as $header) {
                                $response .= '<th>'.apply_filters('flms_credit_report_header', $header, $credit_type).'</th>';
                            }
                        $response .= '</tr>';
                        $has_results = false;
                        foreach($results as $result) {
                            $data = $this->get_customer_credit_data($result, $credit_type);
                            if($data['credits'] > 0) {
                                $has_results = true;
                                $response .= '<tr>';
                                    $response .= '<td data-title="Last name">'.$data['last_name'].'</td>';
                                    $response .= '<td data-title="First name">'.$data['first_name'].'</td>';
                                    $response .= '<td data-title="Email">'.$data['email'].'</td>';
                                    $response .= '<td data-title="License number">'.$data['license_number'].'</td>';
                                    $response .= '<td data-title="Course">'.$data['course'].'</td>';
                                    if(flms_is_module_active('course_numbers')) {
                                        $response .= '<td data-title="'.apply_filters('flms_credit_report_header', 'Course number', $credit_type).'">'.$data['course_number'].'</td>';
                                    }
                                    $response .= '<td data-title="Completed">'.$data['date'].'</td>';
                                    $response .= '<td data-title="Credits">'.$data['credits'].'</td>';       
                                $response .= '</tr>';
                            }
                            
                        }
                        if(!$has_results) {
                            $colspan = 7;
                            if(flms_is_module_active('course_numbers')) {
                                $colspan = 8;
                            }
                            $response .= '<tr><td colspan="'.$colspan.'"><em>No results</em></td></tr>';
                        }
                        
                    } else {
                        $response = 'No results.';
                    }
                } else {
                    $response = 'No results.';
                }

                
                break;
            case 'royalties':
                //$response .= '<pre>'.print_r($data,true).'</pre>';
                $course_royalties = array();
                $args = array(
                    'post_type' => 'flms-courses',
                    'post_status' => 'publish',
                    'orderby'   => 'title',
                    'order'     => 'ASC',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'tax_query' => array(
                        array(
                            'taxonomy' => $data['taxonomy-select'],
                            'field' => 'term_id',
                            'terms' => array($data['selected-taxonomy'])
                        )
                    )
                );
                
                $date_format = get_option('date_format');
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];

                $taxonomy = get_taxonomy($data['taxonomy-select']);
                $term = get_term_by('term_taxonomy_id', $data['selected-taxonomy'], $data['taxonomy-select']);

                $report_information .= '<div data-report-type="'.$type.'"><span>Report type:</span> Royalty Reporting</div>';
                $report_information .= '<div data-flms-taxonomy-select="'.$data['taxonomy-select'].'"><span>Taxonomy:</span> '.$taxonomy->labels->singular_name.'</div>';
                $report_information .= '<div data-flms-selected-taxonomy="'.$data['selected-taxonomy'].'"><span>'.$taxonomy->labels->singular_name.':</span> '.$term->name.'</div>';
                $report_information .= '<div data-flms-date-start="'.$start_date.'"><span>Start Date:</span> '.date($date_format,strtotime($start_date)).'</div>';
                $report_information .= '<div data-flms-date-end="'.$end_date.'"><span>End Date:</span> '.date($date_format,strtotime($end_date)).'</div>';

                $royalty_type = sanitize_text_field(get_term_meta($term->term_id, 'flms_royalty_type', true));
                $amount = absint(get_term_meta($term->term_id, 'flms_royalty_amount', true));
                $percentage = absint(get_term_meta($term->term_id, 'flms_royalty_percentage', true));

                $course_query = new WP_Query( $args );
                if($course_query->have_posts()) {
                    //$response .= '<pre>'.print_r($course_query->posts,true).'</pre>';
                    //$response .= get_post_meta(5827,'flms_woocommerce_product_id', true).'<br><br>'; //5828
                    //update_post_meta($product_id, 'flms_woocommerce_product_id', $course_id);
                    $args = array(
                        'post_type' => array('product','product_variation'),
                        'posts_per_page' => -1,
                        'meta_key' => 'flms_woocommerce_product_id',
                        'meta_value' => $course_query->posts, //'meta_value' => array('yes'),
                        'meta_compare' => 'IN' //'meta_compare' => 'NOT IN'
                    );
                    $products = wc_get_products($args);
                    $products_array = array();
                    if(!empty($products)) {
                        foreach($products as $product) {
                            //$response .= $product->get_id().'<br>';
                            $products_array[] = $product->get_id();
                            if ( $product->is_type( 'variable' ) ) {
                                $variations = $product->get_available_variations();
                                foreach ( $variations as $variation ) {
                                    //35652,35651
                                    //$response .= $variation->get_id();
                                   // $response .= '<pre>'.print_r($variation,true).'</pre>';
                                    //$products_array[] = $variation->get_id();
                                }
                             }
                        }
                    }
                    wp_reset_postdata(  );                    
                    //$response .= '<pre>'.print_r($products_array,true).'</pre>';
                    //$response .= '<pre>'.print_r($products,true).'</pre>';
                    
                    
                    $orders = wc_get_orders( array(
                        'limit' => -1,
                        'date_after' => $start_date,
                        'date_before' => $end_date,
                        'prices_include_tax' => 'no',
                        'status' => array('wc-processing', 'wc-completed'),
                    )); 
                    //$response .= '<pre>'.print_r($orders, true).'</pre>';
                    if(!empty($orders)) {
                        foreach($orders as $order) {
                            foreach ( $order->get_items() as $item_id => $item ) {
                                $product_id = $item->get_product_id();
                                $product = wc_get_product($product_id);
                                $price = $product->get_price();
                                //$variation_id = $item->get_variation_id();
                                /*if( $variation_id > 0 ) {
                                    $variation_product = new WC_Product_Variation($variation_id);
                                    $price = $variation_product->get_price();
                                }*/
                                if(in_array($product_id,$products_array)) {
                                    $course = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
                                    $quantity = $item->get_quantity();
                                    $group_seats = absint($item->get_meta('group_seats'));
                                    $seats = 0;
                                    if($group_seats > 0) {
                                        $quantity = $quantity * $group_seats;
                                    }
                                    $subtotal = $item->get_subtotal();
                                    $due = 0;
                                    $royalty = "$0";
                                    if($royalty_type == 'percentage') {
                                        $royalty = "$percentage%";
                                        if($percentage > 0) {
                                            $due = ($percentage / 100) * $subtotal;
                                        }
                                    } else if($royalty_type == 'flat_fee') {
                                        $currency = '$';
                                        if(function_exists('get_woocommerce_currency_symbol')) {
                                            $currency = get_woocommerce_currency_symbol();
                                        }
                                        $royalty = "$currency$amount";
                                        if($amount > 0) {
                                            $due = $amount * $quantity;
                                        }
                                        if($group_seats > 0) {
                                            $seats = $group_seats;
                                        }
                                    } else if($royalty_type == 'per_course') {
                                        $course_royalty_type = get_post_meta($course, 'flms_royalty_type_'.$term->term_id, true);
                                        if($course_royalty_type == 'percentage') {
                                            $value = get_post_meta($course, 'flms_royalty_percentage_'.$term->term_id, true);
                                            $royalty = "$value%";
                                            if($value > 0) {
                                                $due = ($value / 100) * $subtotal;
                                            }
                                        } else if($course_royalty_type == 'flat_fee') {
                                            $value = get_post_meta($course, 'flms_royalty_amount_'.$term->term_id, true);
                                            $currency = '$';
                                            if(function_exists('get_woocommerce_currency_symbol')) {
                                                $currency = get_woocommerce_currency_symbol();
                                            }
                                            $royalty = "$currency$value";
                                            if($value > 0) {
                                                $due = $value * $quantity;
                                            }
                                            if($group_seats > 0) {
                                                $seats = $group_seats;
                                            }
                                        }
                                    }
                                    
                                    //$cost = $price * $quantity;
                                    
                                    if(!isset($course_royalties[$course])) {
                                        $course_royalties[$course] = array();
                                        /*$course_royalties[$course] = array(
                                            'purchases' => 0,
                                            'total' => 0,
                                            'seats' => 0,
                                            'royalty_percent' => $royalty,
                                            'due' => 0,
                                        );*/
                                    }
                                    $course_royalties[$course][] = array(
                                        'order_date' => $order->get_date_created(),
                                        'order_number' => $order->get_id(),
                                        'total' => $subtotal,
                                        'due' => $due,
                                        'royalty_percent' => $royalty,
                                        'seats' => $seats,
                                    );
                                    /*$course_royalties[$course]['purchases'] += $quantity;
                                    $course_royalties[$course]['total'] += $subtotal;
                                    $course_royalties[$course]['due'] += $due;
                                    //$course_royalties[$course]['cost'] += $cost;
                                    
                                    //$course_royalties[$course]['total'] .= $variation_id . $type. $price.' - ';
                                    $course_royalties[$course]['seats'] += $seats;*/
                                }
                            }
                        }
                    }

                    
                } 
                if(!empty($course_royalties)) {
                    $all_purchases_link = trailingslashit(get_bloginfo('url')).'wp-admin/admin.php?page=wc-admin&period=custom&compare=previous_year&path=%2Fanalytics%2Fproducts&is-variable=true&after='.$start_date.'&before='.$end_date;
                    $response = '<div class="reports-header"><button id="export-report" class="button button-primary">Export</button><a href="'.$all_purchases_link.'" class="button button-primary" target="_blank">View all Woocommerce orders for this period</a></div>';
                    $response .= '<table><tr>';
                        if(flms_is_module_active('course_numbers')) {
                            $response .= '<th>Course number</th>';
                        }
                        $response .= '<th>Title</th>';
                        $response .= '<th>Order Date</th>';
                        $response .= '<th>Order Number</th>';
                        /*if(flms_is_module_active('groups')) {
                            $response .= '<sup>*</sup>';
                        }*/
                        /*if(flms_is_module_active('groups')) {
                            $response .= '<th>Seats</th>';
                        }*/
                        //$response .= '<th>Total cost</th>';
                        if($royalty_type == 'per_course') {
                            if($royalty_type == 'percentage') {
                                $response .= '<th>Royalty percentage</th>';
                            } else {
                                $response .= '<th>Royalty amount</th>';
                                
                            }
                        }
                        $response .= '<th>'.apply_filters('flms_royalties_total_text', 'Total Revenue').'</th>';
                        $response .= '<th>'.apply_filters('flms_royalties_due_text', 'Royalties due').'</th>';
                    $response .= '</tr>';
                    $has_results = false;
                    $total_sales = 0;
                    $total_royalties = 0;
                    $date_format = apply_filters('flms_export_date_format', get_option('date_format'), 'royalties');
                    foreach($course_royalties as $course_id => $course_orders) {
                        $course_title = get_the_title($course_id);
                        $product_id = get_post_meta($course_id, 'flms_woocommerce_product_id', true);
                        $course_link = '<a href="'.get_edit_post_link($course_id).'" title="'.$course_title.'" target="_blank">'.$course_title.'</a>';
                        $order_link_prefix = '<a target="_blank" href="'.get_bloginfo('url').'/wp-admin/admin.php?page=wc-orders&action=edit&id=';
                        if(flms_is_module_active('course_numbers')) {
                            $course_numbers = new FLMS_Module_Course_Numbers();
                            $course_number = $course_numbers->get_course_number($course_id);
                        }
                        if(is_array($course_orders)) {
                            foreach($course_orders as $data) {
                                $response .= '<tr>';
                                    if(flms_is_module_active('course_numbers')) {
                                        $response .= '<td data-title="Course number">'.$course_number.'</td>';
                                    }
                                    $response .= '<td data-title="Title">'.$course_link.'</td>';
                                    $response .= '<td data-title="Order Date">'.date($date_format,strtotime($data['order_date'])).'</td>';
                                    $response .= '<td data-title="Order Number">'.$order_link_prefix.$data['order_number'].'">'.$data['order_number'].'</a></td>';
                                    if($royalty_type == 'per_course') {
                                        $response .= '<td data-title="Royalty percentage">'.$data['royalty_percent'].'</td>';
                                    }
                                    $response .= '<td data-title="Total revenue">'.wc_price($data['total']).'</td>';
                                    $response .= '<td data-title="Royalties due">'.wc_price($data['due']);
                                    if($data['seats'] > 0) {
                                        //$response .= '<sup>*</sup>';
                                    }
                                    $response .= '</td>';
                                $response .= '</tr>';
                                $total_sales += $data['total'];
                                $total_royalties += $data['due'];
                            }
                        }
                    }
                    //footer
                    $response .= '<tr>';
                        if(flms_is_module_active('course_numbers')) {
                            $response .= '<td></td>';
                        }
                        $response .= '<td></td>';
                        $response .= '<td></td>';
                        $response .= '<td></td>';
                        if($royalty_type == 'per_course') {
                            $response .= '<td></td>';
                        }
                        $response .= '<td><strong>'.wc_price($total_sales).'</strong></td>';
                        $response .= '<td><strong>'.wc_price($total_royalties).'</strong></td>';
                    $response .= '</tr>';
                    $response .= '</table>';
                    /*if(flms_is_module_active('groups')) {
                        $response .= '<div class="reports-footer">';
                            $response .= '<span class="flms-asterisk-notice align-right"><sup>*</sup>Royalty total calculated on a per seat basis.</span>';
                        $response .= '</div>';
                    }*/
                } else {
                    $response = '<em>Nothing found with the given criteria.</em>';
                }
                wp_reset_query();
                break;
        }
		unset($_POST['action']);
        $report['report_content'] = $response; //update to analysze the actual report
		$report['report_data'] = $report_data;
        $report['report_information'] = $report_information;
        if($name != '') {
            $report['report_name'] = $name;
        } else {
            $report['report_name'] = '';
        }
        return $report;
    }

    public function get_saved_report($key) {
        $reports = maybe_unserialize(get_option('flms_reports'));
        if(isset($reports[$key])) {
            $report_info = $reports[$key];
            //$data = maybe_unserialize(stripslashes($report_info['data']));
            return $this->generate_report($report_info['data'], $report_info['name']);
        }
    }

    public function export_report($data) {
        $sanitized_data = array();
        $data = maybe_unserialize(stripslashes($data));
        if(is_array($data)) {
            foreach($data as $k => $v) {
                $sanitized_data[$k] = sanitize_text_field($v);
            }
        }
        $data = $sanitized_data;
        $type = $data['report-type'];
        $separator = apply_filters('flms_csv_separator', "\t");
        switch($type) {
            case 'course_progress':
                $exporter = new FLMS_Exporter();
		        $dir = $exporter->get_export_dir();
                $prefix = strtolower(str_replace(' ','',FLMS_PLUGIN_NAME)).'-';
                $status = $data['flms-course-status-select'];
                $course_id = $data['flms-course-select'];
                $version = $data['flms-version-select'];
                $course = new FLMS_Course($course_id);
                $version_name = sanitize_title($course->get_course_version_name($version));
                $date_format = get_option('date_format');
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];
                $start_query_date = date('Y-m-d 00:00:00',strtotime($start_date)); //2022-09-29 19:43:24
                $end_query_date = date('Y-m-d 23:59:59',strtotime($end_date)); //2022-09-29 19:43:24
                $filename = $prefix.$version_name.'-'.$status.'-course-progress-'.$start_date.'-'.$end_date.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
                global $wpdb;
                $table = FLMS_ACTIVITY_TABLE;
                switch($status) {
                    case 'completed':
                        $formatted_headers = array();
                        if(apply_filters('flms_course_report_show_user_id', true)) {
                            $formatted_headers[] = 'User ID';
                        }
                        $formatted_headers[] = 'Last name';
                        $formatted_headers[] = 'First name';
                        if(apply_filters('flms_course_report_show_user_email', true)) {
                            $formatted_headers[] = 'Email';
                        }
                        $formatted_headers[] = 'Completion date';
                        
                        $header_fields = implode($separator,$formatted_headers);
                        $header = "$header_fields";
                        $header .= "\n";
                        fwrite($open,$header);
                        $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE course_id=%d AND course_version=%d AND customer_status=%s AND completion_date >= %s AND completion_date <= %s ORDER BY id", $course_id, $version, $status, $start_query_date, $end_query_date);
                        $results = $wpdb->get_results( $sql_query ); 
                        if(!empty($results)) {
                            foreach($results as $result) {
                                $user = get_user_by('id', $result->customer_id);
                                if($user !== false) {
                                    $fields = array();
                                    if(apply_filters('flms_course_report_show_user_id', true)) {
                                        $fields[] = $user->ID;
                                    }
                                    $fields[] = $user->last_name;
                                    $fields[] = $user->first_name;
                                    if(apply_filters('flms_course_report_show_user_email', true)) {
                                        $fields[] = $user->user_email;
                                    }
                                    $fields[] = date($date_format, strtotime($result->completion_date));
                                    fputcsv($open,$fields,$separator);
                                }
                            }
                        } 
                        break;
                
                    case 'incomplete':
                        $formatted_headers = array();
                        if(apply_filters('flms_course_report_show_user_id', true)) {
                            $formatted_headers[] = 'User ID';
                        }
                        $formatted_headers[] = 'Last name';
                        $formatted_headers[] = 'First name';
                        if(apply_filters('flms_course_report_show_user_email', true)) {
                            $formatted_headers[] = 'Email';
                        }
                        $formatted_headers[] = 'Enroll date';
                        $formatted_headers[] = 'Progress';
                        $formatted_headers[] = 'Last active';
                        $header_fields = implode($separator,$formatted_headers);
                        $header = "$header_fields";
                        $header .= "\n";
                        fwrite($open,$header);
                        $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE course_id=%d AND course_version=%d AND customer_status=%s AND enroll_date >= %s AND enroll_date <= %s ORDER BY id", $course_id, $version, 'enrolled', $start_query_date, $end_query_date);
                        $results = $wpdb->get_results( $sql_query ); 
                        if(!empty($results)) {
                            $current_time = strtotime(current_time('mysql'));
                            $response = '';
                            $time_format = get_option('time_format');
                            $steps = $course->get_all_course_steps();
                            $steps_count = count($steps);
                            foreach($results as $result) {
                                $user = get_user_by('id', $result->customer_id);
                                if($user !== false) {
                                    $steps_completed = maybe_unserialize($result->steps_completed);
                                    if(!is_array($steps_completed)) {
                                        $completed = 0;
                                    } else {
                                        $completed = count($steps_completed);
                                    }
                                    if($steps_count == 0) {
                                        $percent = '0%';
                                    } else {
                                        $percent = absint(100 * (absint($completed) / absint($steps_count))).'%';
                                    }
                                    $progress = "$percent ($completed of $steps_count steps)";
                                    $active_timestamp = strtotime($result->last_active);
                                    $last_active = date("$date_format, $time_format", strtotime($result->last_active));

                                    $fields = array();
                                    if(apply_filters('flms_course_report_show_user_id', true)) {
                                        $fields[] = $user->ID;
                                    }
                                    $fields[] = $user->last_name;
                                    $fields[] = $user->first_name;
                                    if(apply_filters('flms_course_report_show_user_email', true)) {
                                        $fields[] = $user->user_email;
                                    }
                                    $fields[] = date($date_format, strtotime($result->enroll_date));
                                    $fields[] = $progress;
                                    $fields[] = $last_active;
                                    fputcsv($open,$fields,$separator);
                                }
                            }
                        }
                        break;
                }
                $url = trailingslashit($exporter->get_export_dir_url()) . $filename;
                return array(
                    'filename' => $filename,
                    'filepath' => $url
                );
                break;
            case 'answers':
                return '';
                break;
            case 'course_credits': 
                $exporter = new FLMS_Exporter();
		        $dir = $exporter->get_export_dir();
                $credit_type = $data['course-credit-select'];
                $course_credits_module = new FLMS_Module_Course_Credits();
				$label = preg_replace('/[\W\s\/]+/', '',strip_tags(strtolower($course_credits_module->get_credit_label($credit_type))));
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];
                $prefix = strtolower(str_replace(' ','',FLMS_PLUGIN_NAME)).'-';
                $accepted = '-';
                if(isset($data['reporting-fee-select'])) {
                    $reporting_fee_select = $data['reporting-fee-select'];
                    if($reporting_fee_select == 1) {
                        $accepted = '-accepted-reporting-fee-';
                    } else {
                        $accepted = '-declined-reporting-fee-';
                    }
                }
                $filename = $prefix.$label.$accepted.'credit-report-'.$start_date.'-'.$end_date.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
                $formatted_headers = array();
                $credit_report_headers = $this->get_credit_report_headers();
                foreach($credit_report_headers as $header) {
                    $formatted_headers[] = apply_filters('flms_credit_report_header', $header, $credit_type);
                }
				$header_fields = implode($separator,$formatted_headers);
				$header = "$header_fields";
				$header .= "\n";
				fwrite($open,$header);
                
                $date_format = get_option('date_format');
                
                global $wpdb;
                $table = FLMS_REPORTING_TABLE;
                if(isset($data['reporting-fee-select'])) {
                    $reporting_fee_select = $data['reporting-fee-select'];
                    $report_fee_label = 'No';
                    if($reporting_fee_select == 1) {
                        $report_fee_label = 'Yes';
                    }
                    if($reporting_fee_select >= 0) {
                        $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s AND accepts_reporting_fee=%d", $credit_type, $reporting_fee_select);
                    } else {
                        $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s", $credit_type);
                    }
                } else {
                    $sql_query = $wpdb->prepare("SELECT entry_id FROM $table WHERE credit_type=%s", $credit_type);
                }
                $entries = $wpdb->get_col( $sql_query); 
                if(!empty($entries)) {
                    
                    $table = FLMS_ACTIVITY_TABLE;
                    $array_string = implode(',',$entries);
                    $start_query_date = date('Y-m-d 00:00:00',strtotime($start_date)); //2022-09-29 19:43:24
		            $end_query_date = date('Y-m-d 23:59:59',strtotime($end_date)); //2022-09-29 19:43:24

                    $sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND id in (" . implode(',', $entries) . ") AND completion_date >= %s AND completion_date <= %s", 'completed', $start_query_date, $end_query_date);
                    $results = $wpdb->get_results( $sql_query ); 
                    if(!empty($results)) {
                        foreach($results as $result) {
                            $data = $this->get_customer_credit_data($result, $credit_type, true);
                            if($data['credits'] > 0) {
                                $fields = array();
                                $fields[] = $data['last_name'];
                                $fields[] = $data['first_name'];
                                $fields[] = $data['email'];
                                $fields[] = $data['license_number'];
                                $fields[] = $data['course'];
                                if(flms_is_module_active('course_numbers')) {
                                    $fields[] = $data['course_number'];
                                }
                                $fields[] = $data['date'];
                                $fields[] = $data['credits'];
                                fputcsv($open,$fields,$separator);
                            }
                        }
                        
                    } 
                    fclose($open);
                } 
                $url = trailingslashit($exporter->get_export_dir_url()) . $filename;
                return array(
                    'filename' => $filename,
                    'filepath' => $url
                );
                break;
            case 'royalties': 

                $exporter = new FLMS_Exporter();
                $dir = $exporter->get_export_dir();
                $term = get_term_by('term_taxonomy_id', $data['selected-taxonomy'], $data['taxonomy-select']);
                $royalty_type = sanitize_text_field(get_term_meta($term->term_id, 'flms_royalty_type', true));
                $amount = absint(get_term_meta($term->term_id, 'flms_royalty_amount', true));
                $percentage = absint(get_term_meta($term->term_id, 'flms_royalty_percentage', true));
                
                $label = preg_replace('/[\W\s\/]+/', '',strip_tags(strtolower($term->name)));
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];
                $prefix = strtolower(str_replace(' ','',FLMS_PLUGIN_NAME)).'-';
                $filename = $prefix.$label.'-royalties-'.$start_date.'-'.$end_date.'.csv';
                $file = $dir . '/'. $filename;
                $open = fopen( $file, "w" ); 
                $formatted_headers = array();

                $report_headers = array();
                if(flms_is_module_active('course_numbers')) {
                    $report_headers[] = 'Course Number';
                }
                $report_headers[] = 'Title';
                $report_headers[] = 'Order Date';
                $report_headers[] = 'Order Number';

                /*if(apply_filters('flms_export_royalty_fees', true)) {
                    if($royalty_type == 'percentage') {
                        $report_headers[] = 'Royalty percentage';
                    } else {
                        $report_headers[] = 'Royalty amount';
                    }
                }*/
                if($royalty_type == 'per_course') {
                    $report_headers[] = 'Royalty';
                }
                $report_headers[] = apply_filters('flms_royalties_total_text', 'Total Revenue');
                $report_headers[] = apply_filters('flms_royalties_due_text', 'Royalties due');
                


                $header_fields = implode($separator,$report_headers);
                $header = "$header_fields";
                $header .= "\n";
                fwrite($open,$header);

                $course_royalties = array();
                $args = array(
                    'post_type' => 'flms-courses',
                    'post_status' => 'publish',
                    'orderby'   => 'title',
                    'order'     => 'ASC',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'tax_query' => array(
                        array(
                            'taxonomy' => $data['taxonomy-select'],
                            'field' => 'term_id',
                            'terms' => array($data['selected-taxonomy'])
                        )
                    )
                );

                $date_format = apply_filters('flms_export_date_format', get_option('date_format'), 'royalties');
                $start_date = $data['date-start'];
                $end_date = $data['date-end'];

                $taxonomy = get_taxonomy($data['taxonomy-select']);
                
                $course_query = new WP_Query( $args );
                if($course_query->have_posts()) {
                    //$response .= '<pre>'.print_r($course_query->posts,true).'</pre>';
                    //$response .= get_post_meta(5827,'flms_woocommerce_product_id', true).'<br><br>'; //5828
                    //update_post_meta($product_id, 'flms_woocommerce_product_id', $course_id);
                    $args = array(
                        'post_type' => array('product','product_variation'),
                        'posts_per_page' => -1,
                        'meta_key' => 'flms_woocommerce_product_id',
                        'meta_value' => $course_query->posts, //'meta_value' => array('yes'),
                        'meta_compare' => 'IN' //'meta_compare' => 'NOT IN'
                    );
                    $products = wc_get_products($args);
                    $products_array = array();
                    if(!empty($products)) {
                        foreach($products as $product) {
                            //$response .= $product->get_id().'<br>';
                            $products_array[] = $product->get_id();
                        }
                    }
                    wp_reset_postdata(  );                    
                    
                    $orders = wc_get_orders( array(
                        'limit' => -1,
                        'date_after' => $start_date,
                        'date_before' => $end_date,
                        'prices_include_tax' => 'no',
                        'status' => array('wc-processing', 'wc-completed'),
                    )); 
                    if(!empty($orders)) {
                        foreach($orders as $order) {
                            foreach ( $order->get_items() as $item_id => $item ) {
                                $product_id = $item->get_product_id();
                                $product = wc_get_product($product_id);
                                $price = $product->get_price();
                                if(in_array($product_id,$products_array)) {
                                    $quantity = $item->get_quantity();
                                    $group_seats = absint($item->get_meta('group_seats'));
                                    $seats = '0';
                                    if($group_seats > 0) {
                                        $quantity = $quantity * $group_seats;
                                        //$seats = $group_seats;
                                    }
                                    $subtotal = $item->get_subtotal();
                                    $due = 0;
                                    $royalty = '';
                                    $course = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
                                    
                                    //
                                    if($royalty_type == 'percentage') {
                                        $royalty = "$percentage%";
                                        if($percentage > 0) {
                                            $due = ($percentage / 100) * $subtotal;
                                        }
                                    } else if($royalty_type == 'flat_fee') {
                                        $royalty = "$amount";
                                        if($amount > 0) {
                                            $due = $amount * $quantity;
                                        }
                                    } else if($royalty_type == 'per_course') {
                                        $course_royalty_type = get_post_meta($course, 'flms_royalty_type_'.$term->term_id, true);
                                        if($course_royalty_type == 'percentage') {
                                            $value = get_post_meta($course, 'flms_royalty_percentage_'.$term->term_id, true);
                                            $royalty = "$value%";
                                            if($value > 0) {
                                                $due = ($value / 100) * $subtotal;
                                            }
                                        } else if($course_royalty_type == 'flat_fee') {
                                            $value = get_post_meta($course, 'flms_royalty_amount_'.$term->term_id, true);
                                            $royalty = "$value";
                                            if($value > 0) {
                                                $due = $value * $quantity;
                                            }
                                        }
                                    } 
                                    //


                                    if(!isset($course_royalties[$course])) {
                                        $course_royalties[$course] = array();
                                        /*$course_royalties[$course] = array(
                                            'purchases' => 0,
                                            'total' => 0,
                                            'seats' => 0,
                                            'royalty_percent' => $royalty,
                                            'due' => 0,
                                        );*/
                                    }
                                    $course_royalties[$course][] = array(
                                        'order_date' => $order->get_date_created(),
                                        'order_number' => $order->get_id(),
                                        'total' => $subtotal,
                                        'due' => $due,
                                        'royalty_percent' => $royalty,
                                    );
                                    
                                }
                            }
                        }
                    }
                } 
                if(!empty($course_royalties)) {
                    $currency = get_woocommerce_currency_symbol();
                    foreach($course_royalties as $course_id => $course_orders) {
                        if(is_array($course_orders)) {
                            $course_title = get_the_title($course_id);
                            $product_id = get_post_meta($course_id, 'flms_woocommerce_product_id', true);
                            foreach($course_orders as $data) {
                                $fields = array();
                                if(flms_is_module_active('course_numbers')) {
                                    $course_numbers = new FLMS_Module_Course_Numbers();
                                    $course_number = $course_numbers->get_course_number($course_id, 'inherit', 'global');
                                    $fields[] = $course_number;
                                }
                                $fields[] = $course_title;
                                $fields[] = date($date_format,strtotime($data['order_date']));
                                $fields[] = $data['order_number'];
                                
                                if($royalty_type == 'per_course') {
                                    $fields[] = $data['royalty_percent'];
                                }
                                $fields[] =  number_format(floatval($data['total']), 2);
                                $fields[] = number_format(floatval($data['due']), 2);
                                
                                fputcsv($open,$fields,$separator);
                            }
                        }
                        
                    }
                }
                wp_reset_query();
                fclose($open);
                
                $url = trailingslashit($exporter->get_export_dir_url()) . $filename;
                return array(
                    'filename' => $filename,
                    'filepath' => $url
                );
                break;
                
                
        }
		
    }

    public function get_customer_credit_data($result, $credit_type, $export = false) {
        $data = array();
        $date_format = get_option('date_format');
        $course_id = $result->course_id;
        $course_version = $result->course_version;
        $course_data = "$course_id:$course_version";
        $course_credits = new FLMS_Module_Course_Credits();
        $credits = $course_credits->get_course_credits($course_data);
        $credit_value = 0;
        if(isset($credits[$credit_type])) {
            $credit_value = $credits[$credit_type];
        }
        $data['credits'] = $credit_value;
        if($credit_value > 0) {

            $customer_id = $result->customer_id;
            $customer = get_user_by('id', $customer_id);
            
            $course = new FLMS_Course($course_id);
            
            $course_title = $course->get_course_version_name($course_version);
            $course_link = '<a href="'.get_edit_post_link($course_id).'&set-course-version='.$course_version.'" title="'.$course_title.'" target="_blank">'.$course_title.'</a>';
            if ( $customer !== false ) {
                $data['last_name'] = $customer->last_name;
                $data['first_name'] = $customer->first_name;
                if(!$export) {
                    $data['email'] = '<a href="mailto:'.$customer->user_email.'">'.$customer->user_email.'</a>';
                } else {
                    $data['email'] = $customer->user_email;
                }
                $value = get_user_meta( $customer_id, "flms_license-$credit_type", true);
                $data['license_number'] = $value;
            } else {
                $data['last_name'] = '<em>Customer removed</em>';
                $data['first_name'] = '<em>Customer removed</em>';
                $data['email'] = '<em>Customer removed</em>';;
                $data['license_number'] = '<em>Customer removed</em>';
            }
            if(!$export) {
                $data['course'] = $course_link;
            } else {
                $data['course'] = $course_title;
            }

            if(flms_is_module_active('course_numbers')) {
                global $flms_course_version_content;
                $data['course_number'] = '';
                if(isset($flms_course_version_content["$course_version"]['course_numbers'][$credit_type])) {
                    $data['course_number'] = $flms_course_version_content["$course_version"]['course_numbers'][$credit_type];
                }
            }
            //$response .= '<td>'.$result->course_version.'</td>';
            //$response .= '<td>'.ucwords($result->customer_status).'</td>';
            //$response .= '<td>'.date($date_format,strtotime($result->enroll_date)).'</td>';
            $data['date'] = date($date_format,strtotime($result->completion_date));
        }
        return $data;
    }

    public function delete_report($active_report) {
        $reports = maybe_unserialize(get_option('flms_reports'));
        if($active_report >= 0) {
            unset($reports[$active_report]);
            update_option('flms_reports',maybe_serialize(array_values($reports)));
        } 
        
    }

    public function get_credit_report_headers() {
        $headers = array();
        $headers[] = 'Last name';
        $headers[] = 'First name';
        $headers[] = 'Email';
        $headers[] = flms_get_label('license_singular') .' number';
        $headers[] = 'Course';
        if(flms_is_module_active('course_numbers')) {
            $headers[] = 'Course number';
        }
        $headers[] = 'Completed';
        $headers[] = 'Credits';
        return $headers;
        //'Course status',
        //'Enrolled',
        //'Version',
        //'Accepts credit reporting',

    }
}
new FLMS_Reports();