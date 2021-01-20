<?php 

/**
 * Plugin Name: Docs Plugin
 * Plugin URI: http://wajek.id/wordpress/docs-plugin
 * Description: Plugin Documentation
 * Version: 1.0
 * Author: Moch. Rizal Rachmadani
 * Author URI: http://blog.wajek.id
 */

class WajekDocsPlugin {

    private static $instance;
    public static function instance() {
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private static $prefix = 'wajek_';

    function __construct() {
        add_action('after_setup_theme', array($this, 'register'));
        add_filter('the_content', array($this, 'content_filter'));
        add_action('wp_enqueue_scripts', array($this, 'scripts'));
        add_filter('generate_rewrite_rules', array($this, 'rewrite_rules'));
        add_filter('post_type_link',array($this, 'change_post_link'), 10, 2);
        add_action('term_link', array($this, 'change_term_link'), 10, 3);
        add_action('nav_menu_css_class', array($this, 'menu_class'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'admin_tax_filter'));
        add_action('manage_edit-'.self::$prefix.'docs_sortable_columns', array($this, 'manage_sortable_columns'));
        add_action('template_redirect', array($this, 'template_redirect'));

        // Activate for debugging only
        // add_action('parse_request', array($this, 'parse_request'));
    }

    function parse_request($request) {
        var_dump($request);
    }

    /**
     * Redirect to first post when accesing topics
     */
    function template_redirect() {
        if(is_tax()) {
            $term = get_queried_object();
            if($term->taxonomy == self::$prefix.'docs_topics') {
                $query = new WP_Query([
                    'post_type' => self::$prefix.'docs',
                    'post_parent' => 0,
                    'post_status' => 'publish',
                    'orderby' => 'menu_order',
                    'order' => 'ASC',
                    'tax_query' => [[
                        'taxonomy' => self::$prefix.'docs_topics',
                        'field' => 'slug',
                        'terms' => $term->slug
                    ]]
                ]);
                $posts = $query->get_posts();
                if(count($posts) > 0) {
                    wp_redirect(get_permalink($posts[0]->ID));
                }
            }
        }
    }

    /**
     * Disable admin sort by field
     */
    function manage_sortable_columns($columns) {
        return [];
    }

    /**
     * Show combobox on documentation admin page
     */
    function admin_tax_filter() {
        global $typenow;
        $post_type = self::$prefix.'docs';
        $taxonomy = self::$prefix.'docs_topics';
        if($typenow == $post_type) {
            $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
            $info_taxonomy = get_taxonomy($taxonomy);
            wp_dropdown_categories([
                'show_option_all' => __('Show all'),
                'taxonomy' => $taxonomy,
                'name' => $taxonomy,
                'orderby' => 'name',
                'selected' => $selected,
                'show_count' => true,
                'hide_empty' => true,
                'value_field' => 'slug'
            ]);
        }
    }

    /**
     * Add current-menu-item class
     * when user read its content
     */
    function menu_class($classes = [], $menu_item = false) {
        if($menu_item->type == 'post_type_archive') {
            if(get_post_type() == $menu_item->object) {
                $classes[] = 'current-menu-item';
            }
        }

        if($menu_item->type == 'taxonomy') {
            $terms = get_the_terms(get_the_ID(), $menu_item->object);
            if(count($terms) > 0) {
                if($terms[0]->term_id == $menu_item->object_id) {
                    $classes[] = 'current-menu-item';
                }
            }
        }
        return $classes;
    }

    /**
     * Generating rewrite rules to URL so it can handle following
     * 
     * docs/{term-slug}/{post-slug}
     * docs/{term-slug}
     * docs
     */
    function rewrite_rules($rewrite) {
        $rules = [];
        $post_type = self::$prefix.'docs';
        $tax = self::$prefix.'docs_topics';
        $terms = get_terms([
            'taxonomy' => $tax,
            'hide_empty' => false
        ]);
        foreach($terms as $term) {
            $rules['docs/'.$term->slug . '/(.+?)(?:/([0-9]+))?/?$'] = 'index.php?post_type='.$post_type.'&'.$post_type.'=$matches[1]&name=$matches[1]&term='.$term->slug.'&taxonomy='.$tax;
        }
        $rules['docs/(.+?)(?:/([0-9]+))?/?$'] = 'index.php?post_type='.$post_type.'&'.$tax.'=$matches[1]';
        $rewrite->rules = $rules + $rewrite->rules;
    }

    /**
     * Change term link with following
     * 
     * docs/{term-slug}
     */
    function change_term_link($permalink, $term, $taxonomy) {
        if($taxonomy == self::$prefix.'docs_topics') {
            $permalink = get_home_url().'/docs/'.$term->slug;
        }
        return $permalink;
    }

    /**
     * Change post link with following
     * 
     * docs/{term-slug}/{post-slug}
     */
    function change_post_link($permalink, $post) {
        if($post->post_type == self::$prefix.'docs') {
            $terms = get_the_terms($post, self::$prefix.'docs_topics');
            $slug = '';
            foreach($post->ancestors as $aid) {
                $slug = get_post($aid)->post_name.'/'.$slug;
            }

            $term_slug = '';
            if(!empty($terms)) {
                foreach($terms as $term) {
                    $term_slug = $term->slug.'/'.$term_slug;
                    break;
                }
            }

            $permalink = get_home_url().'/docs/'.$term_slug.$slug.$post->post_name;
        }
        return $permalink;
    }

    /**
     * Style and jasvascript
     */
    function scripts() {
        if(!is_admin()) {
            wp_enqueue_style(self::$prefix.'docs_css', plugins_url('style.css', __FILE__), array(), false, 'all');
        }
    }

    /**
     * Register post type
     */
    function register() {
        register_post_type(self::$prefix.'docs', [
            'label' => 'Documentation',
            'public' => true,
            'hierarchical' => true,
            'menu_icon' => 'dashicons-category',
            'show_in_rest' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'page-attributes'],
            'rewrite' => ['slug' => 'docs']
        ]);
        register_taxonomy(self::$prefix.'docs_topics', self::$prefix.'docs', [
            'labels' => [
                'name' => 'Topics'
            ],
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_ui' => true,
            'show_admin_column' => true
        ]);
        flush_rewrite_rules(false);
    }

