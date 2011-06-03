<?php
/*
Plugin Name: Google Post Locations
Plugin URI: http://voceconnect.com
Description: Adds the ability to link pages and posts to locations and link the location to a google map.
Version: 1.0
Author: Michael Pretty (prettyboymp)
Author URI: http://voceconnect.com
*/

require_once(dirname(__FILE__) .'/classes.php');

class Post_Locations_API
{
	/**
	 * Google API Key
	 *
	 * @var string
	 */
	private $api_key;

	const POST_LOCATION_META_KEY = 'pl_map_settings';
	const MARKERS_CACHE_KEY = 'markers_archive';

	public static function get_default_map_settings()
	{
		$defaults = get_option('pl_settings');
		if(!$defaults)
		{
			$defaults = array();
		}
		$defaults_core = array(
		'latitude' => '',
		'longitude' => '', 'zoom' => 13,
		'height' => 400, 'width' => 400,
		'is_static' => true,	'markers' => array(),
		'showcontrol' => false);

		$defaults = array_merge($defaults_core, $defaults);
		return $defaults;
	}

	public function __construct()
	{
		$api_keys = $this->get_api_keys();
		if(is_admin())
		{
			$this->api_key = $api_keys["admin"];
		}
		else
		{
			$this->api_key = $api_keys["front-end"];
		}
	}

	public function get_api_keys()
	{
		$api_keys = get_option('pl_google_api_key');
		if($api_keys == false)
		{
			$api_keys = '';
		}
		if($api_keys !== false && !is_array($api_keys))
		{
			$api_keys = array("front-end" => $api_keys, "admin" => $api_keys);
		}
		return $api_keys;
	}

	private function set_api_keys($api_keys)
	{
		if(is_admin())
		{
			$this->api_key = $api_keys["admin"];
		}
		else
		{
			$this->api_key = $api_keys["front-end"];
		}
		update_option('pl_google_api_key', $api_keys);
	}

	public function add_meta_boxes()
	{
		add_meta_box('post-location', 'Post Location', array($this, 'post_meta_box'), 'post', 'normal', 'high');
	}

	public function admin_menu()
	{
		add_options_page('Google Post Locations', 'Google Post Locations', 5, __FILE__, array($this, 'options_page'));
	}

	/**
	 * Replaces Maps Shortcode with Map Div and adds the map to the loader
	 *
	 * We're not going to add the markers at this point since we'll use a call back for
	 * archive markers.  That way, the loading of markers can be more dynamic.
	 *
	 * @param array $args
	 * @return string
	 */
	public function do_archive_shortcode($args)
	{
		$map = new PL_Map($args);
		$map->is_static = false;
		$map->showcontrol = true;

		$map_loader = PL_Map_Loader::get_instance();
		$div_id = $map_loader->add_map($map);

		$cat_args = array(
		'show_option_all' => 'Filter By Category', 'show_option_none' => '',
		'orderby' => 'name', 'order' => 'ASC',
		'show_last_update' => 0, 'show_count' => 0,
		'hide_empty' => 1, 'child_of' => 0,
		'exclude' => '', 'echo' => 0,
		'selected' => 0, 'hierarchical' => 1,
		'name' => 'cat-filter-'.$div_id, 'class' => 'map-filter',
		'depth' => 0, 'tab_index' => 0
		);
		$category_filter = wp_dropdown_categories($cat_args);

		$archive_filter = "<select id=\"arch-filter-$div_id\" name=\"arch-filter-$div_id\" class=\"map-filter\"><option value='0'>Filiter by Month/Year</option>";
		$archive_filter.= str_replace(site_url(), '', wp_get_archives(array('echo'=> 0, 'format' => 'option')));
		$archive_filter.= '</select>';
		return "<div class=\"map-filters\">$category_filter $archive_filter</div><div id=\"$div_id\" class=\"archive-map\"></div><p><span id=\"loading-$div_id\">Loading Map...</span>&nbsp;</p>";
	}

	/**
	 * Returns the current Google API Key
	 *
	 * @return string
	 */
	public function get_api_key()
	{
		return $this->api_key;
	}

