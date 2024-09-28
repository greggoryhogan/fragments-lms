<?php
class FLMS_Cron {
    
    public function __construct() {
        
	}

    public function import_courses($file, $import_action, $processed_rows = 0, $field_indexes = array(), $errors = array(), $user_id = 0) {
        $course_data = array();
        $course_manager = new FLMS_Course_Manager();
        if (($handle = fopen($file, 'r')) !== false) {
            $rows = 0;
            $deliminator = flms_detect_csv_elimiter($file);
            while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
                if(++$rows > 1) { //skip headers
                    $course_data[] = $data;
                }
            }
            fclose($handle);
        }
        $default_max_records = apply_filters('flms_import_record_iterator_count', 75);
        $max_records_per_iteration = apply_filters('flms_courses_per_import', $default_max_records);
        $max_rows = $processed_rows + $max_records_per_iteration;
        $course_data_count = count($course_data);
        $max_rows_reached = false;
        
        for($i = $processed_rows; $i < $course_data_count && $i < $max_rows; $i++) {
            $data = $course_data[$i];
            $continue = true;
            $course_id = $data[$field_indexes['ID']];
            $question_args = array();
            if($field_indexes['Title'] != -2) {
                $question_args['post_title'] = $data[$field_indexes['Title']];
            }
            if($field_indexes['Post Content'] != -2) {
                $question_args['post_content'] = $data[$field_indexes['Post Content']];
            }
            if($field_indexes['Status'] != -2) {
                $question_args['post_status'] = $data[$field_indexes['Status']];
            }
            //insert or update title, content
            if($import_action == 'insert') {
                if ( FALSE === get_post_status( $course_id ) ) {
                    // The post does not exist, ok to insert
                    $question_args['post_type'] = 'flms-courses';
                    $course_id = wp_insert_post( $question_args );
                    if( is_wp_error( $course_id ) ) {
                        $errors[] = 'Error inserting course "'.$title.'". Error: '.$course_id->get_error_message();
                        $continue = false;
                    } 
                } else {
                    $errors[] = 'Skipped inserting course "'.$title.'". Course ID exists, remove ID to insert.';
                    $continue = false;
                }
            } else {
                //update
                $question_args['ID'] = absint($course_id);
                $updated = wp_update_post( $question_args );
                if( is_wp_error( $updated ) ) {
                    $errors[] = "Skipped importing course '.$course_id.'. Error: ".$updated->get_error_message();
                    $continue = false;
                }
            }
            if($continue) {
                if($field_indexes['Version'] == -2) {
                    $errors[] = "Skipped importing course '.$course_id.'. Version is required.";
                } else {
                    $version = $data[$field_indexes['Version']];
                    $versioned_content = get_post_meta($course_id,'flms_version_content',true);
                    if(!is_array($versioned_content)) {
                        $versioned_content = array();
                    }
                    if(!isset($versioned_content["$version"])) {
                        $versioned_content["$version"] = array();
                    }
                    if($field_indexes['Version Name'] != -2) {
                        $versioned_content["$version"]['version_name'] = $data[$field_indexes['Version Name']];
                    } else {
                        if(!isset($versioned_content["$version"]['version_name'])) {
                            $versioned_content["$version"]['version_name'] = "Version $version";
                        }
                    }
                    if($field_indexes['Version Permalink'] != -2) {
                        $versioned_content["$version"]['version_permalink'] = $data[$field_indexes['Version Permalink']];
                    } else {
                        if(!isset($versioned_content["$version"]['version_permalink'])) {
                            $versioned_content["$version"]['version_permalink'] = "version-$version";
                        }
                    }
                    if($field_indexes['Version Status'] != -2) {
                        $versioned_content["$version"]['version_status'] = $data[$field_indexes['Version Status']];
                    } else {
                        if(!isset($versioned_content["$version"]['version_status'])) {
                            $versioned_content["$version"]['version_status'] = 'draft';
                        }
                    }
                    
                    //post content
                    if($field_indexes['Post Content'] != -2) {
                        $versioned_content["$version"]['post_content'] = $data[$field_indexes['Post Content']];
                    } else {
                        if(!isset($versioned_content["$version"]['post_content'])) {
                            $versioned_content["$version"]['post_content'] = '';
                        }
                    }

                    if($field_indexes['Course Preview'] != -2) {
                        $versioned_content["$version"]['course_preview'] = $data[$field_indexes['Course Preview']];
                    } else {
                        if(!isset($versioned_content["$version"]['course_preview'])) {
                            $versioned_content["$version"]['course_preview'] = '';
                        }
                    }

                    //course access
                    if($field_indexes['Course Access'] != -2) {
                        $versioned_content["$version"]['course_settings']['course_access'] = $data[$field_indexes['Course Access']];
                    } else {
                        if(!isset($versioned_content["$version"]['course_settings']['course_access'])) {
                            $versioned_content["$version"]['course_settings']['course_access'] = 'open';
                        }
                    }
                    if($field_indexes['Course Progression'] != -2) {
                        $versioned_content["$version"]['course_settings']['course_progression'] = $data[$field_indexes['Course Progression']];
                    } else {
                        if(!isset($versioned_content["$version"]['course_settings']['course_progression'])) {
                            $versioned_content["$version"]['course_settings']['course_progression'] = 'linear';
                        }
                    }

                    if(flms_is_module_active('course_numbers')) {
                        if($field_indexes['Course Number'] != -2) {
                            $versioned_content["$version"]['course_numbers']['global'] = $data[$field_indexes['Course Number']];
                        }
                    }

                    if(flms_is_module_active('course_credits')) {
                        $course_credits = new FLMS_Module_Course_Credits();
                        $credit_fields = $course_credits->get_course_credit_fields();
                        foreach($credit_fields as $credit_field) {
                            $label = $course_credits->get_credit_label($credit_field);
                            if($field_indexes["$label Credits"] != -2) {
                                $versioned_content["$version"]['course_credits']["$credit_field"] = absint($data[$field_indexes["$label Credits"]]);
                            }
                            if(flms_is_module_active('course_numbers')) {
                                if($field_indexes["$label Course Number"] != -2) {
                                    $versioned_content["$version"]['course_numbers'][$credit_field] = sanitize_text_field($data[$field_indexes["$label Course Number"]]);
                                }
                            }
                        }
                    }

                    if(flms_is_module_active('course_materials')) {
                        if($field_indexes["Course Materials"] != -2) {
                            $materials = explode('|',$data[$field_indexes["Course Materials"]]);
                            $updated_materials = array();
                            if(!empty($materials)) {
                                $file_index = 0;
                                foreach($materials as $material) {
                                    if($material != '') {
                                        $material_data = preg_split("/[{}]+/", $material, -1, PREG_SPLIT_NO_EMPTY);
                                        if(isset($material_data[0])) {
                                            if($material_data[0] != '') {
                                                //check if we need to upload a new file
                                                $import_course_materials = apply_filters('flms_import_course_materials', true);
                                                if($import_course_materials) {
                                                    $file_path = flms_maybe_insert_course_material($material_data[0]);
                                                } else {
                                                    $file_path = $material_data[0];
                                                }
                                                if(isset($material_data[1])) {
                                                    $status = $material_data[1];
                                                } else {
                                                    $status = 'any';
                                                }
                                                if(isset($material_data[2])) {
                                                    $file_title = $material_data[2];
                                                } else {
                                                    $file_title = basename($file_path);
                                                }
                                                $updated_materials[] = array(
                                                    'index' => $file_index,
                                                    'title' => $file_title,
                                                    'status' => $status,
                                                    'file' => $file_path
                                                );
                                                $file_index++;
                                            }
                                        }
                                        else {
                                            $errors[] = "Invalid material url for course $course_id.";
                                        }
                                    } 
                                }
                            }
                            $versioned_content["$version"]['course_materials'] = $updated_materials;
                        }
                    }

                    
                    update_post_meta($course_id,'flms_version_content',$versioned_content);

                    if(flms_is_module_active('course_taxonomies')) {
                        $course_taxonomies = new FLMS_Module_Course_Taxonomies();
                        $tax_fields = $course_taxonomies->get_course_taxonomies_fields(true);
                        foreach($tax_fields as $tax_field) {
                            $slug = $tax_field['key'];
                            $label = $tax_field['label'];
                            if($field_indexes[$label] != -2) {
                                $values = explode('|',$data[$field_indexes[$label]]);
                                $insert_values = array();
                                if(!empty($values)) {
                                    foreach($values as $value) {
                                        if($value != '') {
                                            $insert_values[] = flms_get_taxonomy_id($value, $slug);
                                        }
                                    }
                                }
                                wp_set_post_terms($course_id, $insert_values, $slug);
                            }
                        }
                    }

                    if(flms_is_module_active('woocommerce')) {
                        $existing =  get_post_meta(absint($course_id),'flms_course_product_options', true);
                        if(!is_array($existing)) {
                            $existing = array();
                        }
                        if($field_indexes["Product Type"] != -2) {
                            $product_type = $data[$field_indexes["Product Type"]];
                            $existing['product_type'] = $product_type;
                        }
                        if($field_indexes["Attributes"] != -2) {
                            if(!isset($existing['product_type'])) {
                                $errors[] = "Cannot import attributes without a product type.";
                            } else {
                                $atts = explode('|',$data[$field_indexes["Attributes"]]);
                                if(!empty($atts)) {
                                    $new_atts = array();
                                    foreach($atts as $att) {
                                        $att_data = explode(':',$att);
                                        if(isset($att_data[1])) {
                                            $att_ids = explode('/',$att_data[1]);
                                            $new_atts[$att_data[0]] = $att_ids;
                                        }
                                    }
                                    $existing['variation_attributes'] = $new_atts;
                                }
                            }
                            
                            
                        }
                        if($field_indexes["Price(s)"] != -2) {
                            if(!isset($existing['product_type'])) {
                                $errors[] = "Cannot import prices without a product type.";
                            } else {
                                $prices = explode('|',$data[$field_indexes["Price(s)"]]);
                                if($existing['product_type'] == 'simple') {
                                    $regular = $prices[0];
                                    $sale = '';
                                    if(isset($prices[1])) {
                                        $sale = $prices[1];
                                    }
                                    $existing['simple_prices'] = array(
                                        'regular_price' => $regular,
                                        'sale_price' => $sale
                                    );
                                    $existing['variation_prices'] = array();
                                } else if($existing['product_type'] == 'variable') {
                                    if(!empty($prices)) {
                                        $new_prices = array();
                                        foreach($prices as $price) {
                                            $price_data = explode(':',$price);
                                            if(isset($price_data[1])) {
                                                $price_options = explode('/',$price_data[1]);
                                                $regular = $price_options[0];
                                                $sale = '';
                                                if(isset($price_options[1])) {
                                                    $sale = $price_options[1];
                                                }
                                                $new_prices[$price_data[0]] = array(
                                                    'regular_price' => $regular,
                                                    'sale_price' => $sale
                                                );
                                            }
                                        }
                                        $existing['variation_prices'] = $new_prices;
                                        $existing['simple_prices'] = array(
                                            'regular_price' => 0,
                                            'sale_price' => ''
                                        );
                                    }
                                }
                                
                            }
                            
                            
                        }
                        update_post_meta(absint($course_id),'flms_course_product_options', $existing);
                        $woo = new FLMS_Module_Woocommerce();
                        $woo->create_update_course_product($course_id);
                            
                    }

                    if($field_indexes['Lessons'] != -2) {
                        $lessons_inputs = explode('|',$data[$field_indexes['Lessons']]);
                        $lessons = array();
                        $sample_lessons = array();
                        if(!empty($lessons_inputs)) {
                            foreach($lessons_inputs as $lesson) {
                                if($lesson != '') {
                                    $lesson_id = flms_get_cpt_id($lesson, 'flms-lessons');
                                    if($lesson_id !== false) {
                                        $lessons[] = $lesson_id;
                                        //samples
                                        $lesson_meta = "$course_id:$version";
                                        $sample = get_post_meta($lesson_id,'flms_is_sample_lesson',$lesson_meta);
                                        if($sample != '') {
                                            $sample_lessons[] = $lesson_id;
                                        }
                                    } else {
                                        $errors[] = "$lesson does not exist.";
                                    }
                                }
                            }
                        }
                        $flms_active_version = $version;
                        $course_manager->update_course_lessons($course_id, $lessons, true, array(), $version);
                        $flms_active_version = $version;
                        $course_manager->update_course_sample_lessons($course_id, $sample_lessons, $version);
                    }

                    if($field_indexes['Exams'] != -2) {
                        $exam_inputs = explode('|',$data[$field_indexes['Exams']]);
                        $exams = array();
                        if(!empty($exam_inputs)) {
                            foreach($exam_inputs as $exam) {
                                if($exam != '') {
                                    $exam_id = flms_get_cpt_id($exam, 'flms-exams');
                                    if($exam_id !== false) {
                                        $exams[] = $exam_id;
                                    } else {
                                        $errors[] = "$exam does not exist.";
                                    }
                                }
                            }
                        }
                        $flms_active_version = $version;
                        $course_manager->update_exam_associations($course_id, $exams, true, array(), $version);
                    }

                    //update course steps
                    $course = new FLMS_Course($course_id);
                    $flms_active_version = $version;
                    $course->update_course_steps();

                }
            }

            if(($i + 1) == $max_rows) {
                $cron_data = array(
                    'file' => $file,
                    'import_action' => $import_action,
                    'processed_rows' => $i,
                    'field_indexes' => $field_indexes,
                    'errors' => $errors,
                    'user_id' => $user_id
                );
                wp_schedule_single_event( time() + 30, 'flms_import_courses', $cron_data );
                $max_rows_reached = true;
            }

            if(($i + 1) == $course_data_count) {
                //clear cron
                wp_clear_scheduled_hook('flms_import_courses');
                //notify user
                $this->import_complete_notification($user_id, 'flms-courses', $errors);
                set_transient( 'flms_import_complete', 'is_complete' );
                //delete the attachment, we're done
                wp_delete_attachment(absint($file), true);
                
            } 
        }
        
    }

    public function import_exams($file, $import_action, $processed_rows = 0, $field_indexes = array(), $errors = array(), $user_id = 0) {
        $course_manager = new FLMS_Course_Manager();
        $course_data = array();
        if (($handle = fopen($file, 'r')) !== false) {
            $rows = 0;
            $deliminator = flms_detect_csv_elimiter($file);
            while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
                if(++$rows > 1) { //skip headers
                    $course_data[] = $data;
                }
            }
            fclose($handle);
        }
        $default_max_records = apply_filters('flms_import_record_iterator_count', 75);
        $max_records_per_iteration = apply_filters('flms_exams_per_import', $default_max_records);
        $max_rows = $processed_rows + $max_records_per_iteration;
        $course_data_count = count($course_data);
        $max_rows_reached = false;
        
        for($i = $processed_rows; $i < $course_data_count && $i < $max_rows; $i++) {
            $data = $course_data[$i];
            if($field_indexes['Version'] != -2) {
                if($field_indexes['Associated Content'] != -2) {
                    $associated_content = $data[$field_indexes['Associated Content']];
                    if($associated_content == '') {
                        $errors[] = "Skipped row $i. No associated content specified.";
                    } else {
                        $associated_content_post_type = $data[$field_indexes['Associated Content Post Type']];
                        if($associated_content_post_type == '') {
                            $errors[] = 'No associated content post type specified.';
                        } else {
                            $version = $data[$field_indexes['Version']];
                            $posts = get_posts(array('post_type' => $associated_content_post_type, 'title' => $associated_content));
                            if(!empty($posts)) {
                                foreach($posts as $flms_post) {
                                    $continue = true;
                                    $versioned_content = get_post_meta($flms_post->ID,'flms_version_content',true);
                                    if(!isset($versioned_content["$version"])) {
                                        $errors[] = "Version $version does not exists for $associated_content.";
                                    } else {
                                        $question_args = array();
                                        if($field_indexes['Title'] != -2) {
                                            $question_args['post_title'] = $data[$field_indexes['Title']];
                                        }
                                        if($field_indexes['Post Content'] != -2) {
                                            $question_args['post_content'] = $data[$field_indexes['Post Content']];
                                        }
                                        if($field_indexes['Status'] != -2) {
                                            $question_args['post_status'] = $data[$field_indexes['Status']];
                                        }
                                        //insert or update title, content
                                        $exam_id = $data[$field_indexes['ID']];
                                        if($import_action == 'insert') {
                                            if ( FALSE === get_post_status( $exam_id ) ) {
                                                // The post does not exist, ok to insert
                                                $question_args['post_type'] = 'flms-exams';
                                                $exam_id = wp_insert_post( $question_args );
                                                if( is_wp_error( $exam_id ) ) {
                                                    $errors[] = 'Error importing exam "'.$title.'". Error: '.$exam_id->get_error_message();
                                                    $continue = false;
                                                } 
                                            } else {
                                                $errors[] = "Skipped inserting exam $exam_id. Exam ID exists, remove ID to insert.";
                                                $continue = false;
                                            }
                                        } else {
                                            //update
                                            $question_args['ID'] = absint($exam_id);
                                            $updated = wp_update_post( $question_args );
                                            if( is_wp_error( $updated ) ) {
                                                $errors[] = "Skipped importing exam '.$exam_id.'. Error: ".$updated->get_error_message();
                                                $continue = false;
                                            }
                                        }

                                        if($continue) {
                                            $exam_versioned_content = get_post_meta($exam_id,'flms_version_content',true);
                                            if(!is_array($exam_versioned_content)) {
                                                $exam_versioned_content = array();
                                            }
                                            if($field_indexes['Post Content'] != -2) {
                                                $exam_versioned_content["$version"]['post_content'] = $data[$field_indexes['Post Content']];
                                                update_post_meta($exam_id,'flms_version_content', $exam_versioned_content);
                                            }
                                            $course_manager->update_exam_associations($flms_post->ID, array($exam_id), false, array(), $version); 
                                            if($import_action == 'insert') {
                                                $settings = $course_manager->get_default_exam_settings();
                                            } else {
                                                $settings = get_post_meta($exam_id, "flms_exam_settings_$version", true);
                                                if(!is_array($settings)) {
                                                    $settings = $course_manager->get_default_exam_settings();
                                                }
                                            }
                                            if($field_indexes['Exam Type'] != -2) {
                                                $exam_type = sanitize_text_field($data[$field_indexes['Exam Type']]);
                                                $settings['exam_type'] = $exam_type;
                                                /*if($exam_type == 'standard') {
                                                    
                                                }*/
                                            }
                                            if($field_indexes['Question Select Type'] != -2) {
                                                $question_select_type = sanitize_text_field($data[$field_indexes['Question Select Type']]);
                                                $settings['question_select_type'] = $question_select_type;	
                                            }
                                            if ($field_indexes['Exam Questions'] != -2) {
                                                $questions = array();
                                                // Sanitize and save the selected lesson IDs as a custom field
                                                $questions_input = explode('|',$data[$field_indexes['Exam Questions']]);
                                                if(!empty($questions_input)) {
                                                    foreach($questions_input as $question) {
                                                        //get id, could be id or a title
                                                        $question_id = flms_get_post_id($question, 'flms-questions');
                                                        if($question_id !== false) {
                                                            $questions[] = $question_id;
                                                        } else {
                                                            $errors[] = 'Could not find '.$question;
                                                        }
                                                    }
                                                }
                                                $settings['exam_questions'] = $questions;
                                            } 
                                            if($field_indexes['Exam Question Categories'] != -2) {
                                                $categories = array();
                                                if($data[$field_indexes['Exam Question Categories']] != '') {
                                                    $categories_inputs = explode('|',$data[$field_indexes['Exam Question Categories']]);
                                                    if(!empty($categories_inputs)) {
                                                        foreach($categories_inputs as $category) {
                                                            $cat_id = flms_get_question_category_id($category);
                                                            if($cat_id != false) {
                                                                $categories[] = $cat_id;
                                                            }
                                                        }
                                                    }
                                                }
                                                $settings['exam_question_categories'] = $categories;
                                            } 

                                            if($field_indexes['Exam Attempts'] != -2) {
                                                $settings['exam_attempts'] = (int) $data[$field_indexes['Exam Attempts']];
                                            }
                                            if($field_indexes['Question Per Page'] != -2) {
                                                $settings['questions_per_page'] = (int) $data[$field_indexes['Question Per Page']];
                                            }
                                            if($field_indexes['Save/Continue Enabled'] != -2) {
                                                $settings['save_continue_enabled'] = (int) $data[$field_indexes['Save/Continue Enabled']];
                                            }
                                            if($field_indexes['Exam Review Enabled'] != -2) {
                                                $settings['exam_review_enabled'] = (int) $data[$field_indexes['Exam Review Enabled']];
                                            }
                                            if($field_indexes['Exam is Graded'] != -2) {
                                                $settings['exam_is_graded'] = sanitize_text_field($data[$field_indexes['Exam is Graded']]);
                                            }
                                            if($field_indexes['Exam is Graded Using'] != -2) {
                                                $settings['exam_is_graded_using'] = sanitize_text_field($data[$field_indexes['Exam is Graded Using']]);
                                            }
                                            if($field_indexes['Pass Percentage'] != -2) {
                                                $settings['pass_percentage'] = (int) $data[$field_indexes['Pass Percentage']];
                                            }
                                            if($field_indexes['Pass Points'] != -2) {
                                                $settings['pass_points'] = (int) $data[$field_indexes['Pass Points']];
                                            }
                                            if($field_indexes['Exam Attempts Action'] != -2) {
                                                $settings['exam_attempt_action'] = sanitize_text_field($data[$field_indexes['Exam Attempts Action']]);
                                            }
                                            if($field_indexes['Exam Label'] != -2) {
                                                $settings['exam_label_override'] = sanitize_text_field($data[$field_indexes['Exam Label']]);
                                            }
                                            if($field_indexes['Start Exam Label'] != -2) {
                                                $settings['exam_start_label'] = sanitize_text_field($data[$field_indexes['Start Exam Label']]);
                                            }
                                            if($field_indexes['Questions to Draw'] != -2) {
                                                $settings['sample-draw-question-count'] = absint($data[$field_indexes['Questions to Draw']]);
                                            }
                                            if($field_indexes['Questions Order'] != -2) {
                                                $settings['question_order'] = sanitize_text_field($data[$field_indexes['Questions Order']]);
                                            }
                                            if($field_indexes['Cumulative Exam Settings'] != -2) {
                                                $options = explode('|',$data[$field_indexes['Cumulative Exam Settings']]);
                                                $value = array();
                                                if(is_array($options)) {
                                                    if(!empty($options)) {
                                                        $success = true;
                                                        foreach($options as $option) {
                                                            $option_data = explode(':', $option);
                                                            if(isset($option_data[0]) && isset($option_data[1])) {
                                                                if(is_numeric($option_data[0]) && is_numeric($option_data[1])) {
                                                                    $value[$option_data[0]] = $option_data[1];
                                                                } else {
                                                                    $success = false;
                                                                }
                                                            } else {
                                                                $success = false;
                                                            }
                                                        }
                                                        if(!$success) {
                                                            $errors[] = 'Error updating cumulative exam settings for exam "'.$exam_id.'". Data is improperly formatted.';
                                                        }
                                                    }
                                                }
                                                $settings['cumulative_exam_questions'] = $value;
                                            }
                                        
                                            $update = update_post_meta($exam_id, "flms_exam_settings_$version", $settings);
                                            if($update === false) {
                                                $errors[] = "Error updating $exam_id settings.";		
                                            }
                                        }
                                    }
                                    
                                }
                            } else {
                                $errors[] = 'Could not update exam "'.$data[$field_indexes['Title']].'", associated content "'.$associated_content.'" not found.';
                            }
                        }
                    }
                } else {
                    $errors[] = 'Skipped importing exam, no version specified.';
                }
                
            }

            if(($i + 1) == $max_rows) {
                $cron_data = array(
                    'file' => $file,
                    'import_action' => $import_action,
                    'processed_rows' => $i,
                    'field_indexes' => $field_indexes,
                    'errors' => $errors,
                    'user_id' => $user_id
                );
                wp_schedule_single_event( time() + 30, 'flms_import_exams', $cron_data );
                $max_rows_reached = true;
            }

            if(($i + 1) == $course_data_count) {
                //clear cron
                wp_clear_scheduled_hook('flms_import_exams');
                //notify user
                $this->import_complete_notification($user_id, 'flms-exams', $errors);
                set_transient( 'flms_import_complete', 'is_complete' );
                //delete the attachment, we're done
                wp_delete_attachment(absint($file), true);
                
            } 
        }
        
    }

    public function import_questions($file, $import_action, $processed_rows = 0, $field_indexes = array(), $errors = array(), $user_id = 0) {
        $course_manager = new FLMS_Course_Manager();
        $course_data = array();
        if (($handle = fopen($file, 'r')) !== false) {
            $rows = 0;
            $deliminator = flms_detect_csv_elimiter($file);
            while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
                if(++$rows > 1) { //skip headers
                    $course_data[] = $data;
                }
            }
            fclose($handle);
        }
        $default_max_records = apply_filters('flms_import_record_iterator_count', 75);
        $max_records_per_iteration = apply_filters('flms_questions_per_import', $default_max_records);
        $max_rows = $processed_rows + $max_records_per_iteration;
        $course_data_count = count($course_data);
        $max_rows_reached = false;
        
        for($i = $processed_rows; $i < $course_data_count && $i < $max_rows; $i++) {
            $data = $course_data[$i];
            $question_id = $data[$field_indexes['ID']];
							
            $continue = true;

            $question_args = array();
            if($field_indexes['Title'] != -2) {
                $question_args['post_title'] = $data[$field_indexes['Title']];
            }
            if($field_indexes['Post Content'] != -2) {
                $question_args['post_content'] = $data[$field_indexes['Post Content']];
            }
            if($field_indexes['Status'] != -2) {
                $question_args['post_status'] = $data[$field_indexes['Status']];
            }


            //insert or update title, content
            if($import_action == 'insert') {
                if ( FALSE === get_post_status( $question_id ) ) {
                    // The post does not exist, ok to insert
                    $question_args['post_type'] = 'flms-questions';
                    $question_id = wp_insert_post( $question_args );
                    if( is_wp_error( $question_id ) ) {
                        $errors[] = 'Error importing question "'.$title.'". Error: '.$question_id->get_error_message();
                        $continue = false;
                    } 
                } else {
                    $errors[] = "Skipped inserting question $question_id. Question ID exists, remove ID to insert.";
                    $continue = false;
                }
            } else {
                //update
                $question_args['ID'] = absint($question_id);
                $updated = wp_update_post( $question_args );
                if( is_wp_error( $updated ) ) {
                    $errors[] = "Skipped importing question '.$question_id.'. Error: ".$updated->get_error_message();
                    $continue = false;
                }
            }
            
            if($continue) {
                if($field_indexes['Category'] != -2) {
                    $categories = explode('|',$data[$field_indexes['Category']]);
                    //update categoris
                    $taxonomy = 'flms-question-categories';
                    $term_ids = array();
                    if(!empty($categories)) {
                        foreach($categories as $category) {
                            $cat  = get_term_by('name', $category, $taxonomy);
                            if($cat === false){
                                //cateogry not exist create it 
                                $cat = wp_insert_term($category, $taxonomy);
                                if(!is_wp_error( $cat )) {
                                    $cat_id = $cat['term_id'];
                                } else {
                                    $errors[] = "Could not create category $category.";
                                }
                            } else{ 
                                //category already exists, get ID
                                $cat_id = $cat->term_id ;
                            }
                            $term_ids[] = $cat_id;
                        }
                    }
                    wp_set_post_terms($question_id,$term_ids,$taxonomy);
                }

                if($field_indexes['Type'] != -2) {
                    //update quesiton type
                    $question_type = $data[$field_indexes['Type']];
                    update_post_meta($question_id,'flms_question_type', $question_type );
                } else {
                    $question_type = get_post_meta($question_id,'flms_question_type', true );
                }

                if($field_indexes['Answer'] != -2 && $field_indexes['Options'] != -2) {
                    $question_answers = $data[$field_indexes['Answer']];
                    $question_options = $data[$field_indexes['Options']];
                    //Answer / Options
                    switch($question_type) {
                        case 'single-choice':
                        case 'multiple-choice':
                            $question_meta = array();
                            $options = explode('|',$question_options);
                            $answers = explode('|',$question_answers);
                            foreach($options as $option) {
                                if(in_array($option, $answers)) {
                                    $correct = 1;
                                } else {
                                    $correct = 0;
                                }
                                $question_meta[] = array(
                                    'answer' => $option,
                                    'correct' => $correct
                                );
                            }
                            update_post_meta($question_id,'flms_question_answer', $question_meta );
                            break;
                        case 'free-choice':
            
                            break;
                        case 'fill-in-the-blank':
                            $before = '';
                            if (preg_match('/^(.*?)[{]/', $question_options, $matches) == 1) {
                                if($matches[1] != '') {
                                    $before = $matches[1];
                                }
                            }
                            $after = '';
                            if (preg_match('/[}](.*)/', $question_options, $matches) == 1) {
                                if($matches[1] != '') {
                                    $after = $matches[1];
                                }
                            }
                            $answers = '';
                            if (preg_match('/[{](.*?)[}]/', $question_options, $matches) == 1) {
                                $answers = $matches[1];
                                preg_match_all('/\[(.*?)\]/', $answers, $matches);
                                if(isset($matches[1])) {
                                    if(!empty($matches[1])) {
                                        $answers = $matches[1];
                                    }
                                }
                            }
                            $answer_content = array(
                                'text' => $question_options,
                                'before' => $before,
                                'after' => $after,
                                'correct' => $answers
                            );
                            
                            update_post_meta($question_id,'flms_question_answer', $answer_content );
                            break;
                        case 'assessment':
                            update_post_meta($question_id,'flms_question_answer', $question_options );
                            break;
                        case 'essay':
                            break;
                    }
                } else if(($field_indexes['Answer'] != -2 && $field_indexes['Options'] == -2) || ($field_indexes['Answer'] == -2 && $field_indexes['Options'] != -2)) {
                    $errors[] = "Skipped importing question '.$question_id.' answers and options, both fields required for import.";
                }
            }

            if(($i + 1) == $max_rows) {
                $cron_data = array(
                    'file' => $file,
                    'import_action' => $import_action,
                    'processed_rows' => $i,
                    'field_indexes' => $field_indexes,
                    'errors' => $errors,
                    'user_id' => $user_id
                );
                wp_schedule_single_event( time() + 30, 'flms_import_questions', $cron_data );
                $max_rows_reached = true;
            }

            if(($i + 1) == $course_data_count) {
                //clear cron
                wp_clear_scheduled_hook('flms_import_questions');
                //notify user
                $this->import_complete_notification($user_id, 'flms-questions', $errors);
                set_transient( 'flms_import_complete', 'is_complete' );
                //delete the attachment, we're done
                wp_delete_attachment(absint($file), true);
                
            } 
        }
        
    }

    public function import_user_data($file, $import_action, $processed_rows = 0, $field_indexes = array(), $errors = array(), $user_id = 0) {
        $course_manager = new FLMS_Course_Manager();
        $user_data = array();
        if (($handle = fopen($file, 'r')) !== false) {
            $rows = 0;
            $deliminator = flms_detect_csv_elimiter($file);
            while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
                if(++$rows > 1) { //skip headers
                    $user_data[] = $data;
                }
            }
            fclose($handle);
        }
        $default_max_records = apply_filters('flms_import_record_iterator_count', 75);
        $max_records_per_iteration = apply_filters('flms_users_per_import', $default_max_records);
        $max_rows = $processed_rows + $max_records_per_iteration;
        $user_data_count = count($user_data);
        $max_rows_reached = false;
        
        for($i = $processed_rows; $i < $user_data_count && $i < $max_rows; $i++) {
            $data = $user_data[$i];
            $process_user_id = 0;
            if($field_indexes['ID'] != -2) {
                $process_user_id = $data[$field_indexes['ID']];
            }
							
            $continue = true;

            $user_args = array();
            if($field_indexes['First Name'] != -2) {
                $user_args['first_name'] = $data[$field_indexes['First Name']];
            }
            if($field_indexes['Last Name'] != -2) {
                $user_args['last_name'] = $data[$field_indexes['Last Name']];
            }
            if($field_indexes['Display Name'] != -2) {
                $user_args['display_name'] = $data[$field_indexes['Display Name']];
            }


            //insert or update title, content
            if($import_action == 'insert') {
                if ( FALSE === get_user_by('id', $process_user_id ) || $process_user_id == 0) {
                    if($field_indexes['Email'] != -2) {
                        $user_args['user_email'] = $data[$field_indexes['Email']];
                        // The  does not exist, ok to insert
                        $user_args['user_pass'] = wp_generate_password();
                        if($field_indexes['Username'] != -2) {
                            $user_args['user_login'] = $data[$field_indexes['Username']];
                        } else {
                            $user_args['user_login'] = $data[$field_indexes['Email']];
                        }
                        
                        $process_user_id = wp_insert_user( $user_args );
                        if( is_wp_error( $process_user_id ) ) {
                            $errors[] = 'Error importing question "'.$title.'". Error: '.$question_id->get_error_message();
                            $continue = false;
                        } 
                    } else {
                        $errors[] = "Skipped inserting user, $process_user_id. Email is required.";
                        $continue = false;
                    }
                } else {
                    $errors[] = "Cannot insert user $process_user_id. User ID exists, remove ID to insert.";
                    $continue = false;
                }
            } else {
                //update
                if($process_user_id > 0) {
                    $user_args['ID'] = absint($process_user_id);
                    $updated = wp_update_user( $user_args );
                    if( is_wp_error( $updated ) ) {
                        $errors[] = "Skipped importing question '.$process_user_id.'. Error: ".$updated->get_error_message();
                        $continue = false;
                    }
                } else {
                    $errors[] = "Skipped importing user, user ID is required to update";
                    $continue = false;
                }
            }
            
            if($continue) {
                //profile data
                if(flms_is_module_active('course_credits')) {
                    $course_credits = new FLMS_Module_Course_Credits();
                    $credit_fields = $course_credits->get_course_credit_fields();
                    foreach($credit_fields as $credit_field) {
                        $label = $course_credits->get_credit_label($credit_field);
                        //$credit_name = preg_replace('/[^\w-]/', '', trim(html_entity_decode($label)));
                        $credit_name = strip_tags(flms_get_label($credit_field));
                        if($field_indexes[$credit_name] != -2) {
                            $options = $data[$field_indexes[$credit_name]];
                            if($options == '') {
                                delete_user_meta( $process_user_id, "flms_has-license-$credit_field");    
                                delete_user_meta( $process_user_id, "flms_license-$credit_field");    
                            } else {
                                $credit_values = explode(':',$options);
                                if(isset($credit_values[0])) {
                                    if($credit_values[0] == 'active') {
                                        update_user_meta( $process_user_id, "flms_has-license-$credit_field", 'on');    
                                    } else {
                                        delete_user_meta( $process_user_id, "flms_has-license-$credit_field"); 
                                    }
                                }
                                if(isset($credit_values[1])) {
                                    if($credit_values[1] != '') {
                                        update_user_meta( $process_user_id, "flms_license-$credit_field", $credit_values[1]);    
                                    } else {
                                        delete_user_meta( $process_user_id, "flms_license-$credit_field"); 
                                    }
                                }
                            }
                        }
                    }
                }
                if(flms_is_module_active('woocommerce')) {
                    $billing_address_fields = WC()->countries->get_address_fields('','billing_');
                    foreach($billing_address_fields as $index => $field) {
                        if($index == 'billing_address_2') {
                            $label = 'Address line 2';
                        } else {
                            $label = $field['label'];
                        }
                        if($field_indexes["Billing $label"] != -2) {
                            update_user_meta($process_user_id, $index, $data[$field_indexes["Billing $label"]]);
                        }
                    }
                    $shipping_address_fields = WC()->countries->get_address_fields('','shipping_');
                    foreach($shipping_address_fields as $index => $field) {
                        if($index == 'shipping_address_2') {
                            $label = 'Address line 2';
                        } else {
                            $label = $field['label'];
                        }
                        if($field_indexes["Shipping $label"] != -2) {
                            update_user_meta($process_user_id, $index, $data[$field_indexes["Shipping $label"]]);
                        }
                    }
                }
                
                //active and completed courses
                $course_progress = new FLMS_Course_Progress();
                $process_active_courses = false;
                $new_active_courses = array();
                if($field_indexes['Active Courses'] != -2) {
                    $process_active_courses = true;
                    $new_active_courses = explode('|',$data[$field_indexes['Active Courses']]);
                    $new_active_courses = array_filter($new_active_courses);
                }
                $process_completed_courses = false;
                $new_completed_courses = array();
                if($field_indexes['Completed Courses'] != -2) {
                    $process_completed_courses = true;
                    $new_completed_courses = explode('|',$data[$field_indexes['Completed Courses']]);
                    $new_completed_courses = array_filter($new_completed_courses);
                }

                $active_courses = flms_get_user_active_courses($process_user_id);
                $active_course_array = array();
                foreach($active_courses as $active_course) {
                    $course_id = $active_course['course_id'];
                    $course_version = $active_course['course_version'];
                    $course_identifier = "$course_id:$course_version";
                    $active_course_array[] = $course_identifier;
                }

                $completed_courses = flms_get_user_completed_courses($process_user_id);
                $completed_course_array = array();
                foreach($completed_courses as $completed_course) {
                    $course_id = $completed_course['course_id'];
                    $course_version = $completed_course['course_version'];
                    $course_identifier = "$course_id:$course_version";
                    $completed_course_array[] = $course_identifier;
                }
                
                if(empty($new_active_courses) && $process_active_courses) {
                    //unenroll them from everything
                    if(!empty($active_courses)) {
                        foreach($active_courses as $active_course) {
                            $course_id = $active_course['course_id'];
                            $course_version = $active_course['course_version'];
                            if(!$process_completed_courses || ($process_completed_courses && !in_array("$course_id:$course_version", $new_completed_courses))) {
                                $course_progress->unenroll_user($process_user_id, $course_id, $course_version);
                            } 
                        }
                    }
                } 
                if(!empty($new_active_courses) || $process_active_courses) {
                    //unenroll any not listed
                    foreach($active_courses as $active_course) {
                        $course_id = $active_course['course_id'];
                        $course_version = $active_course['course_version'];
                        $course_identifier = "$course_id:$course_version";
                        if(!in_array($course_identifier, $new_active_courses)) {
                            if(!$process_completed_courses || ($process_completed_courses && !in_array($course_identifier, $new_completed_courses))) {
                                $course_progress->unenroll_user($process_user_id, $course_id, $course_version);
                            } 
                        }
                    }
                    //enroll those listed
                    foreach($new_active_courses as $course) {
                        if(!in_array($course, $active_course_array)) {
                            $course_data = explode(':',$course);
                            $course_id = $course_data[0];
                            $course_version = $course_data[1];
                            $status = $course_progress->get_user_course_status($process_user_id, $course_id, $course_version);
                            if($status == 'completed') {
                                $course_progress->reset_course_progress($process_user_id, $course_id, $course_version);
                            }
                            $course_progress->enroll_user($process_user_id, $course_id, $course_version);
                        }
                    }
                
                }

                if(empty($new_completed_courses) && $process_completed_courses) {
                    //see if previously completed courses should be reset into active courses and otherwise uncomplete the courses
                    if(!empty($completed_courses)) {
                        foreach($completed_courses as $completed_course) {
                            $course_id = $completed_course['course_id'];
                            $course_version = $completed_course['course_version'];
                            if($process_active_courses && in_array("$course_id:$course_version", $new_active_courses)) {
                                $course_progress->reset_user_completed_course($process_user_id, $course_id, $course_version);
                            }
                            if(!$process_active_courses || ($process_active_courses && !in_array("$course_id:$course_version", $new_active_courses))) {
                                $course_progress->remove_user_completed_course($process_user_id, $course_id, $course_version);
                            } 
                        }
                    }
                } 

                if(!empty($new_completed_courses) && $process_completed_courses) {
                    //unenroll any not listed
                    foreach($completed_courses as $completed_course) {
                        $course_id = $completed_course['course_id'];
                        $course_version = $completed_course['course_version'];
                        $course_identifier = "$course_id:$course_version";
                        if(!in_array($course_identifier, $new_completed_courses)) {
                            $course_progress->unenroll_user($process_user_id, $course_id, $course_version);
                            if($process_active_courses && in_array($course_identifier, $new_active_courses)) {
                                $course_progress->reset_user_completed_course($process_user_id, $course_id, $course_version);
                            } else {
                                $course_progress->remove_user_completed_course($process_user_id, $course_id, $course_version);
                            }
                        }
                    }
                    //complete those listed
                    foreach($new_completed_courses as $course) {
                        if(!in_array($course, $completed_course_array)) {
                            $course_data = explode(':',$course);
                            if(!empty($course_data)) {
                                $course_id = $course_data[0];
                                $course_version = $course_data[1];
                                $course_progress->complete_course($process_user_id, $course_id, $course_version);
                            }
                        }
                    }
                
                }


            }

            if(($i + 1) == $max_rows) {
                $cron_data = array(
                    'file' => $file,
                    'import_action' => $import_action,
                    'processed_rows' => $i,
                    'field_indexes' => $field_indexes,
                    'errors' => $errors,
                    'user_id' => $user_id
                );
                wp_schedule_single_event( time() + 30, 'flms_import_user_data', $cron_data );
                $max_rows_reached = true;
            }

            if(($i + 1) == $user_data_count) {
                //clear cron
                wp_clear_scheduled_hook('flms_import_user_data');
                //notify user
                $this->import_complete_notification($user_id, 'flms-users', $errors);
                set_transient( 'flms_import_complete', 'is_complete' );
                //delete the attachment, we're done
                wp_delete_attachment(absint($file), true);
                
            } 
        }
        
    }

    public function cron_notices() {
        if ( 'is_complete' == get_transient( 'flms_import_complete' ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( FLMS_PLUGIN_NAME .' import complete!', 'flms' ); ?></p>
            </div>
            <?php
            delete_transient( 'flms_import_complete' );
        }
    }

    public function import_complete_notification($user_id, $type, $errors = array()) {
        $to = '';
        if($user_id > 0) {
            $user = get_user_by( 'id', $user_id );
            if($user !== false) {
                $to = $user->user_email;
            }
        } else {
            $to = get_option('admin_email');
        }
        if($to != '') {
            if($type == 'flms-users') {
                $message = "Your user import is complete. ";
                $message .= '<a href="'.admin_url( 'users.php').'">Review users</a>.';
            } else {
                $type_label = strtolower(flms_get_post_type_label($type, true));
                $message = "Your $type_label import is complete. ";
                $message .= '<a href="'.admin_url( 'edit.php?post_type=flms-courses').'">Review courseware</a>.';
            }
            $message = apply_filters('flms_import_complete_notification_message', $message, $type);
            $show_errors = apply_filters('flms_import_notication_show_errors', true);
            if($show_errors && !empty($errors)) {
                $message .= '<p><strong>There were errors during the import:</strong></p>';
                $message .= '<p>'.implode('<br>',$errors).'</p>';
            }
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, FLMS_PLUGIN_NAME .' import complete!',$message, $headers);
        }
    
    }

    /**
     * Unused since we switched to wp_schedule_single_event, action registered in class-flms-setup.php
     */
    function flms_cron_times( $schedules ) {
        // Adds once weekly to the existing schedules.
        $schedules['2_minutes'] = array(
            'interval' => 120,
            'display' => __( 'Every Two Minutes' )
        );
        return $schedules;
    }
    
}