<?php

class PL_Map
{
	var $latitude;
	public $longitude;
	public $zoom;
	public $height;
	public $width;
	public $is_static;
	public $markers;
	public $showcontrol;
	public $category;

	public function __construct($args)
	{
		if(!is_array($args))
		{
			$args = array();
		}
		$defaults = Post_Locations_API::get_default_map_settings();

		$settings = array_merge($defaults, $args);

		$valid_vars = array_keys(get_object_vars($this));
		foreach($settings as $key => $value)
		{
			if(in_array($key, $valid_vars))
			{
				$this->$key = $value;
			}
		}
	}

	public function add_marker($marker)
	{
		$this->markers[] = $marker;
	}

	public function get_markers()
	{
		//do some cleanup in case someone assigned something else to the markers.
		if(!is_array($this->markers))
		{
			if(is_a($this->markers, 'PL_Marker'))
			{
				$this->markers = array($this->markers);
			}
			else
			{
				$this->markers = array();
			}
		}
		return $this->markers;
	}
}

class PL_Marker
{
	public $latitude;
	public $longitude;
	public $html;
	public $title;

	public function __construct($latitude, $longitude, $title = '', $html = '')
	{
		$this->latitude = $latitude;
		$this->longitude = $longitude;
		$this->html = $html;
		$this->title = $title;
	}

}

class PL_Map_Loader
{
	private static $instance;

	private $page_maps;

	private function __construct()
	{
		$page_maps = array();
	}

	/**
	 * Gets the singleton instance of the map loader.
	 *
	 * @return PL_Map_Loader
	 */
	public static function get_instance()
	{
		if(self::$instance == null)
		{
			self::$instance = new PL_Map_Loader();
		}
		return self::$instance;
	}

	/**
	 * Adds the map settings to the loader and returns the html id of the new map
	 *
	 * @param $map PL_Map
	 * @return string HTML ID of the new map;
	 */
	public function add_map($map)
	{
		$this->page_maps[] = $map;
		$id = 'map-'.(count($this->page_maps) - 1);
		return $id;
	}

	public function print_js()
	{
		if(count($this->page_maps) > 0) //make sure there are maps on the page
		{
			$pl = new Post_Locations_API();
			?>
			<script type="text/javascript">
				// <![CDATA[
				//settings
				var archiveUrl = '<?php echo site_url(); ?>/archives/';
				var ajaxUrl = '<?php bloginfo('siteurl');?>/wp-admin/admin-ajax.php';
				var markerUrl = '<?php echo get_bloginfo('template_directory') . '/plugins/google-post-locations/images/marker.png';?>';
				var shadowUrl = '<?php echo get_bloginfo('template_directory') . '/plugins/google-post-locations/images/shadow.png';?>';
				var grpMarkerUrl = 'http://maps.google.com/mapfiles/arrow.png';
				var grpShadowUrl = 'http://www.google.com/intl/en_us/mapfiles/arrowshadow.png';
				var static_maps = [];
				var dynamic_maps = [];
				<?php
				$i = 0;
				$j = 0;
				foreach($this->page_maps as $id => $map)
				{
					if($map->is_static)
					{
						?>static_maps[<?php echo $i ?>]={'id':'<?php echo $id ?>', 'lat':<?php echo $map->latitude ?>, 'lng':<?php echo $map->longitude ?>, 'zm':<?php echo $map->zoom ?>, 'w':<?php echo $map->width ?>, 'h':<?php echo $map->height ?>};<?php
						$i++;
					}
					else
					{
						?>dynamic_maps[<?php echo $j?>]={'id':'<?php echo $id?>', 'lat':<?php echo $map->latitude ?>, 'lng':<?php echo $map->longitude ?>, 'zm':<?php echo $map->zoom ?>, 'w':<?php echo $map->width ?>, 'h':<?php echo $map->height ?>};<?php
						$j++;
					}
				}
				?>
				jQuery(document).ready(function(){
					for(var i=0; i<static_maps.length; i++)
					{
						add_static_map(static_maps[i].id, static_maps[i].lat, static_maps[i].lng, static_maps[i].zm, static_maps[i].w, static_maps[i].h, '<?php echo $pl->get_api_key() ?>', markerUrl, shadowUrl);
					}
					for(var i=0; i<dynamic_maps.length; i++)
					{
						add_dynamic_map(dynamic_maps[i].id, dynamic_maps[i].lat, dynamic_maps[i].lng, dynamic_maps[i].zm, dynamic_maps[i].w, dynamic_maps[i].h);
					}
				});
				// ]]>
			</script>
			<?php
		}
	}

}
