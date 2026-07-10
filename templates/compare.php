<?php
/**
 * Main Compare Template.
 *
 * This template is loaded when visiting /compare/slug1-vs-slug2
 * Theme override: yourtheme/wp-compare/compare.php
 *
 * @package WPCE
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get comparison logic instance.
$comparison = new \WPCE\Frontend\Comparison_Logic();
$posts = $comparison->get_posts();
$fields = $comparison->get_common_fields();

if ( empty( $posts ) || count( $posts ) < 2 ) {
    echo '<div class="wpce-error">';
    echo '<p>' . __( 'Invalid comparison request.', 'wp-compare-engine' ) . '</p>';
    echo '</div>';
    get_footer();
    return;
}
?>

<div class="wpce-compare-page" id="wpce-compare-page">
    
    <?php
    /**
     * Hook before compare header.
     */
    do_action( 'wpce_before_compare_header', $posts );
    ?>

    <!-- Compare Header -->
    <header class="wpce-compare-header">
        <div class="wpce-compare-grid wpce-header-grid">
            <div class="wpce-feature-column">
                <h1 class="wpce-page-title"><?php echo esc_html( implode( ' vs ', wp_list_pluck( $posts, 'post_title' ) ) ); ?></h1>
            </div>
            <?php foreach ( $posts as $post ) : ?>
                <div class="wpce-item-column wpce-item-header">
                    <?php if ( has_post_thumbnail( $post->ID ) ) : ?>
                        <div class="wpce-item-thumbnail">
                            <?php echo get_the_post_thumbnail( $post->ID, 'medium', array( 'loading' => 'lazy' ) ); ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="wpce-item-title">
                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
                            <?php echo esc_html( get_the_title( $post->ID ) ); ?>
                        </a>
                    </h2>
                    <?php if ( ! empty( $post->post_excerpt ) ) : ?>
                        <div class="wpce-item-excerpt">
                            <?php echo esc_html( wp_trim_words( $post->post_excerpt, 20 ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </header>

    <?php
    /**
     * Hook after compare header.
     */
    do_action( 'wpce_after_compare_header', $posts );
    ?>

    <!-- Compare Table -->
    <div class="wpce-compare-table-wrapper">
        <table class="wpce-compare-table" itemscope itemtype="http://schema.org/Product">
            <thead class="wpce-table-header">
                <tr>
                    <th class="wpce-feature-cell" scope="col"><?php _e( 'Features', 'wp-compare-engine' ); ?></th>
                    <?php foreach ( $posts as $post ) : ?>
                        <th class="wpce-item-cell" scope="col">
                            <span class="wpce-sticky-title"><?php echo esc_html( get_the_title( $post->ID ) ); ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="wpce-table-body">
                <?php
                // Group fields by ACF group if applicable.
                $current_group = '';
                
                foreach ( $fields as $field_key ) :
                    $field_object = get_field_object( $field_key, $posts[0]->ID );
                    $parent = $field_object['parent'] ?? 0;
                    
                    // Check if this field belongs to a group.
                    if ( $parent && 'group' === get_field_object( $parent, $posts[0]->ID )['type'] ) {
                        $group_label = get_field_object( $parent, $posts[0]->ID )['label'];
                        if ( $current_group !== $group_label ) {
                            $current_group = $group_label;
                            ?>
                            <tr class="wpce-group-row">
                                <td colspan="<?php echo count( $posts ) + 1; ?>">
                                    <h3 class="wpce-group-title"><?php echo esc_html( $current_group ); ?></h3>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        $current_group = '';
                    }
                    
                    // Determine if values differ.
                    $has_diff = $comparison->has_differences( $field_key );
                    $row_class = $has_diff ? 'compare-different' : 'compare-same';
                    $row_class = apply_filters( 'wpce_row_class', $row_class, $field_key, $posts );
                    ?>
                    <tr class="wpce-field-row <?php echo esc_attr( $row_class ); ?>" data-field="<?php echo esc_attr( $field_key ); ?>">
                        <td class="wpce-feature-label">
                            <?php echo esc_html( $comparison->get_field_label( $field_key, $posts[0]->ID ) ); ?>
                        </td>
                        <?php foreach ( $posts as $post ) : ?>
                            <td class="wpce-field-value">
                                <?php echo $comparison->render_field( $post->ID, $field_key ); ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                
                <?php if ( empty( $fields ) ) : ?>
                    <tr>
                        <td colspan="<?php echo count( $posts ) + 1; ?>">
                            <p><?php _e( 'No common fields found to compare.', 'wp-compare-engine' ); ?></p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    /**
     * Hook after compare table.
     */
    do_action( 'wpce_after_compare_table', $posts );
    ?>

</div>

<?php
// Enqueue schema.org JSON-LD.
add_action( 'wp_footer', function() use ( $posts ) {
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'ProductGroup',
        'name'     => implode( ' vs ', wp_list_pluck( $posts, 'post_title' ) ),
        'hasVariant' => array(),
    );
    
    foreach ( $posts as $post ) {
        $variant = array(
            '@type' => 'Product',
            'name'  => $post->post_title,
            'url'   => get_permalink( $post->ID ),
        );
        
        if ( has_post_thumbnail( $post->ID ) ) {
            $variant['image'] = wp_get_attachment_image_url( get_post_thumbnail_id( $post->ID ), 'full' );
        }
        
        $schema['hasVariant'][] = $variant;
    }
    
    echo '<script type="application/ld+json">' . json_encode( $schema ) . '</script>';
} );

get_footer();