	/**
	 * Returns the archive map markers based on category and date passed in.
	 *
	 * @param array $args
	 * @return PL_Map|bool returns false if no map values are found.
	 */
	public function get_archive_markers_ajax()
	{
		global $wpdb;
		$cat_id = 0;
		if(isset($_POST['cat']) && is_numeric($_POST['cat']))
		{
			$cat_id = (int)$_POST['cat'];
		}
		$year = $month = 0;
		if(isset($_POST['month']))
		{
			$date = $_POST['month'];
			if(substr($date, 0, 1) == '/') $date = substr($date, 1);
			if(substr($date, strlen($date)) == '/') $date = substr($date, 0, strlen($date) - 1);
			list($year, $month) = explode('/', $date);
			$year = (int)$year;
			$month = (int)$month;
			if($year < 1 || $month < 1)
			{
				$year = $month = 0;
			}
		}

		//build the query to get the post data
		$select = "SELECT PM.post_id, PM.meta_value FROM $wpdb->postmeta PM";
		$join = "JOIN $wpdb->posts P ON P.ID = PM.post_id";
		$where = $wpdb->prepare("WHERE meta_key = %s AND P.post_status = 'publish'", self::POST_LOCATION_META_KEY);

		if($cat_id !== 0)
		{
			$join.= " JOIN $wpdb->term_relationships TR ON TR.object_id = PM.post_id ".
			"JOIN $wpdb->term_taxonomy TT ON TT.term_taxonomy_id = TR.term_taxonomy_id AND TT.taxonomy = 'category'";
			$where.= $wpdb->prepare(" AND TT.term_id = %d", $cat_id);
		}
		if($year > 0 && $month > 0)
		{
			$where .= $wpdb->prepare(" AND YEAR(P.post_date) = %d AND MONTH(P.post_date) = %d", $year, $month);
		}
		$sql = "$select $join $where";

		//check and see if data is cached
		$cache_key = md5($sq);
		$cache = wp_cache_get(self::MARKERS_CACHE_KEY , 'post_locations');
		if(!isset($cache[$cache_key]))
		{
			$output = '';
			//crap, build the data to send back.
			$marker_metae = $wpdb->get_results($sql);
			$markers = array();

			if(count($marker_metae) > 0)
			{
				//add markers to map
				foreach($marker_metae as $meta)
				{
					$coordinates = unserialize($meta->meta_value);
					$title = get_the_title($meta->post_id);
					$html = "<div class=\"pop-up-content\">";
					if(isset($coordinates['thumbnail']))
					{
						$html.= "<img class=\"thumbnail\" alt=\"$title\" src=\"{$coordinates['thumbnail']}\" />";
					}
					$html .= '<a href="'.get_permalink($meta->post_id).'">'.get_the_title($meta->post_id).'</a></div>';
					$markers[] = new PL_Marker($coordinates['latitude'], $coordinates['longitude'], $title, $html);
				}
			}
			$output = json_encode($markers);

			//save the data for future use.
			$cache[$cache_key] = $output;
			wp_cache_add(self::MARKERS_CACHE_KEY , $cache, 'post_locations');
		}
		else
		{
			$output = $cache[$cache_key];
		}

		die($output);
	}

	public function install()
	{}

