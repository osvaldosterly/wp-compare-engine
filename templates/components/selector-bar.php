<?php
/**
 * Selector Bar Component.
 *
 * Floating bar template (rendered by JS mostly).
 *
 * @package WPCE
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="wpce-floating-bar" class="wpce-floating-bar" style="display: none;">
    <div class="wpce-bar-container">
        <span class="wpce-bar-text">
            <span class="wpce-count">0</span> <?php _e( 'items selected', 'wp-compare-engine' ); ?>
        </span>
        <div class="wpce-bar-actions">
            <button type="button" id="wpce-btn-compare" class="wpce-btn wpce-btn-primary" disabled>
                <?php _e( 'Compare', 'wp-compare-engine' ); ?>
            </button>
            <button type="button" id="wpce-btn-clear" class="wpce-btn wpce-btn-secondary">
                <?php _e( 'Clear All', 'wp-compare-engine' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="wpce-search-modal" class="wpce-modal" style="display: none;" aria-hidden="true">
    <div class="wpce-modal-overlay"></div>
    <div class="wpce-modal-content" role="dialog" aria-modal="true" aria-labelledby="wpce-modal-title">
        <header class="wpce-modal-header">
            <h2 id="wpce-modal-title"><?php _e( 'Search Items to Compare', 'wp-compare-engine' ); ?></h2>
            <button type="button" class="wpce-modal-close" aria-label="<?php _e( 'Close', 'wp-compare-engine' ); ?>">&times;</button>
        </header>
        <div class="wpce-modal-body">
            <input type="text" id="wpce-search-input" class="wpce-search-input" placeholder="<?php _e( 'Type to search...', 'wp-compare-engine' ); ?>" autocomplete="off" />
            <div id="wpce-search-results" class="wpce-search-results"></div>
        </div>
    </div>
</div>