    /**
     * Adding next/previous button
     */
    function content_filter($content) {
        global $post;
        if($post->post_type == self::$prefix.'docs') {
            $content .= '<ul class="'.self::$prefix.'docs-nav">';

            $content .= '<li class="'.self::$prefix.'docs-nav-prev">';
            $prev_post = $this->get_prev_post();
            if($prev_post) {
                $content .= '<div class="'.self::$prefix.'docs-nav-prev-label">Sebelumnya</div>';
                $content .= '<a href="'.get_permalink($prev_post->ID).'">'.$prev_post->post_title.'</a>';
            }
            $content .= '</li>';

            $content .= '<li class="'.self::$prefix.'docs-nav-next">';
            $next_post = $this->get_next_post();
            if($next_post) {
                $content .= '<div class="'.self::$prefix.'docs-nav-next-label">Selanjutnya</div>';
                $content .= '<a href="'.get_permalink($next_post->ID).'">'.$next_post->post_title.'</a></li>';
            }
            $content .= '</li>';
            $content .= '</ul>';
        }
        return $content;
    }

    /**
     * Get Prev post
     */
    function get_prev_post() {
        $query = new WP_Query();
        $args = [
            'post_type' => self::$prefix.'docs',
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'DESC',
            'tax_query' => [[
                'taxonomy' => get_query_var('taxonomy'),
                'field' => 'slug',
                'terms' => get_query_var('term')
            ]]
        ];
        add_filter('posts_where', array($this, 'prev_filter'));
        $result = $query->query($args);
        remove_filter('posts_where', array($this, 'prev_filter'));
        if(count($result)) {
            return $result[0];
        }

        /** if doesnt has prev, back to its parent */
        global $post;
        if($post->post_parent > 0) {
            $parent = get_post($post->post_parent);
            if($parent) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Get Next post
     */
    function get_next_post() {
        $args = [
            'post_type' => self::$prefix.'docs',
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'tax_query' => [[
                'taxonomy' => get_query_var('taxonomy'),
                'field' => 'slug',
                'terms' => get_query_var('term')
            ]]
        ];
        $query = new WP_Query();

        /** check if has child, return it */
        add_filter('posts_where', array($this, 'next_with_child_filter'));
        $result = $query->query($args);
        remove_filter('posts_where', array($this, 'next_with_child_filter'));
        if(count($result) > 0) {
            return $result[0];
        }

        /** if doesnt has child, return next post */
        add_filter('posts_where', array($this, 'next_filter'));
        $result = $query->query($args);
        remove_filter('posts_where', array($this, 'next_filter'));
        if(count($result) > 0) {
            return $result[0];
        }

        /** if doesnt has child and doesnt has next post, maybe parent has next */
        add_filter('posts_where', array($this, 'next_from_parent_filter'));
        $result = $query->query($args);
        remove_filter('posts_where', array($this, 'next_from_parent_filter'));
        if(count($result) > 0) {
            return $result[0];
        }

        /** Otherwise, it null */
        return null;
    }

    /**
     * Filter to retrieve next post
     */
    function next_filter($where) {
        global $post;
        return $where.' AND post_parent = '.$post->post_parent.' AND menu_order > '.$post->menu_order;
    }

    /**
     * Filter to retrieve next child post
     */
    function next_with_child_filter($where) {
        global $post;
        return $where.' AND post_parent = '.$post->ID;
    }

    /**
     * Filter to retrieve next from parent
     */
    function next_from_parent_filter($where) {
        global $post;
        $parentPost = get_post($post->post_parent);
        return $where.' AND post_parent = '.$parentPost->post_parent.' AND menu_order > '.$parentPost->menu_order;
    }

    /**
     * Filter to retrieve prev post
     */
    function prev_filter($where) {
        global $post;
        return $where.' AND post_parent = '.$post->post_parent.' AND menu_order < '.$post->menu_order;
    }

}

WajekDocsPlugin::instance();
require(__DIR__.'/widgets/chapter.php');