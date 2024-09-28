<?php
class FLMS_Exporter {
    public function __construct() {
        add_action('admin_menu', array($this,'register_reports_page'));
	}

    public function register_reports_page() {
        add_submenu_page(FLMS_PLUGIN_SLUG,'Export','Export','install_plugins', 'flms-exporter',array($this,'flms_export_page'),99);
        add_action('admin_enqueue_scripts', array($this,'flms_exporter_assets'));
    }

    public function flms_export_page() {
        ?><div class="wrap">
			
			<h1><?= __(FLMS_PLUGIN_NAME .' Exporter', 'flms') ?></h1>
            <div class="page-exporter">
                <div class="flms-tabs theme-color flex">
                    <div class="tab is-active" data-tab="#export">Export</div>    
                    <div class="tab" data-tab="#export-history">Export History</div>
                </div>
                <div class="flms-tab-content theme-color">
                    <div class="flms-tab-section is-active export-form" id="export">
                        
                        <form id="export-settings">
                            <div>
                                <label>Export Type</label>
                                <div class="flms-field select">
                                    <select name="import-type" id="export-type">
                                        <option value="-1">Select an export type</option>
                                        <option value="courses">Courses</option>
                                        <option value="lessons">Lessons</option>
                                        <option value="topics">Topics</option>
                                        <option value="exams">Exams</option>
                                        <option value="questions">Questions</option>
                                        <option value="user-data">User Data</option>
                                        <!--<option value="course-data">Course Data (Feature to come)</option>-->
                                        <option value="plugin-settings">Plugin Settings</option>
                                    </select>
                                </div>
                            </div>
                            <div id="appended-export-data"></div>
                            <div>
                                <input type="submit" class="button button-primary export_content" value="Export" />
                            </div>
                            <div class="response"></div>
                        </form>
                        <div class="progress-loading"><div class="progress"></div></div>
                    </div>
                    <div class="flms-tab-section export-list" id="export-history">
                        <?php echo $this->get_export_list(); ?>                        
                    </div>
                </div>
        </div><?php
    }

    public function get_export_list() {
        $export_dir = $this->get_export_dir();
        $export_url = $this->get_export_dir_url();
        $export_files = array_diff(scandir($export_dir), array('..', '.', '.DS_Store'));
        $export_array = array();
        if(!empty($export_files)) {
            $timezone_str = wp_timezone_string();
            $timezone  = new DateTimeZone($timezone_str);
            foreach($export_files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $file_name = explode('.'.$ext,$file);
                //echo $file;
                $file_info = explode('-',$file_name[0]);
                //remove the flms- prepended text in each file
                /*$prefixes = explode('-',strtolower(str_replace(' ','-',FLMS_PLUGIN_NAME)));
                foreach($prefixes as $prefix) {
                    array_splice($file_info, array_search($prefix, $file_info), 1);
                }*/
                $prefix = explode('-',strtolower(str_replace(' ','_',FLMS_PLUGIN_NAME)));
                array_splice($file_info, array_search($prefix, $file_info), 1);
                //print_r($file_info);
                $month = $file_info[2];
                $day = $file_info[3];
                $year = $file_info[4];
                $time = $file_info[5]; //Gis -> 0 through 23, 00 to 59, 00 through 59
                $hour = substr($time, 0, 2);
                $minutes = substr($time, 2, 2);
                $datestring = "$month/$day/$year $hour:$minutes";
                $sortstring = $year.$month.$day.$hour.$minutes;
                $date = date('M j, Y, g:ia', strtotime($datestring));
                /*$stat = stat($export_dir.'/'.$file);
                $date = date('M j, Y, g:ia', $stat['ctime']);
                $sortstring = date('Ymdgia', $stat['ctime']);*/

                // Determine the MIME type of the uploaded file
                switch ($ext) {
                    case 'csv':
                        //print_r($file_info);
                        $type = $file_info[0];
                        $name = ucwords($type);
                    break;
                    case 'txt':
                        //Plugin settings
                        $type = 'plugin-settings';
                        $name = 'Plugin Settings';
                        break;
                }
                //$export_array[$type][] = array(
                $export_array[] = array(
                    'name' => $name,
                    'file' => $file,
                    'date' => $date,
                    'sort_date' => $sortstring,
                );
            }
        } 
        $list = '';
        if(!empty($export_array)) {
            //$export_array = $this->sort_custom($export_array);
            $key_values = array_column($export_array, 'sort_date'); 
            array_multisort($key_values, SORT_DESC, $export_array);
            $list .= '<button id="delete-all-exports" class="button button-secondary">Delete All Exports</button>';
            $list .= '<div class="headings"><div>Export Type</div><div>Date</div><div>Action</div></div>';
            foreach($export_array as $key => $exports) {
                //foreach($exports as $export) {
                    $list .= '<div>';
                        $list .= '<div class="export-type">'.$exports['name'].'</div>';
                        $list .= '<div class="export-date">'.$exports['date'].'</div>';
                        $list .= '<div class="action"><a href="'.$export_url.'/'.$exports['file'].'" class="button button-primary">Download</a><span data-path="'.$exports['file'].'"></span></div>';
                    $list .= '</div>';
               // }
            }
            $list .= '</div>';
        } else {
            $list .= 'No exports to show.';
        }
        return $list;
    }

    public function sort_custom($array) {
        // set the known hierarchy ordered alphabetically by the keys
        $hierarchy = [
            'courses',
            'lessons',
            'topics',
            'exams',
            'questions',
            'plugin-settings',
        ];
    
        $array = array_merge(
            array_intersect_key(array_flip($hierarchy), $array),
            $array
        );
        return $array;
    }

    public function flms_exporter_assets() {
		wp_enqueue_script(
			'flms-exporter',
			FLMS_PLUGIN_URL . 'assets/js/exporter.js',
			array('jquery'),
			false,
			true
		);
		wp_localize_script( 'flms-exporter', 'flms_exporter', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));
	}

    function get_export_dir() {
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $upload_dir = $upload_dir . '/flms';
        if (! is_dir($upload_dir)) {
            mkdir( $upload_dir, 0700 );
        }
        $exports_dir = $upload_dir . '/exports';
        if (! is_dir($exports_dir)) {
            mkdir( $exports_dir, 0700 );
        }
        return $exports_dir;
    }

    function get_export_dir_url() {
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $upload_dir_url = $upload['baseurl'];
        $upload_dir = $upload_dir . '/flms';
        if (! is_dir($upload_dir)) {
            mkdir( $upload_dir, 0700 );
        }
        $exports_dir = $upload_dir . '/exports';
        if (! is_dir($exports_dir)) {
            mkdir( $exports_dir, 0700 );
        }
        $export_url = $upload_dir_url . '/flms/exports';
        return $export_url;
    }

}
new FLMS_Exporter();