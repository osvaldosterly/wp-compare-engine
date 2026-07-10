<?php
/**
 * Table Main Component.
 *
 * Used by shortcode [wp_compare_table] inside a GenerateBlocks page.
 *
 * @package WPCE
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$comparison = new \WPCE\Frontend\Comparison_Logic();
$posts = $comparison->get_posts();
$fields = $comparison->get_common_fields();

if ( empty( $posts ) || count( $posts ) < 2 ) {
    echo '<p class="wpce-notice">' . __( 'Select at least 2 items to compare.', 'wp-compare-engine' ) . '</p>';
    return;
}
?>

<div class="wpce-shortcode-table">
    <div class="wpce-compare-table-wrapper">
        <table class="wpce-compare-table">
            <thead class="wpce-table-header">
                <tr>
                    <th class="wpce-feature-cell" scope="col"><?php _e( 'Features', 'wp-compare-engine' ); ?></th>
                    <?php foreach ( $posts as $post ) : ?>
                        <th class="wpce-item-cell" scope="col">
                            <div class="wpce-item-mini">
                                <?php if ( has_post_thumbnail( $post->ID ) ) : ?>
                                    <div class="wpce-mini-thumb">
                                        <?php echo get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'loading' => 'lazy' ) ); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="wpce-mini-title"><?php echo esc_html( get_the_title( $post->ID ) ); ?></span>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="wpce-table-body">
                <?php foreach ( $fields as $field_key ) : ?>
                    <?php
                    $has_diff = $comparison->has_differences( $field_key );
                    $row_class = $has_diff ? 'compare-different' : 'compare-same';
                    ?>
                    <tr class="wpce-field-row <?php echo esc_attr( $row_class ); ?>">
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
            </tbody>
        </table>
    </div>
</div>
