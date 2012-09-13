<?php
/*
Plugin Name: Twitter Wings Pro
Plugin URI: http://wordpress.org/extend/plugins/twitter-wings/
Version: 2.0.0
Description: Display tweets from one or more users. Output the display URL for links, and hide the t.co URL. Filter tweets by hashtags and/or hide hashtags altogether. Built in caching.
Author: Joe Pahl
Author URI: http://joepahl.is
Text Domain: twitter-wings
*/

/*  Copyright 2012  Joe Pahl (emailme@joepahl.is)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// don't load directly
if (!function_exists('is_admin')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

define( 'TWITTER_WINGS_DIR', WP_PLUGIN_DIR . '/twitter-wings' );
define( 'TWITTER_WINGS_URL', WP_PLUGIN_URL . '/twitter-wings' );

if (!class_exists('Twitter_Wings')) :

class Twitter_Wings {

	function Twitter_Wings() {	
		add_action('admin_init', array(&$this,'init_admin'));
		add_action('init', array(&$this,'init'));
		add_action('admin_menu', array(&$this,'add_pages'));
		// add_action('widgets_init', array('Twitter_Wings_Widget', 'register'));
		add_action( 'widgets_init', array(&$this, 'register_tw_widget'));
		
		register_activation_hook( __FILE__, array(&$this,'activate') );
		register_deactivation_hook( __FILE__, array(&$this,'deactivate') );
	}
	
	function register_tw_widget() {
		register_widget( 'Twitter_Wings_Widget' );
	}

	function activate($networkwide) {
		global $wpdb;

		if (function_exists('is_multisite') && is_multisite()) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ($networkwide) {
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);
					$this->_activate();
				}
				switch_to_blog($old_blog);
				return;
			}	
		} 
		$this->_activate();		
	}

	function deactivate($networkwide) {
		global $wpdb;

		if (function_exists('is_multisite') && is_multisite()) {
			// check if it is a network activation - if so, run the activation function for each blog id
			if ($networkwide) {
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);
					$this->_deactivate();
				}
				switch_to_blog($old_blog);
				return;
			}	
		} 
		$this->_deactivate();		
	}	
	
	function _activate() {
		$options = $this->tw_install();
	}
	
	function _deactivate() {
		include('uninstall.php');
	}
	
	function init_admin() {}

	function init() {
		load_plugin_textdomain( 'twitter-wings', TWITTER_WINGS_DIR . '/languages', basename( dirname( __FILE__ ) ) . '/languages' );			
		if (!defined('TW_VERSION') || TW_VERSION != '2.0.0' || !define('TW_PRO')) {
			define('TW_VERSION', '2.0.0');
			if (get_option('tw_active_version')) {
				include('update.php');
			}
		}
	}
	
	/**
	 * Return data to display
	 * Calculate source, Api or Cache
	 * When cache, it checks first for the transient. If no transient, it checks API. If API fails,
	 * it pulls from option cache.
	 * 
	 */
	function tw_getData($options, $force, $inst=0) {	

		$data = ($options['tw_cache']) ? get_transient("tw_tweet_cache_$inst") : false;
		
		// if (get_transient('tw_tweet_cache')) { echo 'hello transient cache<br/>'; } else { echo 'transient cache expired<br/>'; }
		
		if ($data === false || $force == 'API') {
			include('twitter-wings-class.php');
			$tw_start = new TwitterWingsStart;
			$data = $tw_start->tw_getApiData($options, $inst);
		}
		return $data;
	}

	function add_pages() {
	
		// Add a new submenu
		$this->addpage = add_options_page(__('Twitter Wings Pro', 'twitter-wings'), __('Twitter Wings Pro', 'twitter-wings'), 
											'administrator', 'twitter-wings', 
											array(&$this,'add_twitter_wings_page') );
		add_action("admin_print_styles-$this->addpage", array(&$this,'add_twitter_wings_admin_styles'));
		add_action("admin_print_scripts-$this->addpage", array(&$this,'add_twitter_wings_admin_scripts'));
	}

	function add_twitter_wings_admin_styles() {
		wp_enqueue_style('tw-admin-styles', plugins_url('/css/tw_admin_styles.css', __FILE__));
	}
	
	function add_twitter_wings_admin_scripts() {
		wp_enqueue_script('tw-admin-scripts', plugins_url('/js/tw_admin_scripts.js', __FILE__), array('jquery'));
	}
	
	function add_twitter_wings_page() {
		include('twitter-wings-options.php');
	}
			
	// Default Settings
	// When plugin is activated, update version, and set any new settings to default
	function tw_install($inst=0) {

		if (!defined('TW_VERSION')) define('TW_VERSION', '2.0.0');
		if (!defined('TW_PRO')) define('TW_PRO', true);
		
		if ($inst == 0) {
			$oauth_opts = array('consumerKey' => null,
						 		'consumerSecret' => null,
						 		'accessToken' => null,
						 		'accessTokenSecret' => null,);
			add_option("tw_oauth", $oauth_opts);
			$instance = array();
			$instance[0] = 1;
			add_option('tw_instances', $instance);
		} else {
			$instance = get_option('tw_instances');
			$instance[$inst] = 1;
			update_option('tw_instances', $instance);
		}
		
		$options = array();	
		$options['tw_hashtag'] = null;
		$options['tw_hashes'] = null;
		$options['tw_reply'] = null;
		$options['tw_removehashes'] = null;
		$options['tw_styles'] = null;
		$options['tw_follow'] = null;
		$options['tw_follow_move'] = null;
		$options['tw_follow_name'] = null;
		$options['tw_follow_count'] = null;
		$options['tw_follow_size'] = null;
		$options['tw_follow_lang'] = null;
		
		$options['tw_inst_name'] = ($inst == 0) ? 'New instance' : "New instance_$inst";
		$options['tw_follow_display_name'] = true;
		$options['tw_usernames'] = 'joepahl,bsdeluxe';
		$options['tw_title'] = 'Twitter';
		$options['tw_number'] = 15;
		$options['tw_photos'] = true;
		$options['tw_user_titles'] = true;
		$options['tw_time_below'] = true;
		$options['tw_retweet'] = true;
		$options['tw_https'] = true;
		$options['tw_screennames'] = true;
		$options['tw_chashes'] = true;
		$options['tw_cache'] = true;
		$options['tw_cache_time'] = 60;
		$options['tw_time_form'] = 'M j, Y g:i a';

		add_option("tw_tweet_option_cache_$inst", '');
		add_option("twitter_wings_options_$inst", $options);
		return $options;
	}
	
	function tw_delete_inst($inst=null) {
		delete_option("tw_tweet_option_cache_$inst");
		delete_option("twitter_wings_options_$inst");
		
		$instance = get_option('tw_instances');
		unset($instance[$inst]);
		update_option('tw_instances', $instance);
	}
		
	public function tw_printData($options, $inst=0) {
		
		$force = strtoupper($_GET['tw_force']);
		$data = $this->tw_getData($options, $force, $inst);
		$i = 0;
		
		// Use Option Cache for fallback
		if ((!$data || !is_array($data)) && $force != 'API') {
			$data = get_option("tw_tweet_option_cache_$inst");
		}
		
		if (!$data || !is_array($data)) { return false; }
					
		foreach ($data as $val) {
							
			// Filter by hashtag 			
			if (($options['tw_hashtag'] && $options['tw_hashes']) && $this->tw_checkHashes($options['tw_hashes'], $val['hashtags'][0])) {
				continue;
			}
						
			// "_reasonably_small" image
			$val['avatar'] = $this->str_lreplace('_normal', '_reasonably_small', $val['avatar']);
							
			$img = ($options['tw_photos']) ? "<div class=\"tw-avatar\"><a href=\"http://www.twitter.com/{$val['username']}\" title=\"@{$val['username']} on Twitter\" target=\"twitter\"><img src=\"{$val['avatar']}\" alt=\"{$val['username']}\" height=48 width=48 /></a></div>" : '';
			
			$hd_o = '';
			$hd_c = '';
			
			if (!$options['tw_user_titles']) {
				$screenname = '';
			} else { 
				$screenname = "<p class=\"tw-name byline author vcard\"><a href=\"http://www.twitter.com/{$val['username']}\" class=\"url\" title=\"@{$val['username']} on Twitter\" target=\"twitter\"><span class=\"tw-display-name fn\">{$val['name']}</span> <span class=\"nickname\">@{$val['username']}</span></a></p>";
				$hd_o = "<header>";
				$hd_c = "</header>";
			}
							
			$text = $this->tw_parseStatus($options, $val['text'], $val['urls'], $val['mentions'], $val['username'], $val['hashtags']);
			
			$username = strtolower($val['username']);
			
			$timestamp = "<p class=\"tw-time\"><a href=\"{$val['permalink']}\" rel=\"bookmark\" target=\"twitter\"><time pubdate class=\"updated\" datetime=\"" . date('c', $val['timestamp']) . "\">{$this->tw_showTime($options, $val['timestamp'])}</time></a></p>";

			if (!$options['tw_time_below']) {
				$time_above = $timestamp;
				$time_below = '';
				$hd_o = "<header>";
				$hd_c = "</header>";
			} else { 
				$time_above = '';
				$time_below = '<footer>' . $timestamp . '</footer>';
			}
			
			$twitter_body .= "\t\t\t<article class=\"tw-status tw-$username hentry\">{$img}<div class=\"tw-content\">{$hd_o}{$screenname}{$time_above}{$hd_c}<p class=\"tw-text entry-content\">$text</p>$time_below</div></article>\n";
			$i++;
			if ($i == $options['tw_number']) { break; }
		}		
		return $twitter_body;
	}
	
	/**
	 * Create 'human readable' date format from timestamp
	 * 
	 * 
	 */
	private function tw_showTime($options, $ts){
		
		$c = time() - $ts;
		
		if ($c < 60) {
			return $c . ' seconds ago'; 
		} elseif ($c < 3600) { 
			return (int)($c/60) . ' minutes ago'; 
		} elseif ($c < 3600*24) { 
			return (int)($c/3600) . ' hours ago'; 
		} else {
			$tz = get_option('gmt_offset') * 3600;
			$time_form = ($options['tw_time_form']);
			$nd = date($time_form, $ts+$tz);
			if (!strtotime($nd)) {
				$nd = date('M j\, Y g:i a', $ts+$tz);
			}
			return $nd;
		}
	}
	
	/**
	 * Adding links, screen names, keywords 
	 * Remove unwanted hashes
	 * 
	 */	
	private function tw_parseStatus($options, $text, $urls, $mentions, $username, $hashtags) {

		// add url links
		if ($options['tw_https'] && $urls) {
			
			// loop through URLS (add the pretty links)
			foreach($urls as $url_array) {			
				extract($url_array);
				// fallback for links sans-display_url
				if (!$display_url) {				
					$display_url = $url;
				}
				$text = preg_replace("@{$url}([^A-Za-z0-9\"'<]|\s|$)@iu", "<a href=\"{$url}\" class=\"tw-url\" target=\"_blank\"><span>{$display_url}</span></a>$1", $text); // using @ to delimit, as / shows up in $url
			}
		}	
			
		// add mention links
		if ($options['tw_screennames'] && $mentions) {
			
			// loop through MENTIONS
			foreach($mentions as $mention_array) {
				extract($mention_array);
				$text =  preg_replace("/@{$screen_name}\b/iuU", "<a href=\"http://twitter.com/{$screen_name}\" class=\"tw-mention\" target=\"twitter\">@<span>{$screen_name}</span></a>", $text);
			}
			
			// mentioning yourself is lame, but here ya go (case-insensitive b/c we are dealing with primates)
			if (stripos($text, "@{$username}")) {
				$text = preg_replace("/@{$username}\b/iuU", "<a href=\"http://twitter.com/{$username}\" class=\"tw-mention\" target=\"twitter\">@<span>{$username}</span></a>", $text);
			}
		}
		
		// remove hashes
		if ($options['tw_removehashes'] && $hashtags) {
			foreach($hashtags as $hash_array) {
				extract($hash_array);
				$text = preg_replace("/#{$hashtag}\b/iU", "", $text);
			}
		}
	
		// hashtag links
		if ($options['tw_chashes'] && $hashtags) {
			// loop through HASHTAGS
			foreach($hashtags as $hash_array) {
				extract($hash_array);
				$text =  preg_replace("/#{$hashtag}\b/iU", "<a href=\"http://twitter.com/search?q=%23{$hashtag}\" class=\"tw-hashtag\" target=\"twitter\">#<span>{$hashtag}</span></a>", $text);
			}
		}
		return $text;
	}
	
	/**
	 * Does status have the hashes that we want to display?
	 * "No" means "Yes" and "Yes" means "No"
	 * 
	 */
	private function tw_checkHashes($options_hash, $hashtags) {
				
		if ($options_hash && is_array($hashtags)) {
			
			$options_hash = explode(',',$options_hash);
			
			foreach($options_hash as $hash) {
				
				$hash = trim($hash);
				$hash = str_replace('#', '', $hash);
									
				foreach ($hashtags as $hashtag) {
										
					if (strtolower($hashtag) == strtolower($hash)) { 
						return false;
					} else {
						return true;
					}
				}
			}			
		} else {
			return true;
		}
	}
	
	public function tw_follow($options) {
		$display_username = ($options['tw_follow_display_name']) ? '' : ' data-show-screen-name="false"';
		$count = ($options['tw_follow_count']) ? '' : ' data-show-count="false"';
		$big = (!$options['tw_follow_size']) ? '' : ' data-size="large"';
		
		$lang = ($options['tw_follow_lang'] && $options['tw_follow_lang'] != 'en') ? " data-lang=\"$lang\"" : '';
		
		$follow = "<p class=\"tw-follow\"><a href=\"https://twitter.com/{$options['tw_follow_name']}\" class=\"twitter-follow-button\" data-dnt=\"true\"{$count}{$lang}{$big}{$display_username}>";
		$follow .= sprintf(__('Follow @%s', 'twitter-wings'), $options['tw_follow_name']) . "</a></p>\n\t\t\t";
		$follow .= '<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>';
		
		return $follow;
	}
	
	// replace last occurence of string in string
	function str_lreplace($search, $replace, $subject) {
	    $pos = strrpos($subject, $search);
	    if ($pos !== false) {
	        $subject = substr_replace($subject, $replace, $pos, strlen($search));
	    }
	    return $subject;
	}
	
	function javascript_redirect($location) {
		// redirect after header here can't use wp_redirect($location);
		?>
		  <script type="text/javascript">
		  <!--
		  window.location= <?php echo "'" . $location . "'"; ?>;
		  //-->
		  </script>
		<?php
		exit;
	}

} // end class
endif;

