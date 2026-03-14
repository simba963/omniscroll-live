<?php
/**
 * Plugin Name: OmniScroll Live
 * Description: Premium ticker with Hex Input Color Pickers, Typography Controls, and Seamless Smooth Scroll.
 * Version: 4.1
 * Author: Sibani
 * Text Domain: omniscroll-live
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// --- GITHUB UPDATE CONFIGURATION ---
define('OSL_GITHUB_USER', 'simba963'); 
define('OSL_GITHUB_REPO', 'omniscroll-live');
// -----------------------------------

// 1. DATABASE SETUP
register_activation_hook( __FILE__, 'osl_install' );
function osl_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'omniscroll_strips';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        settings longtext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// 2. GITHUB UPDATE CHECKER LOGIC
add_filter('pre_set_site_transient_update_plugins', 'osl_check_for_update');
function osl_check_for_update($transient) {
    if (empty($transient->checked)) return $transient;

    $plugin_slug = plugin_basename(__FILE__);
    $res = wp_remote_get("https://api.github.com/repos/" . OSL_GITHUB_USER . "/" . OSL_GITHUB_REPO . "/releases/latest", [
        'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')]
    ]);

    if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200) {
        $release = json_decode(wp_remote_retrieve_body($res));
        $remote_version = ltrim($release->tag_name, 'v');
        $local_version = get_plugin_data(__FILE__)['Version'];

        if (version_compare($local_version, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = "https://github.com/" . OSL_GITHUB_USER . "/" . OSL_GITHUB_REPO;
            $obj->package = $release->zipball_url;
            $transient->response[$plugin_slug] = $obj;
        }
    }
    return $transient;
}

// 3. ADMIN MENU & ASSETS
add_action('admin_menu', 'osl_menu');
function osl_menu() {
    $page = add_menu_page('OmniScroll Live', 'OmniScroll Live', 'manage_options', 'omniscroll_settings', 'osl_settings_page', 'dashicons-leftright');
    add_action('admin_print_scripts-' . $page, 'osl_admin_assets');
}

function osl_admin_assets() {
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">';
}

// 4. ADMIN PAGE
function osl_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'omniscroll_strips';

    if (isset($_POST['osl_save'])) {
        $settings_json = json_encode($_POST['osl']);
        $wpdb->replace($table_name, ['id' => 1, 'settings' => $settings_json]);
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Settings Saved!', 'Your ticker is now live.', 'success'); });</script>";
    }

    $row = $wpdb->get_row("SELECT settings FROM $table_name WHERE id = 1");
    $data = $row ? json_decode($row->settings, true) : [
        'bg_color' => '#111111',
        'speed' => '30',
        'strip_height' => '50',
        'hover_pause' => 'yes',
        'wipe_on_delete' => 'no',
        'slots' => [['text' => 'Welcome to OmniScroll', 'font_size' => '14', 'font_weight' => '600', 'btn_text' => 'Learn More', 'btn_font_size' => '11', 'btn_font_weight' => '900', 'url' => '#', 'btn_bg' => '#ff0000', 'btn_color' => '#ffffff', 'icon_class' => 'fa-brands fa-youtube', 'dot_color' => '#ff0000']]
    ];
    ?>
    <div class="wrap pr-6">
        <div class="flex items-center justify-between mb-8 mt-4">
            <h1 class="text-4xl font-black text-gray-800 tracking-tight italic">OmniScroll <span class="text-blue-600">Live</span></h1>
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-400">v<?php echo get_plugin_data(__FILE__)['Version']; ?></span>
                <div class="bg-blue-600 text-white px-4 py-1 rounded-full text-xs font-bold shadow-lg">By Sibani</div>
            </div>
        </div>

        <div class="sticky top-8 z-50 mb-10">
            <div class="bg-slate-900 rounded-xl shadow-2xl overflow-hidden border-4 border-white">
                <div class="bg-slate-800 px-4 py-1 text-[10px] text-slate-400 uppercase tracking-widest font-bold">Live Stream Preview</div>
                <div id="preview-win" class="w-full flex items-center overflow-hidden transition-all duration-500 <?php echo ($data['hover_pause'] == 'yes') ? 'hover-paused' : ''; ?>" style="height: <?php echo $data['strip_height']; ?>px;">
                    <div id="preview-track" class="flex whitespace-nowrap" style="animation: osl-scroll linear infinite;"></div>
                </div>
            </div>
        </div>

        <form method="post" id="osl-form">
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Background Color</label>
                        <div class="flex gap-2">
                            <input type="color" id="osl_bg_cp" value="<?php echo $data['bg_color']; ?>" class="w-12 h-12 rounded cursor-pointer border-none">
                            <input type="text" name="osl[bg_color]" id="osl_bg" value="<?php echo $data['bg_color']; ?>" class="flex-grow border-2 border-slate-100 p-2 rounded-lg font-mono uppercase text-sm outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Scroll Speed (Sec)</label>
                        <input type="number" name="osl[speed]" id="osl_speed" value="<?php echo $data['speed']; ?>" class="w-full border-slate-200 border-2 p-3 rounded-lg focus:border-blue-500 outline-none h-12">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Strip Height (px)</label>
                        <input type="range" name="osl[strip_height]" id="osl_height" min="30" max="150" value="<?php echo $data['strip_height'] ?? '50'; ?>" class="w-full h-12 accent-blue-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Pause on Hover?</label>
                        <select name="osl[hover_pause]" id="osl_hover_pause" class="w-full border-slate-200 border-2 p-2 rounded-lg h-12">
                            <option value="yes" <?php selected($data['hover_pause'] ?? 'yes', 'yes'); ?>>Yes, Pause</option>
                            <option value="no" <?php selected($data['hover_pause'] ?? 'yes', 'no'); ?>>No, Keep Moving</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-3 bg-red-50 p-4 rounded-xl">
                        <input type="checkbox" name="osl[wipe_on_delete]" value="yes" <?php checked($data['wipe_on_delete'] ?? 'no', 'yes'); ?> class="w-5 h-5 rounded border-red-300">
                        <label class="text-sm text-red-700 font-bold italic">Wipe data?</label>
                    </div>
                </div>
            </div>

            <div id="slots-container" class="space-y-6">
                <?php foreach($data['slots'] as $i => $slot): ?>
                <div class="slot-card bg-white p-8 rounded-2xl shadow-sm border border-slate-200 relative group transition-all hover:border-blue-300">
                    <button type="button" class="remove-slot absolute -top-3 -right-3 bg-red-500 text-white shadow-lg rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-700 transition-colors">×</button>
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                        <div class="md:col-span-5">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Message Content</label>
                            <input type="text" name="osl[slots][<?php echo $i; ?>][text]" value="<?php echo esc_attr($slot['text']); ?>" class="osl-text w-full border-slate-200 border-2 p-3 rounded-lg focus:border-blue-400 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Msg Font Size</label>
                            <input type="number" name="osl[slots][<?php echo $i; ?>][font_size]" value="<?php echo $slot['font_size'] ?? '14'; ?>" class="osl-font-size w-full border-slate-200 border-2 p-3 rounded-lg outline-none h-12">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Msg Weight</label>
                            <select name="osl[slots][<?php echo $i; ?>][font_weight]" class="osl-weight w-full border-slate-200 border-2 p-2 rounded-lg h-12">
                                <option value="400" <?php selected($slot['font_weight'] ?? '600', '400'); ?>>Normal</option>
                                <option value="600" <?php selected($slot['font_weight'] ?? '600', '600'); ?>>Semi-Bold</option>
                                <option value="800" <?php selected($slot['font_weight'] ?? '600', '800'); ?>>Bold</option>
                                <option value="900" <?php selected($slot['font_weight'] ?? '600', '900'); ?>>Black</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Dot Color</label>
                            <div class="flex gap-2">
                                <input type="color" class="osl-dot-cp w-12 h-12 rounded cursor-pointer border-none" value="<?php echo $slot['dot_color'] ?? '#ff0000'; ?>">
                                <input type="text" name="osl[slots][<?php echo $i; ?>][dot_color]" value="<?php echo $slot['dot_color'] ?? '#ff0000'; ?>" class="osl-dot-color flex-grow border-2 border-slate-100 p-2 rounded-lg font-mono uppercase text-sm outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-7 gap-4 pt-4 border-t border-slate-50 bg-slate-50/50 -mx-8 -mb-8 p-8 rounded-b-2xl">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Btn Text</label>
                            <input type="text" name="osl[slots][<?php echo $i; ?>][btn_text]" value="<?php echo esc_attr($slot['btn_text']); ?>" class="osl-btn-label w-full border-slate-200 border-2 p-3 rounded-lg outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Btn URL</label>
                            <input type="text" name="osl[slots][<?php echo $i; ?>][url]" value="<?php echo esc_attr($slot['url'] ?? '#'); ?>" class="osl-btn-url w-full border-slate-200 border-2 p-3 rounded-lg outline-none font-mono text-xs">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Btn Size</label>
                            <input type="number" name="osl[slots][<?php echo $i; ?>][btn_font_size]" value="<?php echo $slot['btn_font_size'] ?? '11'; ?>" class="osl-btn-size w-full border-slate-200 border-2 p-3 rounded-lg outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Icon Class</label>
                            <input type="text" name="osl[slots][<?php echo $i; ?>][icon_class]" value="<?php echo esc_attr($slot['icon_class'] ?? 'fa-brands fa-youtube'); ?>" class="osl-icon w-full border-slate-200 border-2 p-3 rounded-lg outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Btn BG</label>
                            <div class="flex gap-1">
                                <input type="color" class="osl-btn-bg-cp w-10 h-10 rounded cursor-pointer border-none" value="<?php echo $slot['btn_bg']; ?>">
                                <input type="text" name="osl[slots][<?php echo $i; ?>][btn_bg]" value="<?php echo $slot['btn_bg']; ?>" class="osl-btn-bg w-full border-slate-100 border-2 p-1 rounded-lg font-mono text-[10px]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Btn Color</label>
                            <div class="flex gap-1">
                                <input type="color" class="osl-btn-color-cp w-10 h-10 rounded cursor-pointer border-none" value="<?php echo $slot['btn_color']; ?>">
                                <input type="text" name="osl[slots][<?php echo $i; ?>][btn_color]" value="<?php echo $slot['btn_color']; ?>" class="osl-btn-color w-full border-slate-100 border-2 p-1 rounded-lg font-mono text-[10px]">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-12 p-8 bg-slate-900 rounded-3xl flex flex-wrap gap-6 items-center shadow-2xl">
                <input type="submit" name="osl_save" class="bg-blue-600 hover:bg-blue-700 text-white font-black py-4 px-10 rounded-xl shadow-lg cursor-pointer transition-all uppercase tracking-widest text-sm" value="Save Strip Settings">
                <button type="button" id="add-slot" class="bg-white hover:bg-slate-100 text-slate-900 py-4 px-8 rounded-xl font-bold uppercase tracking-widest text-sm shadow-lg">+ Add New Item</button>
                <div class="flex items-center gap-2 bg-black border border-slate-700 p-2 rounded-xl ml-auto">
                    <code id="osl-shortcode-text" class="text-blue-400 font-bold font-mono text-sm px-2">[omniscroll]</code>
                    <button type="button" id="copy-shortcode" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition-all">Copy</button>
                </div>
            </div>
        </form>

        <div class="mt-8 text-center">
            <a href="<?php echo admin_url('update-core.php?force-check=1'); ?>" class="text-slate-400 text-xs hover:text-blue-500 transition-colors uppercase font-bold tracking-tighter">
                <i class="fa-solid fa-rotate"></i> Manually Check GitHub for New Version
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('osl-form');
            const track = document.getElementById('preview-track');
            const win = document.getElementById('preview-win');
            const container = document.getElementById('slots-container');

            function setupColorSync(picker, text) {
                picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); updatePreview(); });
                text.addEventListener('input', () => { if(/^#[0-9A-F]{6}$/i.test(text.value)) { picker.value = text.value; updatePreview(); } });
            }

            function initAllSyncs() {
                setupColorSync(document.getElementById('osl_bg_cp'), document.getElementById('osl_bg'));
                document.querySelectorAll('.slot-card').forEach(card => {
                    setupColorSync(card.querySelector('.osl-dot-cp'), card.querySelector('.osl-dot-color'));
                    setupColorSync(card.querySelector('.osl-btn-bg-cp'), card.querySelector('.osl-btn-bg'));
                    setupColorSync(card.querySelector('.osl-btn-color-cp'), card.querySelector('.osl-btn-color'));
                });
            }

            function updatePreview() {
                win.style.backgroundColor = document.getElementById('osl_bg').value;
                win.style.height = document.getElementById('osl_height').value + 'px';
                track.style.animationDuration = document.getElementById('osl_speed').value + 's';
                
                if(document.getElementById('osl_hover_pause').value === 'yes') { win.classList.add('hover-paused'); } 
                else { win.classList.remove('hover-paused'); }

                let content = '';
                document.querySelectorAll('.slot-card').forEach(card => {
                    const text = card.querySelector('.osl-text').value;
                    const fSize = card.querySelector('.osl-font-size').value;
                    const fWeight = card.querySelector('.osl-weight').value;
                    const bLab = card.querySelector('.osl-btn-label').value;
                    const bSize = card.querySelector('.osl-btn-size').value;
                    const bBg = card.querySelector('.osl-btn-bg').value;
                    const bCol = card.querySelector('.osl-btn-color').value;
                    const dot = card.querySelector('.osl-dot-color').value;
                    const icon = card.querySelector('.osl-icon').value;
                    
                    content += `<div style="display:flex; align-items:center; padding:0 80px; color:#fff; font-family:sans-serif; font-size:${fSize}px; font-weight:${fWeight};">
                        <span style="height:10px; width:10px; border-radius:50%; background:${dot}; margin-right:15px; box-shadow: 0 0 10px ${dot}; flex-shrink:0;"></span>
                        ${text} 
                        ${bLab ? `<span style="background:${bBg}; color:${bCol}; padding:4px 12px; border-radius:6px; margin-left:20px; font-size:${bSize}px; text-transform:uppercase; white-space:nowrap;"><i class="${icon}"></i> ${bLab}</span>` : ''}
                    </div>`;
                });
                track.innerHTML = content + content;
            }

            form.addEventListener('input', updatePreview);
            
            document.getElementById('add-slot').addEventListener('click', function() {
                const count = document.querySelectorAll('.slot-card').length;
                const template = document.querySelector('.slot-card').cloneNode(true);
                template.querySelectorAll('input, select').forEach(i => {
                    if(i.type === 'text') i.value = i.classList.contains('osl-btn-url') ? '#' : (i.classList.contains('font-mono') ? '#FFFFFF' : '');
                    i.name = i.name.replace(/\[\d+\]/, `[${count}]`);
                });
                container.appendChild(template);
                initAllSyncs();
                updatePreview();
            });

            container.addEventListener('click', (e) => {
                if(e.target.closest('.remove-slot') && document.querySelectorAll('.slot-card').length > 1) {
                    e.target.closest('.slot-card').remove();
                    updatePreview();
                }
            });

            document.getElementById('copy-shortcode').addEventListener('click', function() {
                navigator.clipboard.writeText('[omniscroll]');
                this.innerHTML = 'Copied!';
                setTimeout(() => { this.innerHTML = 'Copy'; }, 2000);
            });

            initAllSyncs();
            updatePreview();
        });
    </script>
    <style>
        #preview-win { background-size: 20px 20px; background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px); }
        .hover-paused:hover #preview-track { animation-play-state: paused; }
        @keyframes osl-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    </style>
    <?php
}

// 5. FRONT END SHORTCODE
add_shortcode('omniscroll', 'osl_shortcode');
function osl_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'omniscroll_strips';
    $row = $wpdb->get_row("SELECT settings FROM $table_name WHERE id = 1");
    if (!$row) return '';
    $data = json_decode($row->settings, true);
    
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');

    ob_start(); ?>
    <style>
        .osl-wrap-f { width: 100%; background: <?php echo $data['bg_color']; ?>; overflow: hidden; display: flex; align-items: center; height: <?php echo ($data['strip_height'] ?? '50'); ?>px; }
        .osl-track-f { display: flex; width: max-content; animation: osl-s-f <?php echo $data['speed']; ?>s linear infinite; height: 100%; will-change: transform; }
        .osl-item-f { display: flex; align-items: center; color: #fff; padding: 0 80px; white-space: nowrap; font-family: 'Segoe UI', Roboto, sans-serif; height: 100%; }
        .osl-btn-f { text-decoration: none !important; padding: 6px 15px; border-radius: 6px; margin-left: 20px; display: inline-flex; align-items: center; gap: 8px; text-transform: uppercase; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0,0,0,0.2); line-height: 1; border: none !important; }
        .osl-btn-f:hover { transform: scale(1.05); filter: brightness(1.1); }
        .osl-dot { height: 10px; width: 10px; border-radius: 50%; margin-right: 15px; flex-shrink: 0; animation: osl-pulse 1.5s infinite; }
        <?php if(($data['hover_pause'] ?? 'yes') == 'yes'): ?>
        .osl-wrap-f:hover .osl-track-f { animation-play-state: paused; }
        <?php endif; ?>
        @keyframes osl-s-f { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        @keyframes osl-pulse { 0% { box-shadow: 0 0 0 0 var(--p-c); opacity: 1; } 70% { box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); opacity: 0.8; } 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); opacity: 1; } }
    </style>
    <div class="osl-wrap-f">
        <div class="osl-track-f">
            <?php 
            for($loop=0; $loop<2; $loop++): 
                foreach($data['slots'] as $s): 
                    $dot_c = !empty($s['dot_color']) ? $s['dot_color'] : '#ffffff';
                    $f_size = !empty($s['font_size']) ? $s['font_size'] : '14';
                    $f_weight = !empty($s['font_weight']) ? $s['font_weight'] : '600';
                    $b_size = !empty($s['btn_font_size']) ? $s['btn_font_size'] : '11';
                    $b_weight = !empty($s['btn_font_weight']) ? $s['btn_font_weight'] : '900';
                    $target_url = !empty($s['url']) ? $s['url'] : '#';
                    ?>
                    <div class="osl-item-f" style="font-size: <?php echo $f_size; ?>px; font-weight: <?php echo $f_weight; ?>;">
                        <span class="osl-dot" style="background:<?php echo $dot_c; ?>; --p-c: <?php echo $dot_c; ?>66; box-shadow: 0 0 12px <?php echo $dot_c; ?>;"></span>
                        <?php echo esc_html($s['text']); ?>
                        <?php if(!empty($s['btn_text'])): ?>
                            <a href="<?php echo esc_url($target_url); ?>" target="_blank" class="osl-btn-f" style="background:<?php echo $s['btn_bg']; ?>; color:<?php echo $s['btn_color']; ?> !important; font-size: <?php echo $b_size; ?>px; font-weight: <?php echo $b_weight; ?>;">
                                <?php if(!empty($s['icon_class'])): ?><i class="<?php echo esc_attr($s['icon_class']); ?>"></i><?php endif; ?>
                                <?php echo esc_html($s['btn_text']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; 
            endfor; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
