/**
 * WP Compare Engine - Frontend JavaScript
 * Handles: Selection, LocalStorage, Floating Bar, Search Modal, AJAX
 */

(function($) {
    'use strict';

    // Configuration from PHP
    const config = window.wpceConfig || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        restUrl: '/wp-json/wp-compare/v1/',
        nonce: '',
        maxItems: 3,
        minItems: 2,
        compareSlug: 'compare'
    };

    // State
    let compareList = [];

    // DOM Elements
    const $body = $('body');
    let $floatingBar = null;
    let $searchModal = null;

    /**
     * Initialize
     */
    function init() {
        loadFromStorage();
        renderFloatingBar();
        bindEvents();
        updateCheckboxStates();
    }

    /**
     * Load data from LocalStorage
     */
    function loadFromStorage() {
        const stored = localStorage.getItem('wpce_compare_list');
        if (stored) {
            try {
                compareList = JSON.parse(stored);
                if (!Array.isArray(compareList)) {
                    compareList = [];
                }
            } catch (e) {
                compareList = [];
            }
        }
    }

    /**
     * Save data to LocalStorage
     */
    function saveToStorage() {
        localStorage.setItem('wpce_compare_list', JSON.stringify(compareList));
        triggerEvent('updated');
    }

    /**
     * Trigger Custom Event
     */
    function triggerEvent(eventName, detail = {}) {
        const event = new CustomEvent(`wpce:${eventName}`, { detail });
        document.dispatchEvent(event);
    }

    /**
     * Bind Global Events
     */
    function bindEvents() {
        // Delegate checkbox clicks
        $body.on('change', '.wpce-compare-trigger', function(e) {
            e.preventDefault();
            const $checkbox = $(this);
            const postId = $checkbox.data('post-id');
            const postSlug = $checkbox.data('post-slug');
            const postTitle = $checkbox.closest('.post, .product, .wpce-compare-wrapper').find('.entry-title, .woocommerce-loop-product__title, h2, h3').first().text().trim() || 'Item';
            
            // Fallback for title if not found immediately (optional AJAX fetch could go here)
            // For now, we rely on the slug/ID and fetch details later if needed, 
            // but the bar needs a label. Let's assume the user sees the card.
            // Better approach: The checkbox wrapper should ideally have data-title too.
            // Let's try to find a title nearby or use "Item"
            
            toggleItem(postId, postSlug, postTitle);
        });

        // Floating Bar Actions
        $body.on('click', '.wpce-btn-compare', function(e) {
            e.preventDefault();
            goToComparePage();
        });

        $body.on('click', '.wpce-btn-clear', function(e) {
            e.preventDefault();
            clearAll();
        });

        $body.on('click', '.wpce-btn-search', function(e) {
            e.preventDefault();
            openSearchModal();
        });

        $body.on('click', '.wpce-modal-close, .wpce-modal-overlay', function(e) {
            if ($(e.target).is('.wpce-modal-overlay') || $(e.target).is('.wpce-modal-close')) {
                closeSearchModal();
            }
        });

        // Search Modal Actions
        $body.on('click', '.wpce-search-result-item', function(e) {
            e.preventDefault();
            const $item = $(this);
            const id = $item.data('id');
            const slug = $item.data('slug');
            const title = $item.data('title');
            
            addItem(id, slug, title);
            closeSearchModal();
        });

        $body.on('keyup', '.wpce-search-input', function(e) {
            const query = $(this).val();
            if (query.length >= 2) {
                performSearch(query);
            } else {
                $('.wpce-search-results').html('');
            }
        });
    }

    /**
     * Toggle Item Selection
     */
    function toggleItem(id, slug, title) {
        const index = compareList.findIndex(item => item.id == id);

        if (index > -1) {
            // Remove
            compareList.splice(index, 1);
        } else {
            // Add
            if (compareList.length >= config.maxItems) {
                alert(`You can only compare up to ${config.maxItems} items.`);
                // Uncheck the box visually since we rejected it
                $(`.wpce-compare-trigger[data-post-id="${id}"]`).prop('checked', false);
                return;
            }
            compareList.push({ id, slug, title });
        }

        saveToStorage();
        renderFloatingBar();
        updateCheckboxStates();
    }

    /**
     * Add Item (from Modal)
     */
    function addItem(id, slug, title) {
        if (compareList.find(item => item.id == id)) {
            return; // Already exists
        }
        if (compareList.length >= config.maxItems) {
            alert(`Maximum ${config.maxItems} items allowed.`);
            return;
        }
        compareList.push({ id, slug, title });
        saveToStorage();
        renderFloatingBar();
        updateCheckboxStates();
    }

    /**
     * Clear All
     */
    function clearAll() {
        compareList = [];
        saveToStorage();
        renderFloatingBar();
        updateCheckboxStates();
    }

    /**
     * Update Checkbox UI based on Storage
     */
    function updateCheckboxStates() {
        $('.wpce-compare-trigger').each(function() {
            const $cb = $(this);
            const id = $cb.data('post-id');
            const exists = compareList.find(item => item.id == id);
            
            $cb.prop('checked', !!exists);
            
            const $label = $cb.closest('label');
            const $text = $label.find('.wpce-check-text');
            
            if (exists) {
                $label.addClass('wpce-selected');
                if ($text.length) $text.text('Compared');
            } else {
                $label.removeClass('wpce-selected');
                if ($text.length) $text.text('Compare');
            }
        });
    }

    /**
     * Render Floating Bar
     */
    function renderFloatingBar() {
        if (!$floatingBar) {
            createFloatingBarDOM();
        }

        const count = compareList.length;
        const $bar = $floatingBar;
        const $countBadge = $bar.find('.wpce-count');
        const $btnCompare = $bar.find('.wpce-btn-compare');
        const $listPreview = $bar.find('.wpce-bar-list');

        $countBadge.text(count);

        // Update Preview List (Thumbnails/Titles)
        $listPreview.empty();
        compareList.forEach(item => {
            $listPreview.append(`
                <div class="wpce-bar-item">
                    <span class="wpce-bar-item-title">${item.title}</span>
                    <button class="wpce-remove-item" data-id="${item.id}">&times;</button>
                </div>
            `);
        });

        // Handle Remove from Bar
        $bar.find('.wpce-remove-item').off('click').on('click', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            toggleItem(id, null, null); // Will remove based on ID
        });

        // Visibility Logic
        if (count >= config.minItems) {
            $bar.addClass('wpce-bar-visible');
            $btnCompare.prop('disabled', false);
        } else {
            $bar.removeClass('wpce-bar-visible');
            $btnCompare.prop('disabled', true);
        }
        
        // If only 1 item, hide compare button text but keep bar? 
        // Requirement: "If only one item selected: Hide compare button"
        if (count === 1) {
             $btnCompare.hide();
        } else {
             $btnCompare.show();
        }
    }

    /**
     * Create Floating Bar DOM if not exists
     */
    function createFloatingBarDOM() {
        const html = `
            <div id="wpce-floating-bar" class="wpce-floating-bar">
                <div class="wpce-bar-container">
                    <div class="wpce-bar-info">
                        <span class="wpce-label">Compare:</span>
                        <div class="wpce-bar-list"></div>
                        <span class="wpce-count-badge"><span class="wpce-count">0</span> / ${config.maxItems}</span>
                    </div>
                    <div class="wpce-bar-actions">
                        <button class="wpce-btn-search" aria-label="Search Items">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </button>
                        <button class="wpce-btn-clear">Clear All</button>
                        <button class="wpce-btn-compare" disabled>Compare Now</button>
                    </div>
                </div>
            </div>
        `;
        $body.append(html);
        $floatingBar = $('#wpce-floating-bar');
    }

    /**
     * Navigate to Compare Page
     */
    function goToComparePage() {
        if (compareList.length < config.minItems) return;

        const slugs = compareList.map(item => item.slug).join('-vs-');
        const url = `/${config.compareSlug}/${slugs}`;
        window.location.href = url;
    }

    /**
     * Search Modal Logic
     */
    function openSearchModal() {
        if (!$searchModal) {
            createSearchModalDOM();
        }
        $searchModal.addClass('wpce-modal-open');
        $('body').css('overflow', 'hidden');
        setTimeout(() => $searchModal.find('.wpce-search-input').focus(), 100);
    }

    function closeSearchModal() {
        if ($searchModal) {
            $searchModal.removeClass('wpce-modal-open');
            $('body').css('overflow', '');
        }
    }

    function createSearchModalDOM() {
        const html = `
            <div class="wpce-modal-overlay">
                <div class="wpce-search-modal">
                    <div class="wpce-modal-header">
                        <h3>Find Items to Compare</h3>
                        <button class="wpce-modal-close">&times;</button>
                    </div>
                    <div class="wpce-modal-body">
                        <input type="text" class="wpce-search-input" placeholder="Type to search (e.g. iPhone, Samsung)...">
                        <div class="wpce-search-results"></div>
                    </div>
                </div>
            </div>
        `;
        $body.append(html);
        $searchModal = $('.wpce-modal-overlay');
    }

    /**
     * Perform AJAX Search
     */
    function performSearch(query) {
        const $results = $('.wpce-search-results');
        $results.html('<div class="wpce-loading">Searching...</div>');

        $.ajax({
            url: config.restUrl + 'search',
            method: 'POST',
            data: {
                query: query,
                // Optionally send post_type if you want to restrict search
            },
            headers: {
                'X-WP-Nonce': config.nonce
            },
            success: function(response) {
                $results.empty();
                if (response.length === 0) {
                    $results.html('<div class="wpce-no-results">No items found.</div>');
                    return;
                }

                const listHtml = response.map(item => `
                    <div class="wpce-search-result-item" 
                         data-id="${item.id}" 
                         data-slug="${item.slug}" 
                         data-title="${item.title}">
                        <div class="wpce-search-thumb">
                            ${item.thumbnail ? `<img src="${item.thumbnail}" alt="">` : '<div class="wpce-no-thumb"></div>'}
                        </div>
                        <div class="wpce-search-info">
                            <div class="wpce-search-title">${item.title}</div>
                            <div class="wpce-search-type">${item.post_type}</div>
                        </div>
                    </div>
                `).join('');

                $results.html(listHtml);
            },
            error: function(xhr) {
                $results.html('<div class="wpce-error">Search failed. Please try again.</div>');
                console.error(xhr);
            }
        });
    }

    // Run on Ready
    $(document).ready(init);

})(jQuery);
