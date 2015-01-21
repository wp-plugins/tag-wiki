<?php
/**
 * Plugin Name: Tag Wiki for WordPress
 * Plugin URI: https://github.com/meitar/wp-tag-wiki
 * Description: Use your tags as the basis for a useful, SEO-friendly wiki about your website. <strong>Like this plugin? Please <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Tag%20Wiki%20for%20WordPress&amp;item_number=wp-tag-wiki&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" title="Send a donation to the developer of Tag Wiki for WordPress">donate</a>. &hearts; Thank you!</strong>
 * Version: 0.1
 * Author: Meitar Moscovitz <meitar@maymay.net>
 * Author URI: http://maymay.net/
 * Text Domain: tag-wiki
 * Domain Path: /languages
 */

class WP_TagWikiPlugin {
    private $prefix = 'tag_wiki_';
    private $post_type;
    private $endpoint = 'info';

    public function __construct () {
        $this->post_type = str_replace('_', '-', $this->prefix) . 'page';

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'registerL10n'));
        add_action('init', array($this, 'registerCustomPostType'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_menu', array($this, 'registerAdminMenu'));
        add_action('template_redirect', array($this, 'templateRedirect'));

        add_shortcode('tag-wiki', array($this, 'shortcodeTagWikiLink'));
    }

    public function registerL10n () {
        load_plugin_textdomain('tag-wiki', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function registerCustomPostType () {
        $labels = array(
            'name'               => __('Tag Wiki Pages', 'tag-wiki'),
            'singular_name'      => __('Tag Wiki Page', 'tag-wiki'),
            'add_new'            => __('Add Tag Wiki Page', 'tag-wiki'),
            'add_new_item'       => __('Add Tag Wiki Page', 'tag-wiki'),
            'edit'               => __('Edit Tag Wiki Page', 'tag-wiki'),
            'edit_item'          => __('Edit Tag Wiki Page', 'tag-wiki'),
            'new_item'           => __('New Tag Wiki Page', 'tag-wiki'),
            'view'               => __('View Tag Wiki Page', 'tag-wiki'),
            'view_item'          => __('View Tag Wiki Page', 'tag-wiki'),
            'search'             => __('Search Tag Wiki Pages', 'tag-wiki'),
            'not_found'          => __('No Tag Wiki Pages found', 'tag-wiki'),
            'not_found_in_trash' => __('No Tag Wiki Pages found in trash', 'tag-wiki')
        );
        $url_rewrites = array(); // TODO: Do we need this?
        $args = array(
            'labels' => $labels,
            'description' => __('Wiki pages for your tags', 'tag-wiki'),
            'public' => true,
            'has_archive' => 'tag',
            'show_in_menu' => 'edit.php',
            'menu_icon' => 'dashicons-media-text',
            'supports' => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'trackbacks',
                'custom-fields',
                'comments',
                'revisions' // TODO: Provide a "Recent changes" feed from these revisions
            ),
            'capability_type' => 'page', // TODO: More robust capability management, please.
            'map_meta_cap' => true,
            'taxonomies' => array('category', 'post_tag'),
            'rewrite' => $url_rewrites
        );
        register_post_type($this->post_type, $args);
        add_rewrite_endpoint($this->endpoint, EP_TAGS);
    }

    public function templateRedirect () {
        global $wp_query;
        if (!is_tag()) { return; }
        if (!isset($wp_query->query[$this->endpoint])) { return; }
        $query_name = "{$this->prefix}query";
        $$query_name = $wp_query; // a variable variable name :P
        $p = get_page_by_title($wp_query->query['tag'], 'OBJECT', $this->post_type);
        if (!$p) {
            include(locate_template('404.php'));
            exit();
        }
        $wp_query = new WP_Query("post_type={$this->post_type}&p={$p->ID}");
        include(locate_template(array( // include() so templates get both $wp_query and $$query_name
            "tag-wikipage-{$$query_name->query['tag']}.php",
            'tag-wikipage.php',
            'single.php', // fucks with the WP template hierarchy
            'tag.php',
            'archive.php',
            'index.php' // every template has an index, so safe to exit() now
        )));
        exit();
    }

    public function shortcodeTagWikiLink ($atts, $content = null) {
        $tag = $atts[0];
        // Determine if the linked page (and term) exists
        $page = get_page_by_title($tag, 'OBJECT', $this->post_type);
        $term = term_exists($tag, 'post_tag');
        if ($page && $term) {
            $redlink = false;
            // TODO: Get this to work with and without pretty permalinks.
            //$url = add_query_arg($this->endpoint, true, get_tag_link($t['term_id']));
            $url = trailingslashit(get_tag_link($term['term_id'])) . $this->endpoint;
        } else {
            $redlink = true;
            if (!$page) {
                $url = admin_url("post-new.php?post_type={$this->post_type}&post_title=" . ucfirst($tag));
                $tooltip = sprintf(esc_attr__('Create tag wiki page for %s', 'tag-wiki'), $tag);
            } else if (!$term) {
                $url = admin_url('edit-tags.php?taxonomy=post_tag');
                $tooltip = sprintf(esc_attr__('Create tag %s', 'tag-wiki'), $tag);
            }
        }

        $atts = shortcode_atts(array(
            // basic HTML hooks
            'class' => false,
            'title' => false,
            'target' => false,
            'style' => false,
            'id' => false
        ), $atts);
        // Prepare output HTML
        $html  = '<a ';
        foreach ($atts as $attr => $val) {
            if ($val) {
                $html .= esc_attr($attr) . '="';
                if ('class' === $attr && true === $redlink) {
                    $val .= ' redlink'; // add the 'redlink' class if destination page doesn't exist yet
                }
                $html .= esc_attr($val) . '" ';
            }
        }
        $html .= 'href="' . $url . '">' . ((empty($content)) ? esc_html($tag) : $content);
        if ($redlink) {
            $html .= '<sup title="' . $tooltip . '">?</sup>';
        }
        $html .= '</a>';
        return $html;
    }

    public function activate () {
        flush_rewrite_rules();
    }

    public function deactivate () {
        flush_rewrite_rules();
    }

    public function registerSettings () {
        register_setting(
            $this->prefix . 'settings',
            $this->prefix . 'settings',
            array($this, 'validateSettings')
        );
    }

    public function registerAdminMenu () {
        add_options_page(
            __('Tag Wiki for WordPress Settings', 'tag-wiki'),
            __('Tag Wiki for WordPress', 'tag-wiki'),
            'manage_options',
            $this->prefix . 'settings',
            array($this, 'renderOptionsPage')
        );
    }

    private function debugLog ($msg = '') {
        $msg = trim(strtoupper(str_replace('_', ' ', $this->prefix))) . ': ' . $msg;
        $options = get_option($this->prefix . 'settings');
        if (!empty($options['debug'])) {
            return error_log($msg);
        }
    }

    private function showDonationAppeal () {
?>
<div class="donation-appeal">
    <p style="text-align: center; font-size: larger; width: 70%; margin: 0 auto;"><?php print sprintf(
esc_html__('Tag Wiki for WordPress is provided as free software, but sadly grocery stores do not offer free food. If you like this plugin, please consider %1$s to its %2$s. &hearts; Thank you!', 'tag-wiki'),
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=TJLPJYXHSRBEE&amp;lc=US&amp;item_name=Tag%20Wiki%20for%20WordPress&amp;item_number=wp-tag-wiki&amp;currency_code=USD&amp;bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">' . esc_html__('making a donation', 'tag-wiki') . '</a>',
'<a href="http://Cyberbusking.org/">' . esc_html__('houseless, jobless, nomadic developer', 'tag-wiki') . '</a>'
);?></p>
</div>
<?php
    }

    /**
     * @param array $input An array of of our unsanitized options.
     * @return array An array of sanitized options.
     */
    public function validateSettings ($input) {
        $safe_input = array();
        foreach ($input as $k => $v) {
            switch ($k) {
                case 'debug':
                    $safe_input[$k] = intval($v);
                    break;
            }
        }
        return $safe_input;
    }

    /**
     * Writes the HTML for the options page, and each setting, as needed.
     */
    // TODO: Add contextual help menu to this page.
    public function renderOptionsPage () {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tag-wiki'));
        }
        $options = get_option($this->prefix . 'settings');
?>
<h2><?php esc_html_e('Tag Wiki for WordPress Settings', 'tag-wiki');?></h2>
<form method="post" action="options.php">
<?php settings_fields($this->prefix . 'settings');?>
<fieldset><legend><?php esc_html_e('Customize plugin defaults', 'tag-wiki');?></legend>
<table class="form-table" summary="<?php esc_attr_e('Options for setting default plugin behavior.', 'tag-wiki');?>">
    <tbody>
        <tr>
            <th>
                <label for="<?php esc_attr_e($this->prefix);?>debug">
                    <?php esc_html_e('Enable detailed debugging information?', 'tag-wiki');?>
                </label>
            </th>
            <td>
                <input type="checkbox" id="<?php esc_attr_e($this->prefix);?>debug" name="<?php esc_attr_e($this->prefix);?>settings[debug]" value="1" <?php if (isset($options['debug'])) { checked($options['debug'], 1); } ?> />
                <label for="<?php esc_attr_e($this->prefix);?>debug"><span class="description"><?php
        print sprintf(
            esc_html__('Turn this on only if you are experiencing problems using this plugin, or if you were told to do so by someone helping you fix a problem (or if you really know what you are doing). When enabled, extremely detailed technical information is displayed as a WordPress admin notice when you take certain actions. If you have also enabled WordPress\'s built-in debugging (%1$s) and debug log (%2$s) feature, additional information will be sent to a log file (%3$s). This file may contain sensitive information, so turn this off and erase the debug log file when you have resolved the issue.', 'tag-wiki'),
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG"><code>WP_DEBUG</code></a>',
            '<a href="https://codex.wordpress.org/Debugging_in_WordPress#WP_DEBUG_LOG"><code>WP_DEBUG_LOG</code></a>',
            '<code>' . content_url() . '/debug.log' . '</code>'
        );
                ?></span></label>
            </td>
        </tr>
    </tbody>
</table>
</fieldset>
<?php submit_button();?>
</form>
<?php
        $this->showDonationAppeal();
    } // end public function renderOptionsPage
}

$wp_tag_wiki = new WP_TagWikiPlugin();
