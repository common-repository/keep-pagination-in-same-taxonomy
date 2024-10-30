<?php
/*
Plugin Name: Keep Pagination in Same Taxonomy
Version: 0.13
Description: Makes the previous/next post links use the same taxonomy as the current post. Select which taxonomies are affected in Settings-><strong>Reading</strong>.
Author: Keith Drakard
Author URI: https://drakard.com/
*/

class KeepPaginationInSameTaxonomyPlugin {

	public function __construct() {
		load_plugin_textdomain('KeepPaginationInSameTaxonomy', false, plugin_dir_path(__FILE__).'/languages');

		$this->settings = get_option('KeepPaginationInSameTaxonomyPlugin', array(
			'kpist_toggle' => 'any',
		));

		if (is_admin()) {
			add_action('init', array($this, 'load_admin'), 99);
		} else {
			add_action('get_header', array($this, 'same_pagination')); // rel links
			add_action('the_post', array($this, 'same_pagination'));
		}
	}


	public function activation_hook() {
		update_option('KeepPaginationInSameTaxonomyPlugin', $this->settings, false);
	}

	public function deactivation_hook() {
		delete_option('KeepPaginationInSameTaxonomyPlugin');
	}

	public function load_admin() {
		// in general, don't bother adding anything if we can't use it
		if (current_user_can('manage_options')) {
			$this->taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'objects');
			ksort($this->taxonomies);
			$this->toggles = array(
				'all' => __('ALL', 'KeepPaginationInSameTaxonomy'),
				'any' => __('ANY', 'KeepPaginationInSameTaxonomy'),
			);
			add_action('admin_init', array($this, 'settings_init'));
		}
	}


	/******************************************************************************************************************************************************************/


	public function settings_init() {
		register_setting('reading', 'KeepPaginationInSameTaxonomyPlugin', array($this, 'validate_settings'));
		add_settings_section('KeepPaginationInSameTaxonomySettings', '', array($this, 'settings_form'), 'reading');
	}




	public function settings_form() {
		$output = '<table class="form-table" role="presentation"><tbody>';

		$output.= '<tr><th scope="row">'.__('Keep Pagination in Same Taxonomy', 'KeepPaginationInSameTaxonomy').'</th>'
				. '<td><fieldset><legend class="screen-reader-text">'.__('Keep Pagination in Same Taxonomy', 'KeepPaginationInSameTaxonomy').'</legend>';

		foreach ($this->taxonomies as $key => $obj) {
			$active = (in_array($key, $this->settings) AND isset($this->settings[$key]) AND $this->settings[$key]);
			$checked = ($active != false) ? ' checked="checked"' : '';
			$field = '<input type="checkbox" id="kpist_'.$key.'" name="KeepPaginationInSameTaxonomyPlugin['.$key.']" value="true" '.$checked.'>';
			$output.= '<label for="kpist_'.$key.'">'.$field.' '.$obj->labels->name.'</label><br>';
		}

		$output.= '<p class="description">'
				. __('Posts that have one of the selected taxonomies will have their Previous/Next Post links directed to other posts that share their taxonomy.', 'KeepPaginationInSameTaxonomy')
				. '</p><br>';


		foreach ($this->toggles as $key => $name) {
			$active = (isset($this->settings['kpist_toggle']) AND $this->settings['kpist_toggle'] == $key);
			$checked = ($active != false) ? ' checked="checked"' : '';
			$field = '<input type="radio" id="kpist_'.$key.'" name="KeepPaginationInSameTaxonomyPlugin[kpist_toggle]" value="'.$key.'" '.$checked.'>';
			$output.= '<label for="kpist_'.$key.'">'.$field.' '.$name.'</label> ';
		}

		$output.= '<p class="description">'
				. __('Selecting ALL will only join posts that share at least one common term in ALL of the chosen taxonomies; selecting ANY will join posts that share at least one common term across ANY of the chosen taxonomies.', 'KeepPaginationInSameTaxonomy')
				. '</p>';

		$output.= '</fieldset></td></tr></tbody></table>';

		echo $output;
	}


	public function validate_settings($input) {
		if (! isset($input) OR ! isset($_POST['KeepPaginationInSameTaxonomyPlugin']) OR
			! is_array($input) OR ! is_array($_POST['KeepPaginationInSameTaxonomyPlugin'])
		) return false;

		// reset our settings to no options chosen
		$settings = array_fill_keys(array_keys($this->taxonomies), false);

		foreach ($input as $type => $value) {
			if (isset($settings[$type])) {
				$settings[$type] = (bool) $value;
			} elseif ($type == 'kpist_toggle' AND in_array($value, array_keys($this->toggles))) {
				$settings[$type] = $value;
			}
		}

		return $settings;
	}




	/******************************************************************************************************************************************************************/

	public function same_pagination($post = null) {
		if (! is_singular()) return; // bail if we're not looking at a single post
		if (is_null($post)) global $post; // need this for the get_header call here

		$selected = array_filter($this->settings);
		$taxonomy_names = array_flip(get_object_taxonomies($post));
		$matching_taxonomies = array_intersect($selected, $taxonomy_names);
		
		if (sizeof($matching_taxonomies) > 0) {

			$taxonomy_termids = wp_cache_get($post->ID.'_kpist_taxonomy_termids'); // single request only cache (ie. caches the get_header call for use in the_post)
			if ($taxonomy_termids === false) {
				$taxonomy_termids = array();

				$terms = wp_get_object_terms($post->ID, array_keys($matching_taxonomies));
				$terms = wp_list_pluck($terms, 'taxonomy', 'term_id');
				foreach ($terms as $term_id => $taxonomy) {
					if (! isset($taxonomy_termids[$taxonomy])) $taxonomy_termids[$taxonomy] = array();
					$taxonomy_termids[$taxonomy][] = $term_id;
				}

				wp_cache_add($post->ID.'_kpist_taxonomy_termids', $taxonomy_termids);
			}

			$this->taxonomy_termids = $taxonomy_termids;

			add_filter('get_next_post_join', array($this, 'filter_join_clause'), 10, 5);
			add_filter('get_previous_post_join', array($this, 'filter_join_clause'), 10, 5);
			add_filter('get_next_post_where', array($this, 'filter_where_clause'), 10, 5);
			add_filter('get_previous_post_where', array($this, 'filter_where_clause'), 10, 5);
		}

	}


	public function filter_join_clause($join, $in_same_term, $excluded_terms, $taxonomy, $post) {
		global $wpdb;

		$until = sizeof($this->taxonomy_termids);
		for ($i=0; $i<$until; $i++) {
			$join.= " INNER JOIN {$wpdb->term_relationships} AS kpist_tr{$i} ON p.ID = kpist_tr{$i}.object_id INNER JOIN {$wpdb->term_taxonomy} AS kpist_tt{$i} ON kpist_tr{$i}.term_taxonomy_id = kpist_tt{$i}.term_taxonomy_id";
		}

		return $join;
	}


	public function filter_where_clause($where, $in_same_term, $excluded_terms, $taxonomy, $post) {
		global $wpdb;
	
		$clauses = array(); $i = 0;
		foreach ($this->taxonomy_termids as $taxonomy => $term_ids) {
			$term_ids = array_map('intval', array_diff($term_ids, (array) $excluded_terms));
			if (sizeof($term_ids)) {
				$clauses[]= $wpdb->prepare("(kpist_tt{$i}.term_id IN (".implode(',', $term_ids).") AND kpist_tt{$i}.taxonomy = %s)", $taxonomy);
			}
			$i++;
		}

		if (sizeof($clauses)) {
			switch ($this->settings['kpist_toggle']) {
				case 'any':	$toggle = ' OR ';
							break;
				case 'all':
				default:	$toggle = ' AND ';
			}
			$where.= ' AND ('. implode($toggle, $clauses). ')';
		}

		return $where;
	}


}


$KeepPaginationInSameTaxonomy = new KeepPaginationInSameTaxonomyPlugin();
register_activation_hook(__FILE__, array($KeepPaginationInSameTaxonomy, 'activation_hook'));
register_deactivation_hook(__FILE__, array($KeepPaginationInSameTaxonomy, 'deactivation_hook'));
