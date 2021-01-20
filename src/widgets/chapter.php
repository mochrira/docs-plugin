<?php 

class WajekDocsChapterWidget extends WP_Widget {

    private static $instance;
    public static function instance() {
        if(!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        parent::__construct('wajek-docs-chapter', 'Chapter');
        add_action('widgets_init', array($this, 'register'));
    }

    function register() {
        register_widget('WajekDocsChapterWidget');
    }

    private $currentParent;

    function same_chapter_filter($where) {
        $where .= ' AND post_parent = '.$this->currentParent;
        return $where;
    }

    function print_chapter($parent) {
        global $post;
        $terms = get_the_terms($post->ID, 'wajek_docs_topics');
        $term = $terms[0];

        $this->currentParent = $parent;
        add_filter('posts_where', array($this, 'same_chapter_filter'));
        $chapterQuery = new WP_Query([
            'post_type' => 'wajek_docs', 
            'post_status' => 'publish',
            'orderby' => 'menu_order', 
            'order' => 'ASC',
            'tax_query' => [[
                'taxonomy' => 'wajek_docs_topics',
                'field' => 'slug',
                'terms' => $term->slug
            ]]
        ]);
        $data = $chapterQuery->get_posts();
        remove_filter('posts_where', array($this, 'same_chapter_filter'));

        if(count($data) > 0) { echo '<ul class="wajek_docs_chapter">'; }
        foreach($data as $p) { 
        ?>
            <li>
                <?php if(get_the_ID() == $p->ID) { echo $p->post_title; } else { ?>
                    <a href="<?php echo get_permalink($p->ID); ?>"><?php echo $p->post_title; ?></a>
                <?php } 
                $this->print_chapter($p->ID);
                ?>
            </li>
        <?php
        }
        if(count($data) > 0) { echo '</ul>'; }
    }

    function widget($args, $instance) {
        global $post;
        echo $args['before_widget'];
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }
        $this->print_chapter(0);
        echo $args['after_widget'];
    }

    function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : esc_html__('');
    ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php echo __('Title'); ?></label>
            <input type="text" class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" value="<?php echo esc_attr($title); ?>">
        </p>
    <?php
    }

    function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }

}

WajekDocsChapterWidget::instance();