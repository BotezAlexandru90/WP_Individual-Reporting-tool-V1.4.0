<?php
/**
 * Plugin Name:       EVE Individual Report
 * Description:       Monitors EVE Online corporation members' character skills from a CSV file.
 * Version:           1.4.0
 * Author:            Surama Badasaz
 * Author URI: https://zkillboard.com/character/91036298/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       eve-skill-monitor
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Ensure we are in the admin area.
if ( is_admin() ) {
    new EVE_Corp_Skill_Monitor_V4();
}

/**
 * Main plugin class.
 */
class EVE_Corp_Skill_Monitor_V4 {

    private $plugin_slug = 'eve-skill-monitor';
    private $settings_option_name = 'eve_skill_monitor_settings';

    /**
     * Constructor. Hooks into WordPress actions.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_get_skill_data', [ $this, 'ajax_get_skill_data' ] );
    }

    /**
     * Adds the menu pages to the WordPress admin.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'EVE Corp Skill Monitor', 'eve-skill-monitor' ), 'EVE IRT', 'edit_posts',
            $this->plugin_slug, [ $this, 'render_monitor_page' ], 'dashicons-chart-bar'
        );

        add_submenu_page(
            $this->plugin_slug, __( 'Settings', 'eve-skill-monitor' ), 'Settings', 'edit_posts',
            $this->plugin_slug . '-settings', [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Registers the plugin's settings.
     */
    public function register_settings() {
        register_setting( 'eve_skill_monitor_options', $this->settings_option_name, [ $this, 'sanitize_settings' ] );
    }

    /**
     * The single sanitization callback for all plugin options.
     */
    public function sanitize_settings( $input ) {
        $new_settings = [];
        $new_settings['skills_file_path'] = isset($input['skills_file_path']) ? esc_url_raw(trim($input['skills_file_path'])) : '';
        $new_settings['multipliers_file_path'] = isset($input['multipliers_file_path']) ? esc_url_raw(trim($input['multipliers_file_path'])) : '';
        $new_sections = [];
        if (isset($input['sections'])) {
            foreach ($input['sections'] as $key => $data) {
                if (isset($data['delete'])) continue;
                $new_sections[$key] = [
                    'name' => sanitize_text_field($data['name']), 'ref_level' => intval($data['ref_level']),
                    'skills' => sanitize_textarea_field(stripslashes($data['skills']))
                ];
            }
        }
        if (!empty($input['new_section']['name'])) {
            $new_key = sanitize_key(strtolower(str_replace(' ', '_', $input['new_section']['name'])));
            if (!isset($new_sections[$new_key])) {
                $new_sections[$new_key] = [
                    'name' => sanitize_text_field($input['new_section']['name']), 'ref_level' => intval($input['new_section']['ref_level']),
                    'skills' => sanitize_textarea_field(stripslashes($input['new_section']['skills']))
                ];
            }
        }
        $new_settings['sections'] = $new_sections;
        add_settings_error('eve_skill_monitor_notices', 'settings_updated', __('Settings saved successfully.', 'eve-skill-monitor'), 'updated');
        return $new_settings;
    }