	public function options_page()
	{
		if(is_admin())
		{
			$settings = self::get_default_map_settings();
			if(isset($_POST['Submit']))
			{
				$api_keys = array();
				$api_keys['front-end'] = $_POST['api_key'];
				$api_keys['admin'] = $_POST['admin_api_key'];
				$this->set_api_keys($api_keys);

				$settings['latitude'] = $_POST['latitude'];
				$settings['longitude'] = $_POST['longitude'];
				$settings['zoom'] = $_POST['zoom'];
				$settings['height'] = $_POST['height'];
				$settings['width'] = $_POST['width'];
				update_option('pl_settings', $settings);
			}
			$postback_url = parse_url($_SERVER['REQUEST_URI']);
			$postback_url = $postback_url['path'] . (empty($postback_url['query']) ? '' : '?' . $postback_url['query']) . '#' . $unit_tag;
			$api_keys = $this->get_api_keys();
			?>
			<div class="wrap">
				<h2>Google Post Location Options</h2>
				<form method="post" action="<?php echo $postback_url ?>" >
					<table class="form-table">
						<tr valign="top">
							<th>API Key</th>
							<td>
								<input id="api_key" name="api_key" class="regular-text" value="<?php echo $api_keys['front-end'] ?>" />
								<span class="setting-description">Sign up for an api key at <a href="http://code.google.com/apis/maps/signup.html">http://code.google.com/apis/maps/signup.html</a></span>
							</td>
						</tr>
						<tr valign="top">
							<th>API Key &ndash; Admin</th>
							<td>
								<input id="admin_api_key" name="admin_api_key" class="regular-text" value="<?php echo $api_keys['admin'] ?>" />
								<span class="setting-description">Sign up for an api key at <a href="http://code.google.com/apis/maps/signup.html">http://code.google.com/apis/maps/signup.html</a></span>
							</td>
						</tr>
	  				<tr valign="top">
							<th>Default Location</th>
							<td>
								<fieldset>
									<ul>
										<li><label for="latitude">Latitude: <input id="latitude" name="latitude" class="medium-text" value="<?php echo $settings['latitude'] ?>" /></li>
										<li><label for="longitude">Longitude: <input id="longitude" name="longitude" class="medium-text" value="<?php echo $settings['longitude'] ?>" /></li>
										<li><label for="zoom">Zoom: <input id="zoom" name="zoom" class="small-text" value="<?php echo $settings['zoom'] ?>" /></li>
									</ul>
								</fieldset>
							</td>
						</tr>
						<tr valign="top">
							<th>Default Map Size</th>
							<td>
								<fieldset>
									<ul>
										<li><label for="height">Height: <input id="height" name="height" class="small-text" value="<?php echo $settings['height'] ?>" />px</li>
										<li><label for="width">Width: <input id="width" name="width" class="small-text" value="<?php echo $settings['width'] ?>" />px</li>
									</ul>
								</fieldset>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input class="button-primary" type="submit" value="Save Changes" name="Submit" />
					</p>
				</form>
			</div>
			<?php
		}
	}


	/**
	 * Prints the new map div and adds it to the loader.
	 *
	 * @param PL_Map $map
	 */
	public function print_map($map)
	{

		$map_loader = PL_Map_Loader::get_instance();
		$div_id = $map_loader->add_map($map);

		echo "<a href=\"".site_url('/archives')."\"><div id=\"$div_id\" class=\"static-map\"></div></a>";
	}

	public function print_map_scripts()
	{
		$map_loader = PL_Map_Loader::get_instance();
		$map_loader->print_js();
	}

	public function print_post_map($post_id = 0)
	{
		if($post_id == 0)
		{
			$post = &get_post($post_id);
			$post_id = $post->ID;
		}

		$map_settings = get_post_meta($post_id, self::POST_LOCATION_META_KEY, true);
		if(is_array($map_settings) && isset($map_settings['latitude']) && isset($map_settings['longitude']))
		{
			$marker = new PL_Marker($map_settings['latitude'], $map_settings['longitude'], get_the_title($post_id));
			$map = new PL_Map(array('markers' => array($marker), 'latitude' => $map_settings['latitude'], 'longitude' => $map_settings['longitude']));
			$this->print_map($map);
			return true;
		}
		return false;
	}

	public function post_meta_box($post)
	{
		$settings = get_post_meta($post->ID, self::POST_LOCATION_META_KEY, true);
		?>
		<div style="float:left; width: 265px; margin-bottom: 10px;">
			<fieldset>
				<label for="latitude">Latitude:*</label><br /><input id="latitude" name="latitude" value="<?php echo $settings['latitude']?>" tabindex="21" /><br/>
				<label for="longitude">Longitude:*</label><br /><input id="longitude" name="longitude" value="<?php echo $settings['longitude']?>" tabindex="22" /><br />
				<label for="location_text">Location:</label><br /><input id="location_text" name="location_text" value="<?php echo $settings['location_text']?>" tabindex="20" /></label><br/>
				<label for="thumbnail">Thumb URL:</label><br /><input id="thumbnail" name="thumbnail" value="<?php echo $settings['thumbnail']?>" tabindex="23" /><br />
			</fieldset>
		</div>
		<div style="float: left;">
			<label for="Address">Address:</label><br /><input id="address" value="" tabindex="24" style="width: 200px" /> <a href="#" id="search-address">Search</a>
			<div id="post-location-map" style="width: 100%; height: 250px;"></div>
		</div>
		<div class="clear"></div>
		<?php
	}

