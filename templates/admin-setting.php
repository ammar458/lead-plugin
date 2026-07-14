<?php
// Handle form submission and save data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    update_option('pbx_enabled', isset($_POST['pbx_enabled']) ? '1' : '');
    update_option('repair_desk_enabled', isset($_POST['repair_desk_enabled']) ? '1' : '');
    update_option('error_api_email', sanitize_text_field($_POST['error_api_email'] ?? ''));

    // Save the dynamic location list (each location has its own PBX key and Repair Desk key)
    $posted_pbx_keys = isset($_POST['location_pbx_key']) && is_array($_POST['location_pbx_key']) ? $_POST['location_pbx_key'] : [];
    $posted_rd_keys  = isset($_POST['location_rd_key']) && is_array($_POST['location_rd_key']) ? $_POST['location_rd_key'] : [];
    $posted_labels   = isset($_POST['location_label']) && is_array($_POST['location_label']) ? $_POST['location_label'] : [];
    $location_count = max(count($posted_pbx_keys), count($posted_rd_keys));

    $locations_to_save = [];
    for ($i = 0; $i < $location_count; $i++) {
        $locations_to_save[] = [
            'pbx'   => sanitize_text_field($posted_pbx_keys[$i] ?? ''),
            'rd'    => sanitize_text_field($posted_rd_keys[$i] ?? ''),
            'label' => sanitize_text_field($posted_labels[$i] ?? ''),
        ];
    }
    // Always keep at least one (possibly empty) location
    if (empty($locations_to_save)) {
        $locations_to_save[] = ['pbx' => '', 'rd' => '', 'label' => ''];
    }
    update_option('rmfl_locations', wp_json_encode($locations_to_save));

    if (isset($_POST['pbx_referral']) && is_array($_POST['pbx_referral'])) {
        $referrals = array_map('sanitize_text_field', $_POST['pbx_referral']);
        update_option('pbx_referral', json_encode($referrals)); // Save as JSON
    }
    if (isset($_POST['repair_desk_referral']) && is_array($_POST['repair_desk_referral'])) {
        $rd_referrals = array_map('sanitize_text_field', $_POST['repair_desk_referral']);
        update_option('repair_desk_referral', json_encode($rd_referrals)); // Save as JSON
    }

    echo '<div class="notice notice-success is-dismissible rmfl-saved-notice"><p>Settings saved.</p></div>';
}

// Register settings
function my_plugin_register_settings() {
    register_setting('form_plugins_options_group', 'pbx_enabled');
    register_setting('form_plugins_options_group', 'repair_desk_enabled');
    register_setting('form_plugins_options_group', 'pbx_referral'); // Save dropdown selection
    register_setting('form_plugins_options_group', 'rmfl_locations');
    register_setting('form_plugins_options_group', 'error_api_email');
}
add_action('admin_init', 'my_plugin_register_settings');

// Retrieve saved settings
$pbx_enabled = get_option('pbx_enabled', '');
$repair_desk_enabled = get_option('repair_desk_enabled', '');
$error_api_email = get_option('error_api_email', '');
$saved_referrals = json_decode(get_option('pbx_referral'), true) ?? [];
$repairDesk_referral = json_decode(get_option('repair_desk_referral'), true) ?? [];

// Retrieve saved locations, migrating from the old single/dual API key options if this is the first load
$locations = json_decode(get_option('rmfl_locations', ''), true);
if (!is_array($locations) || empty($locations)) {
    $legacy_pbx_1 = get_option('pbx_api_key', '');
    $legacy_rd_1  = get_option('repair_desk_api_key', '');
    $legacy_pbx_2 = get_option('pbx_api_key_2', '');
    $legacy_rd_2  = get_option('repair_desk_api_key_2', '');

    $locations = [['pbx' => $legacy_pbx_1, 'rd' => $legacy_rd_1, 'label' => '']];
    if ($legacy_pbx_2 !== '' || $legacy_rd_2 !== '') {
        $locations[] = ['pbx' => $legacy_pbx_2, 'rd' => $legacy_rd_2, 'label' => ''];
    }
}