    /**
     * Enqueues admin-specific scripts and styles, including new grid styles.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_' . $this->plugin_slug && $hook !== 'eve-monitor_page_' . $this->plugin_slug . '-settings' ) {
            return;
        }
        echo '<style>
            /* General Plugin Styles */
            .eve-sm-section { border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .eve-sm-notice { border-left: 4px solid #ffb900; padding: 10px; background: #fff; margin: 15px 0; }
            .eve-sm-error { border-left-color: #dc3232; }
            .eve-sm-success { border-left-color: #46b450; }

            /* Settings Page Specifics */
            #eve-sm-settings-form textarea { width: 100%; min-height: 150px; font-family: monospace; }
            #eve-sm-settings-form .form-table th { width: 180px; }
            
            /* --- NEW: Grid View for Monitor Page --- */
            #skill-results-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
            }
            #skill-results-grid .eve-sm-section {
                margin-bottom: 0; /* Gap is handled by the grid now */
                display: flex;
                flex-direction: column; /* Arrange title and table vertically */
            }
            #skill-results-grid .eve-sm-section h3 {
                margin-top: 0;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #eee;
                font-size: 1.1em;
            }
            .eve-sm-skill-table { width: 100%; border-collapse: collapse; }
            .eve-sm-skill-table th, .eve-sm-skill-table td {
                padding: 6px 8px; /* More compact padding */
                border: 1px solid #e7e7e7;
                text-align: left;
                font-size: 0.95em;
            }
            .eve-sm-skill-table th { background-color: #f8f8f8; }
        </style>';
    }

    /**
     * Provides the default sections and skills as requested.
     */
    private function get_default_sections() {
        return [
            'tanking' => [ 'name' => 'Tanking', 'ref_level' => 4, 'skills' => "EM Armor Compensation\nKinetic Armor Compensation\nExplosive Armor Compensation\nThermal Armor Compensation\nHull Upgrades\nArmor Rigging\nKinetic Shield Compensation\nEM Shield Compensation\nThermal Shield Compensation\nExplosive Shield Compensation\nShield Management\nShield Rigging" ],
            'guns' => [ 'name' => 'Guns', 'ref_level' => 4, 'skills' => "Large Energy Turret\nLarge Projectile Turret\nMedium Energy Turret\nMedium Projectile Turret\nSmall Energy Turret\nSmall Projectile Turret\nLarge Hybrid Turret\nMedium Hybrid Turret\nSmall Hybrid Turret\nLarge Precursor Weapon\nSmall Precursor Weapon" ],
            'gun_specs' => [ 'name' => 'Gun Specs', 'ref_level' => 4, 'skills' => "Large Beam Laser Specialization\nMedium Beam Laser Specialization\nSmall Beam Laser Specialization\nLarge Artillery Specialization\nLarge Pulse Laser Specialization\nMedium Artillery Specialization\nMedium Blaster Specialization\nMedium Pulse Laser Specialization\nSmall Artillery Specialization\nSmall Blaster Specialization\nSmall Pulse Laser Specialization\nLarge Autocannon Specialization\nLarge Disintegrator Specialization\nMedium Autocannon Specialization\nMedium Disintegrator Specialization\nMedium Railgun Specialization\nSmall Autocannon Specialization\nSmall Disintegrator Specialization" ],
            'missiles' => [ 'name' => 'Misiles', 'ref_level' => 4, 'skills' => "Cruise Missiles\nHeavy Missiles\nHeavy Assault Missiles\nLight Missiles\nRockets\nTorpedoes" ],
            'missile_specs' => [ 'name' => 'Misiles Specs', 'ref_level' => 4, 'skills' => "Heavy Assault Missile Specialization\nLight Missile Specialization\nTorpedo Specialization\nCruise Missile Specialization\nHeavy Missile Specialization\nMissile Projection\nTorpedo Specialization\nMissile Bombardment\nRapid Launch\nTarget Navigation Prediction\nWarhead Upgrades\nGuided Missile Precision" ],
            'ships' => [ 'name' => 'Ships', 'ref_level' => 4, 'skills' => "Command Ships\nAmarr Battlecruiser\nMinmatar Battlecruiser\nCaldari Battlecruiser\nGallente Battlecruiser\nPrecursor Battlecruiser\nCommand Ships\nThermodynamics\nRemote Armor Repair Systems\nCapacitor Management\nAmarr Battleship" ],
            'sensorics' => [ 'name' => 'Sensorics', 'ref_level' => 4, 'skills' => "Magnetometric Sensor Compensation\nLadar Sensor Compensation\nRadar Sensor Compensation\nGravimetric Sensor Compensation" ],
            'navigation' => [ 'name' => 'Navication', 'ref_level' => 4, 'skills' => "High speed Maneuvering\nAcceleration Control" ],
            'subsystems' => [ 'name' => 'Subsystems', 'ref_level' => 4, 'skills' => "Amarr Core Systems\nAmarr Propulsion Systems\nAmarr Defensive Systems\nAmarr Offensive Systems\nMinmatar Core Systems\nMinmatar Defensive Systems\nMinmatar Offensive Systems\nMinmatar Propulsion Systems" ],
        ];
    }

    /**
     * Renders the settings page HTML using a single form.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'IRT Settings', 'eve-skill-monitor' ); ?></h1>
            <?php settings_errors('eve_skill_monitor_notices'); ?>
            <p>Configure file paths and manage skill sections. All changes are saved together.</p>
            <form id="eve-sm-settings-form" method="post" action="options.php">
                <?php
                    settings_fields( 'eve_skill_monitor_options' );
                    $options = get_option( $this->settings_option_name, [] );
                    $options['sections'] = $options['sections'] ?? $this->get_default_sections();
                ?>
                <div class="eve-sm-notice"><p><strong>Important:</strong> For best performance and security, using a local <strong>Server File Path</strong> is recommended over a URL.</p></div>
                <h2>File Paths</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="skills_file_path">Skills Data CSV Path or URL</label></th>
                        <td><input type="text" id="skills_file_path" name="<?php echo $this->settings_option_name; ?>[skills_file_path]" value="<?php echo esc_attr( $options['skills_file_path'] ?? '' ); ?>" class="regular-text"/>
                        <p class="description">Full server path (e.g., <code>/var/www/html/...</code>) or URL. Format: <code>Main;Alt;Skill;Level</code></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="multipliers_file_path">Skill Multipliers CSV Path or URL</label></th>
                        <td><input type="text" id="multipliers_file_path" name="<?php echo $this->settings_option_name; ?>[multipliers_file_path]" value="<?php echo esc_attr( $options['multipliers_file_path'] ?? '' ); ?>" class="regular-text"/>
                        <p class="description">Full server path or URL. Format: <code>Skill;Multiplier</code></p></td>
                    </tr>
                </table>
                <hr>
                <h2>Skill Sections Management</h2>
                <?php if (!empty($options['sections'])): foreach ($options['sections'] as $key => $section): ?>
                    <div class="eve-sm-section">
                        <h3><?php echo esc_html($section['name']); ?></h3>
                        <table class="form-table">
                            <tr><th>Section Name</th><td><input type="text" name="<?php echo $this->settings_option_name; ?>[sections][<?php echo esc_attr($key); ?>][name]" value="<?php echo esc_attr($section['name']); ?>" class="regular-text"></td></tr>
                            <tr><th>Reference Level</th><td><input type="number" name="<?php echo $this->settings_option_name; ?>[sections][<?php echo esc_attr($key); ?>][ref_level]" value="<?php echo esc_attr($section['ref_level']); ?>" min="1" max="5" class="small-text"></td></tr>
                            <tr><th>Skills (one per line)</th><td><textarea name="<?php echo $this->settings_option_name; ?>[sections][<?php echo esc_attr($key); ?>][skills]"><?php echo esc_textarea($section['skills']); ?></textarea></td></tr>
                            <tr><th>Delete Section</th><td><label><input type="checkbox" name="<?php echo $this->settings_option_name; ?>[sections][<?php echo esc_attr($key); ?>][delete]" value="1"> Mark for deletion</label></td></tr>
                        </table>
                    </div>
                <?php endforeach; endif; ?>
                <h3>Add New Section</h3>
                <div class="eve-sm-section">
                     <table class="form-table">
                        <tr><th>Section Name</th><td><input type="text" name="<?php echo $this->settings_option_name; ?>[new_section][name]" value="" class="regular-text" placeholder="e.g., Drones"></td></tr>
                        <tr><th>Reference Level</th><td><input type="number" name="<?php echo $this->settings_option_name; ?>[new_section][ref_level]" value="4" min="1" max="5" class="small-text"></td></tr>
                        <tr><th>Skills (one per line)</th><td><textarea name="<?php echo $this->settings_option_name; ?>[new_section][skills]" placeholder="e.g.,
Drones
Drone Interfacing"></textarea></td></tr>
                    </table>
                </div>
                <?php submit_button('Save All Settings'); ?>
            </form>
        </div>
        <?php
    }

    // --- Main Monitor Page and AJAX Handlers ---

    /**
     * Renders the main monitor page.
     */
    public function render_monitor_page() {
        $options=get_option($this->settings_option_name,[]);$skills_file_path=$options['skills_file_path']??'';if(empty($skills_file_path)){echo '<div class="wrap"><div class="eve-sm-error"><p><strong>Error:</strong> The Skills Data CSV path/URL is not set. Please configure it in the <a href="'.admin_url('admin.php?page='.$this->plugin_slug.'-settings').'">Settings</a> page.</p></div></div>';return;}
        $all_data=$this->parse_skills_csv($skills_file_path);if($all_data===false){echo '<div class="wrap"><div class="eve-sm-error"><p><strong>Error:</strong> Could not read the Skills Data CSV file. Check if the path/URL is correct and the file is accessible.</p></div></div>';return;}
        $mains=array_keys($all_data);sort($mains);
        ?>
        <div class="wrap">
            <h1><?php _e( 'EVE Individual Reporting Tool', 'eve-skill-monitor' ); ?></h1>
            <p>Select a Main character and then an Alt to view their skill status compared to the corporation standard.</p>
            <div class="eve-sm-section">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="main-char-select">Select Main Character:</label></th>
                        <td><select id="main-char-select" name="main_char"><option value="">-- Select a Main --</option><?php foreach ( $mains as $main ) : ?><option value="<?php echo esc_attr( $main ); ?>"><?php echo esc_html( $main ); ?></option><?php endforeach; ?></select></td>
                    </tr><tr>
                        <th scope="row"><label for="alt-char-select">Select Alt Character:</label></th>
                        <td><select id="alt-char-select" name="alt_char" disabled><option value="">-- Select a Main first --</option></select></td>
                    </tr>
                </table>
            </div>
            <div id="skill-results-container"><p class="eve-sm-notice">Please make your selections to view skill data.</p></div>
        </div>
        <?php $js_data = []; foreach($all_data as $main => $alts) { $js_data[$main] = array_keys($alts); } ?>
        <script type="text/javascript">
            const EVE_SM_DATA=<?php echo json_encode($js_data);?>,EVE_SM_AJAX_URL="<?php echo admin_url('admin-ajax.php');?>",EVE_SM_NONCE="<?php echo wp_create_nonce('eve_sm_ajax_nonce');?>";document.addEventListener("DOMContentLoaded",function(){const e=document.getElementById("main-char-select"),t=document.getElementById("alt-char-select"),n=document.getElementById("skill-results-container");e.addEventListener("change",function(){const c=this.value;t.innerHTML='<option value="">-- Select an Alt --</option>',n.innerHTML='<p class="eve-sm-notice">Please select an Alt to view skill data.</p>',c&&EVE_SM_DATA[c]?(t.disabled=!1,EVE_SM_DATA[c].forEach(function(e){const n=document.createElement("option");n.value=e,n.textContent=e,t.appendChild(n)})):(t.disabled=!0,t.innerHTML='<option value="">-- Select a Main first --</option>')}),t.addEventListener("change",function(){const c=e.value,l=this.value;if(!c||!l)return void(n.innerHTML='<p class="eve-sm-notice">Please make your selections to view skill data.</p>');n.innerHTML='<p class="eve-sm-notice">Loading skill data...</p>';const s=new FormData;s.append("action","get_skill_data"),s.append("nonce",EVE_SM_NONCE),s.append("main_char",c),s.append("alt_char",l),fetch(EVE_SM_AJAX_URL,{method:"POST",body:s}).then(e=>e.text()).then(e=>{n.innerHTML=e}).catch(e=>{n.innerHTML='<div class="eve-sm-error"><p>An error occurred: '+e+"</p></div>"})})});
        </script>
        <?php
    }

    /**
     * AJAX handler to fetch and display skill data.
     */
    public function ajax_get_skill_data() {
        check_ajax_referer('eve_sm_ajax_nonce','nonce');$main_char=sanitize_text_field($_POST['main_char']);$alt_char=sanitize_text_field($_POST['alt_char']);$options=get_option($this->settings_option_name,[]);$skills_file_path=$options['skills_file_path']??'';$multipliers_file_path=$options['multipliers_file_path']??'';$sections=$options['sections']??$this->get_default_sections();$all_skills_data=$this->parse_skills_csv($skills_file_path);$multipliers=$this->parse_multipliers_csv($multipliers_file_path);if($all_skills_data===false||$multipliers===false){echo '<div class="eve-sm-error"><p><strong>Error:</strong> A required CSV file could not be read. Please check paths in Settings.</p></div>';wp_die();}
        $char_skills=$all_skills_data[$main_char][$alt_char]??[];$sp_map=[0=>0,1=>250,2=>1414,3=>8000,4=>45255,5=>256000];ob_start();
        ?>
        <h2>Skill Analysis for: <?php echo esc_html($alt_char);?> (Main: <?php echo esc_html($main_char);?>)</h2>
        <div id="skill-results-grid">
        <?php
        $sections_with_content = 0;
        foreach($sections as $section_key=>$section_data):
            $skills_in_section=preg_split("/\r\n|\n|\r/",$section_data['skills']);$skills_to_show=[];$ref_level=intval($section_data['ref_level']);
            foreach($skills_in_section as $skill_name_from_settings){$skill_name_from_settings=trim($skill_name_from_settings);if(empty($skill_name_from_settings))continue;$lookup_key=strtolower($skill_name_from_settings);$current_level=$char_skills[$lookup_key]??0;if($current_level<=$ref_level&&$current_level<5){$multiplier=$multipliers[$lookup_key]??1;$target_sp=($sp_map[$ref_level]??0)*$multiplier;$current_sp=($sp_map[$current_level]??0)*$multiplier;$sp_needed=$target_sp-$current_sp;$training_rate_per_day=25.5*60*24;$days_to_train=($training_rate_per_day>0)?($sp_needed/$training_rate_per_day):0;$skills_to_show[]=['name'=>$skill_name_from_settings,'current_level'=>$current_level,'days_to_train'=>$days_to_train];}}
            if(!empty($skills_to_show)):
                $sections_with_content++;
        ?>
            <div class="eve-sm-section">
                <h3><?php echo esc_html($section_data['name']);?> (Ref: <?php echo esc_html($ref_level);?>)</h3>
                <table class="eve-sm-skill-table"><thead><tr><th>Skill Name</th><th>Lvl</th><th>Time to Ref</th></tr></thead>
                <tbody><?php foreach($skills_to_show as $skill):?>
                <tr><td><?php echo esc_html($skill['name']);?></td><td><?php echo esc_html($skill['current_level']);?></td><td><?php echo($skill['days_to_train']>0)?number_format($skill['days_to_train'],2).'d':'Done';?></td></tr>
                <?php endforeach;?></tbody>
                </table>
            </div>
        <?php endif; endforeach;?>
        </div>
        <?php if ($sections_with_content === 0) {
            echo '<p class="eve-sm-notice">This character meets or exceeds all reference skill levels. Nothing to display.</p>';
        }
        echo ob_get_clean();wp_die();
    }

    /**
     * Get CSV content, either from a local file path or a URL.
     */
    private function get_csv_content($path_or_url){if(filter_var($path_or_url,FILTER_VALIDATE_URL)){$response=wp_remote_get($path_or_url);if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){return false;}return wp_remote_retrieve_body($response);}elseif(file_exists($path_or_url)){return file_get_contents($path_or_url);}return false;}
    private function parse_skills_csv($path_or_url){$content=$this->get_csv_content($path_or_url);if($content===false)return false;$data=[];$lines=preg_split("/\r\n|\n|\r/",$content);foreach($lines as $line){if(empty(trim($line)))continue;$row=str_getcsv($line,';');if(count($row)===4){$main=trim($row[0]);$alt=trim($row[1]);$skill=strtolower(trim($row[2]));$level=intval($row[3]);if(!isset($data[$main]))$data[$main]=[];if(!isset($data[$main][$alt]))$data[$main][$alt]=[];$data[$main][$alt][$skill]=$level;}}return $data;}
    private function parse_multipliers_csv($path_or_url){$content=$this->get_csv_content($path_or_url);if($content===false)return false;$data=[];$lines=preg_split("/\r\n|\n|\r/",$content);foreach($lines as $line){if(empty(trim($line)))continue;$row=str_getcsv($line,';');if(count($row)===2){$skill=strtolower(trim($row[0]));$multiplier=intval($row[1]);$data[$skill]=$multiplier;}}return $data;}
}