	public function save_post_location($post_id)
	{
		if(!(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) //autosave doesn't send post_meta, so don't update.
		{
			if ( $real_post_id = wp_is_post_revision($post_id) )
			{
				$post_id = $real_post_id;
			}
			$latitude =  trim($_POST['latitude']);
			$longitude = trim($_POST['longitude']);
			//update location data
			if(is_numeric($latitude) && is_numeric($longitude))
			{
				$settings = array('latitude' => (float) $latitude, 'longitude' => (float) $longitude);
				if(isset($_POST['thumbnail']) && $_POST['thumbnail'] != '')
				{
					$settings['thumbnail'] = $_POST['thumbnail'];
				}
				if(isset($_POST['location_text']) && $_POST['location_text'] != '')
				{
					$settings['location_text'] = $_POST['location_text'];
				}
				update_post_meta($post_id, self::POST_LOCATION_META_KEY, $settings);
			}
			else
			{
				delete_post_meta($post_id, self::POST_LOCATION_META_KEY);
			}
			wp_cache_delete(self::MARKERS_CACHE_KEY, 'post_locations'); //clear cached markers.
		}
	}

	public function setup_front_end_js()
	{
		wp_enqueue_script('clustermarker', get_bloginfo('template_directory') . '/plugins/google-post-locations/js/ClusterMarker.js', array('jquery'), '1.3.4');
		wp_enqueue_script('mypngfix', get_bloginfo('template_directory') . '/plugins/google-post-locations/js/jquery.pngfix.js', array('jquery'), '1.2');
		wp_enqueue_script('google_maps_api', "http://maps.google.com/maps?file=api&amp;v=2&amp;key=$this->api_key");
	}

	public function setup_admin_js()
	{
		wp_enqueue_script('google_maps_api', "http://maps.google.com/maps?file=api&amp;v=2&amp;key=$this->api_key");
		wp_enqueue_script('gpl-admin', get_bloginfo('template_directory') . '/plugins/google-post-locations/js/admin.js', array('jquery', 'google_maps_api'));
	}

	public function the_location_text($before = '', $after = '', $post_id = 0)
	{
		if(!$post_id)
		{
			$post_id = get_the_ID();
		}
		$location_meta = get_post_meta($post_id, self::POST_LOCATION_META_KEY, true );
		if(is_array($location_meta) && isset($location_meta['location_text']))
		{
			echo $before . $location_meta['location_text'] . $after;
		}
	}
}

//setup hooks
$pl = new Post_Locations_API();
add_action('admin_menu', array($pl, 'admin_menu'));
add_action('activate_google-post-locations/google-post-locations.php', array($pl, 'install'));
add_action('do_meta_boxes', array($pl, 'add_meta_boxes'));
add_action('save_post', array($pl, 'save_post_location'));
add_action('template_redirect', array($pl, 'setup_front_end_js')); //calling on template_redirect so we have access to location variables
add_action('wp_footer', array($pl, 'print_map_scripts'));
add_action('wp_ajax_nopriv_get_markers', array($pl, 'get_archive_markers_ajax'));
add_action('wp_ajax_get_markers', array($pl, 'get_archive_markers_ajax'));
add_shortcode('post-location-archive', array($pl, 'do_archive_shortcode'));
if(is_admin())
{
	add_action('init', array($pl, 'setup_admin_js'));
}
unset($pl); //cleanup global space

function the_map($post_id = 0)
{
	$pl = new Post_Locations_API();
	return $pl->print_post_map($post_id);
}

function the_location_text($before = '', $after = '', $post_id = 0)
{
	$pl = new Post_Locations_API();
	return $pl->the_location_text($before, $after, $post_id);
}

if (!function_exists('json_encode'))
{
	function json_encode($a=false)
	{
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a))
		{
			if (is_float($a))
			{
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}

			if (is_string($a))
			{
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			}
			else
			return $a;
		}
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a))
		{
			if (key($a) !== $i)
			{
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList)
		{
			foreach ($a as $v) $result[] = json_encode($v);
			return '[' . join(',', $result) . ']';
		}
		else
		{
			foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
			return '{' . join(',', $result) . '}';
		}
	}
}