function render_referral_dropdown($selected_value = '') {    
    $referral_options = json_decode(get_option('pbx_referral_opt'), true) ?? [];
    $dropdown = '<select name="pbx_referral[]" class="referralDropdown">';
    $dropdown .= '<option value="">Select a referral…</option>';
    foreach ($referral_options as $value) {        
        $dropdown .= '<option value="' . $value['val'] . '" ' . selected($selected_value, $value['val'], false) . '>' . $value['text'] . '</option>';    
    }
    $dropdown .= '</select>';
    return $dropdown;
}

// Renders a single "Location" card (PBX key + Repair Desk key)
function render_location_block($index, $pbx_value = '', $rd_value = '', $label_value = '') {
    $number = $index + 1;
    $remove_button = $index > 0
        ? '<button type="button" class="remove-location-btn rmfl-icon-btn" title="Remove this location" aria-label="Remove this location"><span class="dashicons dashicons-no-alt"></span></button>'
        : '';
    ob_start();
    ?>
    <div class="location-block rmfl-location-card">
        <div class="rmfl-location-head">
            <span class="rmfl-location-badge">Location <span class="location-number"><?php echo esc_html($number); ?></span></span>
            <input
                type="text"
                class="rmfl-location-label-input"
                name="location_label[]"
                placeholder="Label this location (e.g. Downtown Store), optional"
                value="<?php echo esc_attr($label_value); ?>"
            />
            <?php echo $remove_button; ?>
        </div>
        <div class="rmfl-location-fields">
            <div class="pbx-field rmfl-field">
                <label>PBX API key</label>
                <input type="text" name="location_pbx_key[]" value="<?php echo esc_attr($pbx_value); ?>" placeholder="Paste PBX API key" />
            </div>
            <div class="rd-field rmfl-field">
                <label>Repair Desk API key</label>
                <input type="text" name="location_rd_key[]" value="<?php echo esc_attr($rd_value); ?>" placeholder="Paste Repair Desk API key" />
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<style>
    .rmfl-wrap { max-width: 900px; margin-top: 20px; }
    .rmfl-wrap * { box-sizing: border-box; }

    .rmfl-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
    }
    .rmfl-header img { width: 42px; height: 42px; border-radius: 8px; flex-shrink: 0; }
    .rmfl-header h1 { margin: 0; font-size: 22px; }
    .rmfl-header p { margin: 2px 0 0; color: #646970; font-size: 13px; }

    .rmfl-card {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 8px;
        padding: 22px 24px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .rmfl-card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 6px;
        color: #1d2327;
    }
    .rmfl-card-title .dashicons { color: #2271b1; }
    .rmfl-card-subtitle {
        margin: 0 0 18px;
        color: #646970;
        font-size: 13px;
    }

    /* Toggle switches */
    .rmfl-toggle-row { display: flex; flex-wrap: wrap; gap: 28px; }
    .rmfl-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: 500;
        color: #1d2327;
    }
    .rmfl-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
    .rmfl-toggle-slider {
        width: 38px;
        height: 21px;
        border-radius: 999px;
        background: #c3c4c7;
        position: relative;
        transition: background .15s ease;
        flex-shrink: 0;
    }
    .rmfl-toggle-slider::before {
        content: '';
        position: absolute;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        background: #fff;
        top: 2px;
        left: 2px;
        transition: transform .15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.25);
    }
    .rmfl-toggle input:checked + .rmfl-toggle-slider { background: #2271b1; }
    .rmfl-toggle input:checked + .rmfl-toggle-slider::before { transform: translateX(17px); }
    .rmfl-toggle input:focus-visible + .rmfl-toggle-slider { outline: 2px solid #2271b1; outline-offset: 2px; }

    /* Location cards */
    #locationsContainer { display: flex; flex-direction: column; gap: 14px; margin-bottom: 14px; }
    .rmfl-location-card {
        border: 1px solid #e2e4e7;
        border-radius: 6px;
        padding: 14px 16px;
        background: #fafafa;
    }
    .rmfl-location-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }
    .rmfl-location-badge {
        display: inline-flex;
        align-items: center;
        background: #2271b1;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .rmfl-location-label-input {
        flex: 1;
        border: 1px dashed #c3c4c7;
        background: transparent;
        padding: 5px 8px;
        font-size: 13px;
        border-radius: 4px;
        min-width: 120px;
    }
    .rmfl-location-label-input:focus { border-style: solid; border-color: #2271b1; }
    .rmfl-icon-btn {
        border: none;
        background: transparent;
        cursor: pointer;
        color: #a7161d;
        padding: 4px;
        border-radius: 4px;
        display: inline-flex;
        line-height: 1;
    }
    .rmfl-icon-btn:hover { background: #fbeaea; }
    .rmfl-location-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 640px) {
        .rmfl-location-fields { grid-template-columns: 1fr; }
    }
    .rmfl-field label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #50575e;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .rmfl-field input[type="text"] { width: 100%; }

    #add_location_btn { display: inline-flex; align-items: center; gap: 6px; }

    /* Referral rows */
    .rmfl-referral-hint {
        background: #f0f6fc;
        border: 1px solid #c5d9ed;
        border-radius: 6px;
        padding: 10px 14px;
        font-size: 12.5px;
        color: #1d2327;
        margin-bottom: 14px;
    }
    .rmfl-referral-hint code {
        background: #fff;
        border: 1px solid #dcdcde;
        padding: 1px 5px;
        border-radius: 3px;
    }
    .referral-container,
    .repair-referral-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        background: #fafafa;
        border: 1px solid #e2e4e7;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }
    .referral-container select,
    .repair-referral-container input[type="text"] {
        min-width: 200px;
    }
    .class-display,
    .repair-desk-class-display {
        font-size: 12px;
        color: #50575e;
        flex-basis: 100%;
    }
    .remove-btn, .rd-remove-btn {
        border: none;
        background: #fff;
        border: 1px solid #dcdcde;
        color: #a7161d;
        width: 26px;
        height: 26px;
        border-radius: 4px;
        cursor: pointer;
        line-height: 1;
        flex-shrink: 0;
    }
    .remove-btn:hover, .rd-remove-btn:hover { background: #fbeaea; }

    .rmfl-error-email-field { max-width: 360px; }
    .rmfl-error-email-field label { display:block; font-size: 12px; font-weight: 600; color: #50575e; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .02em; }
    .rmfl-error-email-field input { width: 100%; }

    .rmfl-submit-row { margin-top: 6px; }
</style>

<div class="wrap rmfl-wrap">

    <div class="rmfl-header">
        <img src="<?php echo esc_url(RMFL_PLUGIN_URI . 'assets/logo/ringo-media-profile.png'); ?>" alt="" />
        <div>
            <h1>Leads Destinations</h1>
            <p>Route form submissions to PBX and Repair Desk, by location.</p>
        </div>
    </div>

    <form method="post" action="">
        <?php
        settings_fields('form_plugins_options_group');
        do_settings_sections('form-plugins-settings');
        ?>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-admin-plugins"></span> Integrations</p>
            <p class="rmfl-card-subtitle">Turn on the systems you want leads delivered to.</p>
            <div class="rmfl-toggle-row">
                <label class="rmfl-toggle">
                    <input type="checkbox" name="pbx_enabled" id="pbx_enabled" value="1" <?php checked($pbx_enabled, '1'); ?> />
                    <span class="rmfl-toggle-slider"></span>
                    <span>Enable PBX</span>
                </label>
                <label class="rmfl-toggle">
                    <input type="checkbox" name="repair_desk_enabled" id="repair_desk_enabled" value="1" <?php checked($repair_desk_enabled, '1'); ?> />
                    <span class="rmfl-toggle-slider"></span>
                    <span>Enable Repair Desk</span>
                </label>
            </div>
        </div>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-location"></span> Locations</p>
            <p class="rmfl-card-subtitle">Each location has its own PBX and Repair Desk API key. Location 1 is used by default, add more if you have additional branches.</p>

            <div id="locationsContainer">
                <?php foreach ($locations as $i => $loc) {
                    echo render_location_block($i, $loc['pbx'] ?? '', $loc['rd'] ?? '', $loc['label'] ?? '');
                } ?>
            </div>
            <button type="button" id="add_location_btn" class="button">
                <span class="dashicons dashicons-plus-alt2"></span> Add location
            </button>

            <!-- Hidden template used by JS to add new locations -->
            <script type="text/template" id="location-template"><?php echo render_location_block(1, '', '', ''); ?></script>
        </div>

        <div class="rmfl-card" id="pbx_referrals_wrap">
            <p class="rmfl-card-title"><span class="dashicons dashicons-chart-line"></span> PBX referral sources</p>
            <button type="button" id="show_referrals_btn" class="button" style="margin-bottom:14px;">Fetch referrals from PBX</button>
            <div class="rmfl-referral-hint">
                Add the matching class to your form. Location 1 uses no number suffix (e.g. <code>form_submit_request-google_ads</code>); Location 2 uses <code>_2</code>, Location 3 uses <code>_3</code>, and so on, matching each location's position above.
            </div>
            <div id="dropdownContainer">
                <?php
                if (!empty($saved_referrals)) {
                    foreach ($saved_referrals as $referral) {
                        $sanitized_referral = sanitize_text_field($referral);
                    echo '<div class="referral-container">'
                    . render_referral_dropdown($sanitized_referral) .
                    '<button type="button" class="remove-btn" title="Remove">×</button>
                     <span class="class-display"></span>
                 </div>';
                    }
                } else {
                    // Default dropdown if no values are saved
                    echo '<div class="referral-container">'
                    . render_referral_dropdown('') .
                    '<button type="button" class="remove-btn" title="Remove">×</button>
                     <span class="class-display"></span>
                 </div>';
                }
                ?>
            </div>
            <button type="button" class="add-btn button">+ Add referral</button>
        </div>

        <div class="rmfl-card" id="repair_desk_referrals_wrap">
            <p class="rmfl-card-title"><span class="dashicons dashicons-hammer"></span> Repair Desk referral sources</p>
            <div class="rmfl-referral-hint">
                Add the matching class to your form. Location 1 uses no number suffix (e.g. <code>rd_form_request-google_ads</code>); Location 2 uses <code>_2</code>, Location 3 uses <code>_3</code>, and so on, matching each location's position above.
            </div>
            <div id="repairDropdownContainer">
                <?php
                if (!empty($repairDesk_referral)) {
                    foreach ($repairDesk_referral as $referral) {
                        $sanitized_referral = sanitize_text_field($referral);
                        echo '<div class="repair-referral-container">
                            <input placeholder="e.g. Google Ads" type="text" value="' . esc_attr($sanitized_referral)  . '" name="repair_desk_referral[]" class="rdReferralInput"/>
                            <button type="button" class="remove-btn" title="Remove">×</button>
                            <span class="repair-desk-class-display"></span>
                        </div>';
                    }
                } else {
                    // Default dropdown if no values are saved
                    echo '<div class="repair-referral-container">
                            <input placeholder="e.g. Google Ads" type="text" value="" name="repair_desk_referral[]" class="rdReferralInput"/>
                            <button type="button" class="rd-remove-btn" title="Remove">×</button>
                            <span class="repair-desk-class-display"></span>
                        </div>';
                }
                ?>
            </div>
            <button type="button" class="rd-add-btn button">+ Add referral</button>
        </div>

        <div class="rmfl-card">
            <p class="rmfl-card-title"><span class="dashicons dashicons-email"></span> Failure notifications</p>
            <p class="rmfl-card-subtitle">If a lead fails to send to PBX or Repair Desk, we'll email this address.</p>
            <div class="rmfl-error-email-field">
                <label for="error_api_email">Notification email</label>
                <input type="email" id="error_api_email" name="error_api_email" value="<?php echo esc_attr($error_api_email); ?>" placeholder="you@example.com" />
            </div>
        </div>

        <div class="rmfl-submit-row">
            <?php submit_button('Save changes'); ?>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
jQuery(document).ready(function ($) {

    function locationCount() {
        return $('#locationsContainer .location-block').length;
    }

    // Build the "add this class" hint text across every currently-configured location
    function buildClassHint(base, source) {
        if (!source) return '';
        const n = locationCount();
        const classes = [];
        for (let i = 1; i <= n; i++) {
            const suffix = i === 1 ? '' : '_' + i;
            classes.push(base + suffix + '-' + source);
        }
        return 'Add <code>' + classes.join(' , ') + '</code> class to your form.';
    }

    function renumberLocations() {
        $('#locationsContainer .location-block').each(function (i) {
            $(this).find('.location-number').text(i + 1);
        });
        // First location can never be removed
        $('#locationsContainer .location-block').each(function (i) {
            const $btn = $(this).find('.remove-location-btn');
            if (i === 0) {
                $btn.remove();
            }
        });
        refreshAllHints();
    }

    function refreshAllHints() {
        $('.referralDropdown').each(function () {
            const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
            $(this).siblings('.class-display').html(buildClassHint('form_submit_request', source));
        });
        $('.rdReferralInput').each(function () {
            const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
            $(this).siblings('.repair-desk-class-display').html(buildClassHint('rd_form_request', source));
        });
    }

    // Add / remove locations
    $('#add_location_btn').on('click', function () {
        const template = $('#location-template').html();
        const $newBlock = $(template);
        $('#locationsContainer').append($newBlock);
        renumberLocations();
    });

    $(document).on('click', '.remove-location-btn', function () {
        $(this).closest('.location-block').remove();
        renumberLocations();
    });

    // Show/Hide API key fields (across all locations) based on the enable checkboxes
    $('#pbx_enabled').on('change', function () {
        $('#pbx_referrals_wrap').toggle(this.checked);
    }).trigger('change');

    $('#repair_desk_enabled').on('change', function () {
        $('#repair_desk_referrals_wrap').toggle(this.checked);
    }).trigger('change');

    // Referral rows
    $(document).on('click', '.remove-btn', function () {
        $(this).parent().remove();
    });

    $(document).on('change', '.referralDropdown', function () {
        const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
        $(this).siblings('.class-display').html(buildClassHint('form_submit_request', source));
    });

    ////////////////////// for repair desk

    $('.rd-add-btn').on('click', function () {
        $('#repairDropdownContainer').append(`
            <div class="repair-referral-container">
                <input placeholder="e.g. Google Ads" type="text" name="repair_desk_referral[]" class="rdReferralInput"/>
                <button type="button" class="rd-remove-btn" title="Remove">×</button>
                <span class="repair-desk-class-display"></span>
            </div>
        `);
    });

    $(document).on('click', '.rd-remove-btn', function () {
        $(this).parent().remove();
    });

    $(document).on('keyup', '.rdReferralInput', function () {
        const source = ($(this).val() || '').toLowerCase().replace(/\s+/g, '_');
        $(this).siblings('.repair-desk-class-display').html(buildClassHint('rd_form_request', source));
    });

    // Initial render
    renumberLocations();

    // Auto-dismiss the "Settings saved" notice
    setTimeout(function () {
        $('.rmfl-saved-notice').fadeOut();
    }, 4000);
});
</script>