if (!class_exists('Twitter_Wings_Widget')) :
class Twitter_Wings_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
	 		'tw_widget', // Base ID
			'Twitter Wings', // Name
			array('description' => __( 'Twitter Wings Widget', 'twitter-wings' ), ) // Args
		);
	}

	
	public function form($instance) {
		
		if (isset($instance['title']) ) {
			$title = $instance['title'];
		} else {
			$options = get_option("twitter_wings_options_0");
			$title = $options['tw_inst_name'];
		}
		
		$inst = get_option('tw_instances'); ?>
		
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Twitter Wings instance:', 'twitter-wings'); ?></label><br />
			
			<select name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" style="margin-top: 4px; width: 80%;">
				
				<?php foreach($inst as $key => $instance) {
										
					$options = get_option("twitter_wings_options_$key"); ?>
				
					<option value="<?php echo $options['tw_inst_name'] ?>"<?php if ($title == $options['tw_inst_name']) { ?> selected<?php } ?>><?php echo stripslashes($options['tw_inst_name']); ?></option>
				
				<?php } ?>
				
			</select>
		</p>
		
  	<?php }
  	
  	public function update($new_instance, $old_instance) {		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;		
	}
  	
  	public function widget($args, $instance=null) {
  		global $twitter_wings;
  		
  		$inst = $args['inst'];

		if ($inst === null || $inst === false || (is_array($inst) && empty($inst))) {
			
			$inst = get_option('tw_instances');
			
			foreach($inst as $key => $t_instance) {

				$options = get_option("twitter_wings_options_$key");

				if ($options['tw_inst_name'] == $instance['title']) {
					$inst = $key;
					break;
				}
			}
		}

		if ($inst === null || $inst === false || (is_array($inst) && empty($inst))) { return false; }
		  		  		
  		$options = get_option("twitter_wings_options_$inst");
  		
  		// load stylesheet
  		if (!$options['tw_styles']) {
			wp_enqueue_style('tw-stylesheet', TWITTER_WINGS_URL . '/css/tw_styles.css');
  		}
		
		// defaults for header and footer elements  		
  		$header_o = ''; $header_b = ''; $footer = '';
		
		$tw_args = array('before' => null, 'body' => null, 'after' => null);
  		// Add Follow Button! (option must be checked and username must be added)
  		if ($options['tw_follow'] && $options['tw_follow_name'] && $options['tw_follow_move']) {
  			$tw_args['before'] = "<header class=\"tw-header\">\n\t\t\t";
  			$tw_args['body'] = "\n\t\t\t" . $twitter_wings->tw_follow($options) . "\n\t\t</header>";
  		} elseif ($options['tw_follow'] && $options['tw_follow_name']) {
  			$tw_args['after'] = "\t<footer class=\"tw-footer\">\n\t\t\t" . $twitter_wings->tw_follow($options) . "\n\t\t</footer>\n\t";
  		}
  		
  		// Get formated Twitter data
  		$content = $twitter_wings->tw_printData($options, $inst);
  		
  		// Put everything together and echo results
  		if ($content) {
  			$out = "\n\t" . $args['before_widget'];
	   		$out .= "\n\t\t" . $tw_args['before'] . $args['before_title'] . $options['tw_title'] . $args['after_title'] . $tw_args['body'];
	    	$out .= "\n\t\t<div class=\"tw-body\">\n" . $content . "\t\t</div>";    	
	    	$out .= "\n\t" . $tw_args['after'] . $args['after_widget'] . "\n";
  		} else {
  			$out = "\n\t" . $args['before_widget'] . 'Whoops! Looks like something went wrong. Twitter will be back shortly.' . "\n\t" . $args['after_widget'] . "\n";
  		}
  		echo $out;
	}
}
endif;

if (!function_exists('TwitterWings')) {
	function TwitterWings($args = array('inst' => 0,
										'before_widget' => '<div id="twitter-wings" class="tw-wrapper">',
										'after_widget' => '</div>',
										'before_title' => '<h2 class="tw-title">',
										'after_title' => '</h2>'
										)){
		$twitterwings = new Twitter_Wings_Widget();
		$twitterwings->widget($args);
	}
}

global $twitter_wings;
if (class_exists('Twitter_Wings') && !$twitter_wings) {
    $twitter_wings = new Twitter_Wings();	
}