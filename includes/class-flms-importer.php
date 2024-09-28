<?php
class FLMS_Importer {
    public function __construct() {
        add_action('admin_menu', array($this,'register_reports_page'));
	}

    public function register_reports_page() {
        add_submenu_page(FLMS_PLUGIN_SLUG,'Import','Import','install_plugins', 'flms-importer',array($this,'flms_import_page'),98);
        add_action('admin_enqueue_scripts', array($this,'flms_importer_assets'));
    }

    public function flms_importer_assets() {
		wp_enqueue_script(
			'flms-importer',
			FLMS_PLUGIN_URL . 'assets/js/importer.js',
			array('jquery','wp-hooks'),
			false,
			true
		);
        $current_user = wp_get_current_user();
		wp_localize_script( 'flms-importer', 'flms_importer', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
            'user_id' => $current_user->ID,
            'max_upload_size' => wp_max_upload_size(),
		));
	}

    public function flms_import_page() {
        ?><div class="wrap">
			
			<h1><?= __(FLMS_PLUGIN_NAME .' Importer', 'flms') ?></h1>
            <div class="page-importer">
                <div class="importer-help flms-tabs theme-color flex">
                    <div class="tab is-active" data-tab="#import">Import</div>    
                    <div class="tab" data-tab="#import-instructions">Help</div>
                    <div class="tab" data-tab="#import-examples">Example Import Files</div>
                </div>
                <div class="flms-tab-content theme-color">
                    <div class="flms-tab-section is-active import-fields" id="import">
                        <div class="importer-nav toggle-div select-toggle-div" id="importer-nav">
                            <div class="step is-settings is-active"><span>Settings</span></div>
                            <div class="step is-mapping"><span>Column Mapping</span></div>
                            <div class="step is-importing"><span>Import</span></div>
                            <div class="step is-done"><span>Done!</span></div>
                        </div>
                        <div class="importer-nav toggle-div select-toggle-div" id="importer-settings">
                            <div class="step is-settings is-active"><span>Settings</span></div>
                            <div class="step is-importing"><span>Import</span></div>
                            <div class="step is-done"><span>Done!</span></div>
                        </div>
                        <div class="importer-nav toggle-div select-toggle-div is-active" id="importer-default">
                            <div class="step is-active"><span>Settings</span></div>
                        </div>
                        <div class="import-settings" id="import-settings-content">
                            <div class="toggle-div is-active" id="import-step-1">
                                <div class="settings-field">
                                    <div class="setting-field-label">Import Type:</div>
                                    <div class="flms-field select">
                                        <select name="import-type" id="import-type" class="select-toggle">
                                            <option value="-1" data-select-toggle="#importer-default">Select an import type</option>
                                            <option value="courses"  data-label="Course" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container, #import-notice">Courses</option>
                                            <option value="lessons"  data-label="Lesson" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container, #import-notice">Lessons</option>
                                            <option value="topics"  data-label="Topic" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container, #import-notice">Topics</option>
                                            <option value="questions" data-label="Question" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container" data-toggle-hide="#import-submit-container, #import-notice">Questions</option>
                                            <option value="exams"  data-label="Exam" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container, #import-notice">Exams</option>
                                            <option value="user-data"  data-label="User" data-select-toggle="#content-import-file, #importer-nav, #import-column-map-container, #import-notice">User Data</option>
                                            <!--<option value="course-data" data-select-toggle="#content-import-file, #importer-nav">Course Data (Feature to come)</option>-->
                                            <option value="plugin-settings" data-toggle-hide="#import-column-map-container" data-select-toggle="#settings-import-file, #importer-settings, #import-submit-container">Plugin Settings</option>
                                            <?php do_action('flms_import_options'); ?>
                                        </select>
                                    </div>
                                </div>
                            
                                <div class="toggle-div select-toggle-div" id="content-import-file">
                                    <div class="settings-field">
                                        <div class="setting-field-label">Import Action:</div>
                                        <div class="flms-field select">
                                            <select name="import-action" id="import-action">
                                                <option value="-1">Select an action</option>
                                                <option value="insert">Insert</option>
                                                <option value="update">Update</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="settings-field">
                                        <div class="setting-field-label">Choose a CSV file from your computer:</div>
                                        <div class="flms-field file">
                                            <input type="file" accept=".csv" name="import-file" id="import-file-upload">
                                            <p class="description">Maximum upload file size: <?php echo size_format(wp_max_upload_size()); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="toggle-div select-toggle-div" id="settings-import-file">
                                    <div class="settings-field">
                                        <div class="setting-field-label">Choose a CSV file from your computer:</div>
                                        <div class="flms-field file">
                                            <input type="file" accept=".txt" name="settings-import-file" id="settings-import-file-upload">
                                            <p class="description">Maximum upload file size: <?php echo size_format(wp_max_upload_size()); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php do_action('flms_import_option_fields'); ?>
                                <div id="import-notice" class="select-toggle-div toggle-div">
                                    <div class="settings-field">
                                        <div class="setting-field-label" style="flex: 0 0 auto"></div>
                                        <div>
                                            <p style="margin: 0;"><strong>Notice:</strong> This importer uses unique IDs in the database to update content. It is not meant to migrate between installations. 
                                        Consider using <a href="https://wordpress.org/plugins/all-in-one-wp-migration/" target="_blank">All-in-One WP Migration</a> or <a href="https://deliciousbrains.com/wp-migrate-db-pro/" target="_blank">WP Migrate</a> to import content from another installation. 
                                        Once migrated, you can <a href="<?php echo admin_url('admin.php?page=flms-exporter'); ?>">export your content</a> and import using this tool.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="flms_import_file" id="flms_import_file" />
                            <div class="toggle-div" id="map-fields-response"></div>
                            <div class="toggle-div select-toggle-div" id="import-submit-container">
                                <button id="import-content-back" class="button button-primary flms-is-hidden">&laquo;&nbsp;Back to Settings</button>
                                <button id="import-content-submit" class="button button-primary is-processing">Import</button>
                            </div>
                            <div class="toggle-div select-toggle-div" id="import-column-map-container">
                                <button id="import-content-column-map" class="button button-primary is-processing">Next</button>
                            </div>
                        </div>
                    </div>
                    <div class="flms-tab-section" id="import-instructions">
                        <p>What is importing? <a href="https://youtube.com/clip/Ugkx6r1FUZaFilMmylYsBI90zQR2TFvkd6eV?si=EaIsnyB2IW3aImDt" target="_blank">Watch a short video</a> to learn how our importer and exporter works.</p> <!--https://youtube.com/clip/UgkxDcv5gig32JfBJ5K5PGA5HPKP-a8dodEx?si=84S2zSt0YjuFEOYk-->
                        <!--<iframe width="560" height="315" src="https://www.youtube.com/embed/CsL5QQWPbAQ?start=112&;end=116&autoplay=1" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>-->
                        <p>If you have existing content you would like to update, it&rsquo;s generally easier to <a href="<?php echo admin_url('admin.php?page=flms-exporter'); ?>">export your content</a> and make adjustments in the csv before importing.</p>
                        <p>You will be asked to map the columns in your import to the fields in the database. If you used an export to update your content, this can generally be ignored. You may opt to not import any column for any import type.</p>
                        
                        <p>Please reference below for import fields:</p>
                        <div class="flms-accordion">
                            <div class="flms-accordion-section">
                                <div class="flms-accordion-heading">Courses<div class="toggle flms-primary-bg"></div></div>
                                <div class="accordion-content to-toggle">
                                    <div class="flms-grid columns-3 importer-fields-details">
                                        <div><strong>ID</strong>The course ID, used when updating courses</div>
                                        <div><strong>Title</strong>The course title (global)</div>
                                        <div><strong>Status</strong>Publish status (global)<br>Options: publish, draft, private, trash</div>
                                        <div><strong>Version</strong>Course version number as integer, lowest being 1</div>
                                        <div><strong>Version Name</strong>Name of the version, used for versions other than the most recent, active version</div>
                                        <div><strong>Version Permalink</strong>Slug for the version, used for versions other than the most recent, active version</div>
                                        <div><strong>Version Status</strong>Whether this version is active<br>Options: draft, publish</div>
                                        <div><strong>Post Content</strong>The content for the course, wysiwyg</div>
                                        <div><strong>Course Access</strong>Whether a purchase is required to enroll in the course<br>Options: open, purchase</div>
                                        <div><strong>Course Progression</strong>How users can navigate the course<br>Options: linear, freeform</div>
                                        <div><strong>Lessons<sup>*</sup></strong>A list of lessons by ID or Title, separated by <span>|</span></div>
                                        <div><strong>Exams<sup>*</sup></strong>A list of exams by ID or Title, separated by <span>|</span></div>
                                        <?php if(flms_is_module_active('course_numbers')) { ?>
                                            <div><strong>Course Number</strong>If course number module is active, the number for the course</div>
                                        <?php } ?>
                                        <?php if(flms_is_module_active('course_credits')) { ?>
                                            <div><strong>'X' Credits</strong>The number of credits to assign for 'X'</div>
                                            <?php if(flms_is_module_active('course_numbers')) { ?>
                                                <div><strong>'X' Course Number</strong>If course number module is active, the number for the 'X' course credit</div>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if(flms_is_module_active('course_taxonomies')) { ?>
                                            <div><strong>'X' as Custom Taxonomy</strong>If 'X' taxonomy has been created, the ID or Title of the term(s), separated by <span>|</span></div>
                                        <?php } ?>
                                        <?php if(flms_is_module_active('course_materials')) { ?>
                                            <div><strong>Course Materials</strong>{path_to_file}{access_type}{Title}, separated by <span>|</span><br>Access types: any, pre-enrollment, post-enrollment, post-completion<br>The importer will attempt to fetch external resources and serve them locally.</div>
                                        <?php } ?>
                                        <?php if(flms_is_module_active('woocommerce')) { ?>
                                            <div><strong>Product Type</strong>The product type if course access is 'purchase'<br>Options: simple, variable</div>
                                            <div><strong>Attributes</strong>Product attributes if product type is 'variable', separated by |<br>Options: attribute_name:attribute ids separated by /<br>Example: pa_purchase-type:28/29</div>
                                            <div><strong>Price(s)</strong>Product prices based on product attributes, separated by |<br>Options: attribute_id:price<br>Example: pa_purchase-type-28:149|pa_purchase-type-29:179</div>
                                        <?php } ?>
                                    </div>
                                    <p><sup>*</sup>These columns <strong>WILL NOT</strong> create this content. It must be imported first.</p>
                                    
                                </div>
                            </div>
                            <div class="flms-accordion-section">
                                <div class="flms-accordion-heading">Lessons<div class="toggle flms-primary-bg"></div></div>
                                <div class="accordion-content to-toggle">
                                    <div class="flms-grid columns-5 importer-fields-details">
                                        <div><strong>Coming Soon</strong>TBD</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flms-accordion-section">
                                <div class="flms-accordion-heading">Topics<div class="toggle flms-primary-bg"></div></div>
                                <div class="accordion-content to-toggle">
                                    <div class="flms-grid columns-5 importer-fields-details">
                                        <div><strong>Coming Soon</strong>TBD</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flms-accordion-section">
                                <div class="flms-accordion-heading">Exam Questions<div class="toggle flms-primary-bg"></div></div>
                                <div class="accordion-content to-toggle">
                                    <div class="flms-grid columns-5 importer-fields-details">
                                        <div><strong>Coming Soon</strong>TBD</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flms-accordion-section">
                                <div class="flms-accordion-heading">Exams<div class="toggle flms-primary-bg"></div></div>
                                <div class="accordion-content to-toggle">
                                    <div class="flms-grid columns-5 importer-fields-details">
                                        <div><strong>Coming Soon</strong>TBD</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p>Inserting a (new) full courses must be done in specific steps because of the dependencies on each layer. Please follow the steps below:</p>
                        <ol>
                            <li>Import the course</li>
                            <li>Import lessons</li>
                            <li>Import topics</li>
                            <li>Import exam questions</li>
                            <li>Import exams</li>
                        </ol>
                        
                            
                    </div>
                    <div class="flms-tab-section" id="import-examples">
                        <p>If you&rsquo;re just starting out, you can download example files below:</p>
                        <ol>
                            <li><a href="<?php echo FLMS_PLUGIN_URL; ?>assets/sample-files/flms-courses-import.csv" target="_blank">Course Import</a></li>
                            <li><a href="<?php echo FLMS_PLUGIN_URL; ?>assets/sample-files/flms-lessons-import.csv" target="_blank">Lessons Import</a></li>
                            <li><a href="<?php echo FLMS_PLUGIN_URL; ?>assets/sample-files/flms-topics-import.csv" target="_blank">Topics Import</a></li>
                            <li><a href="<?php echo FLMS_PLUGIN_URL; ?>assets/sample-files/flms-questions-import.csv" target="_blank">Questions Import</a></li>
                            <li><a href="<?php echo FLMS_PLUGIN_URL; ?>assets/sample-files/flms-exams-import.csv" target="_blank">Exams Import</a></li>
                        </ol>
                        <p>Looking for previously exported files? They can be downloaded from the <a href="<?php echo admin_url('admin.php?page=flms-exporter'); ?>">exporter section</a>.
                    </div>
                </div>
                
         
            </div>
        </div><?php
    }
}
new FLMS_Importer();