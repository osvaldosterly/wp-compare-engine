/**
 * WP Compare Engine - Frontend JavaScript
 *
 * Handles:
 * - LocalStorage management
 * - Floating bar visibility
 * - Search modal & AJAX
 * - Checkbox toggling
 */

(function() {
    'use strict';

    // Config from wp_localize_script.
    const config = window.wpceConfig || {};
    const storageKey = config.storageKey || 'wpce_compare_list';
    const maxItems = parseInt(config.maxItems, 10) || 3;
    const minItems = parseInt(config.minItems, 10) || 2;

    // State.
    let compareList = [];

    /**
     * Initialize the module.
     */
    function init() {
        loadFromStorage();
        renderFloatingBar();
        bindEvents();
        updateCheckboxes();
    }

    /**
     * Load compare list from LocalStorage.
     */
    function loadFromStorage() {
        try {
            const stored = localStorage.getItem(storageKey);
            if (stored) {
                compareList = JSON.parse(stored);
                if (!Array.isArray(compareList)) {
                    compareList = [];
                }
            }
        } catch (e) {
            compareList = [];
        }
    }

    /**
     * Save compare list to LocalStorage.
     */
    function saveToStorage() {
        localStorage.setItem(storageKey, JSON.stringify(compareList));
    }

    /**
     * Add item to compare list.
     * @param {Object} item - { slug, title, post_type, thumbnail }
     */
    function addItem(item) {
        if (compareList.length >= maxItems) {
            alert(config.strings.maxReached || 'Maximum items reached');
            return false;
        }

        // Check duplicates.
        if (compareList.some(i => i.slug === item.slug)) {
            return false;
        }

        compareList.push(item);
        saveToStorage();
        updateUI();
        return true;
    }

    /**
     * Remove item from compare list.
     * @param {string} slug - Item slug.
     */
    function removeItem(slug) {
        compareList = compareList.filter(i => i.slug !== slug);
        saveToStorage();
        updateUI();
    }

    /**
     * Clear all items.
     */
    function clearAll() {
        compareList = [];
        saveToStorage();
        updateUI();
    }

    /**
     * Update UI components.
     */
    function updateUI() {
        renderFloatingBar();
        updateCheckboxes();
    }

    /**
     * Render floating bar.
     */
    function renderFloatingBar() {
        let bar = document.getElementById('wpce-floating-bar');
        
        // Create if not exists.
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'wpce-floating-bar';
            bar.className = 'wpce-floating-bar';
            bar.innerHTML = `
                <div class="wpce-bar-container">
                    <span class="wpce-bar-text">
                        <span class="wpce-count">${compareList.length}</span> ${compareList.length === 1 ? 'item' : 'items'} selected
                    </span>
                    <div class="wpce-bar-actions">
                        <button type="button" id="wpce-btn-compare" class="wpce-btn wpce-btn-primary" disabled>
                            ${config.strings.compare || 'Compare'}
                        </button>
                        <button type="button" id="wpce-btn-clear" class="wpce-btn wpce-btn-secondary">
                            ${config.strings.clearAll || 'Clear All'}
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(bar);
        }

        const countSpan = bar.querySelector('.wpce-count');
        const textNode = bar.querySelector('.wpce-bar-text');
        const compareBtn = document.getElementById('wpce-btn-compare');
        const clearBtn = document.getElementById('wpce-btn-clear');

        if (countSpan) countSpan.textContent = compareList.length;
        if (textNode) {
            textNode.childNodes[2].textContent = ` ${compareList.length === 1 ? 'item' : 'items'} selected`;
        }

        // Show/hide based on count.
        if (compareList.length >= minItems) {
            bar.style.display = 'block';
            if (compareBtn) compareBtn.disabled = false;
        } else if (compareList.length > 0) {
            bar.style.display = 'block';
            if (compareBtn) compareBtn.disabled = true;
        } else {
            bar.style.display = 'none';
        }
    }

    /**
     * Update checkbox states on page.
     */
    function updateCheckboxes() {
        document.querySelectorAll('[data-wpce-slug]').forEach(checkbox => {
            const slug = checkbox.dataset.wpceSlug;
            const isSelected = compareList.some(i => i.slug === slug);
            
            if (checkbox.type === 'checkbox') {
                checkbox.checked = isSelected;
            }
            
            // Update button text if applicable.
            const btnText = checkbox.closest('.wpce-compare-btn-wrapper')?.querySelector('.wpce-btn-text');
            if (btnText) {
                btnText.textContent = isSelected 
                    ? (config.strings.compared || 'Compared') 
                    : (config.strings.compare || 'Compare');
            }
            
            // Add/remove class.
            checkbox.closest('.wpce-compare-btn-wrapper')?.classList.toggle('is-selected', isSelected);
        });
    }

    /**
     * Bind global events.
     */
    function bindEvents() {
        // Delegated event for compare buttons.
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-wpce-action="toggle-compare"]');
            if (!btn) return;

            e.preventDefault();
            const slug = btn.dataset.wpceSlug;
            const title = btn.dataset.wpceTitle || '';
            const postType = btn.dataset.wpcePostType || 'post';
            const thumbnail = btn.dataset.wpceThumbnail || '';

            const isSelected = compareList.some(i => i.slug === slug);

            if (isSelected) {
                removeItem(slug);
            } else {
                addItem({ slug, title, post_type: postType, thumbnail });
            }
        });

        // Floating bar actions.
        document.addEventListener('click', function(e) {
            if (e.target.id === 'wpce-btn-clear') {
                e.preventDefault();
                clearAll();
            }

            if (e.target.id === 'wpce-btn-compare' && !e.target.disabled) {
                e.preventDefault();
                navigateToCompare();
            }

            // Modal close.
            if (e.target.classList.contains('wpce-modal-close') || e.target.classList.contains('wpce-modal-overlay')) {
                closeModal();
            }
        });

        // Search modal trigger (if any element has data-wpce-open-search).
        document.addEventListener('click', function(e) {
            if (e.target.dataset.wpceOpenSearch === 'true') {
                e.preventDefault();
                openModal();
            }
        });

        // Search input.
        let searchTimeout;
        document.addEventListener('input', function(e) {
            if (e.target.id === 'wpce-search-input') {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(e.target.value);
                }, 300);
            }
        });

        // Keyboard navigation for modal.
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Navigate to compare page.
     */
    function navigateToCompare() {
        if (compareList.length < minItems) return;

        const slugs = compareList.map(i => i.slug);
        const url = `${config.restUrl.replace('/wp-json/wp-compare/v1/', '')}${config.compareSlug}/${slugs.join('-vs-')}`;
        
        // Validate via API first.
        fetch(`${config.restUrl}validate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({ slugs }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.valid && data.url) {
                window.location.href = data.url;
            } else {
                alert(data.error || 'Invalid comparison');
            }
        })
        .catch(err => console.error(err));
    }

    /**
     * Open search modal.
     */
    function openModal() {
        let modal = document.getElementById('wpce-search-modal');
        if (!modal) {
            // Create modal markup.
            modal = document.createElement('div');
            modal.id = 'wpce-search-modal';
            modal.className = 'wpce-modal';
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML = `
                <div class="wpce-modal-overlay"></div>
                <div class="wpce-modal-content" role="dialog" aria-modal="true" aria-labelledby="wpce-modal-title">
                    <header class="wpce-modal-header">
                        <h2 id="wpce-modal-title">${config.strings.searchTitle || 'Search Items'}</h2>
                        <button type="button" class="wpce-modal-close" aria-label="Close">&times;</button>
                    </header>
                    <div class="wpce-modal-body">
                        <input type="text" id="wpce-search-input" class="wpce-search-input" placeholder="${config.strings.searchPlaceholder || 'Type to search...'}" autocomplete="off" />
                        <div id="wpce-search-results" class="wpce-search-results"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('wpce-search-input').focus();
    }

    /**
     * Close search modal.
     */
    function closeModal() {
        const modal = document.getElementById('wpce-search-modal');
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Perform AJAX search.
     * @param {string} query - Search query.
     */
    function performSearch(query) {
        if (!query || query.length < 2) {
            return;
        }

        const resultsContainer = document.getElementById('wpce-search-results');
        if (!resultsContainer) return;

        resultsContainer.innerHTML = '<li class="wpce-search-item">Loading...</li>';

        fetch(`${config.restUrl}search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.results || data.results.length === 0) {
                    resultsContainer.innerHTML = `<li class="wpce-search-item">${config.strings.noResults || 'No items found.'}</li>`;
                    return;
                }

                resultsContainer.innerHTML = '';
                data.results.forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'wpce-search-item';
                    li.innerHTML = `
                        ${item.thumbnail ? `<img src="${item.thumbnail}" alt="" />` : ''}
                        <div class="wpce-search-item-info">
                            <p class="wpce-search-item-title">${escapeHtml(item.title)}</p>
                            <span class="wpce-search-item-type">${item.post_type}</span>
                        </div>
                    `;
                    li.addEventListener('click', () => {
                        addItem({
                            slug: item.slug,
                            title: item.title,
                            post_type: item.post_type,
                            thumbnail: item.thumbnail,
                        });
                        closeModal();
                    });
                    resultsContainer.appendChild(li);
                });
            })
            .catch(err => {
                console.error(err);
                resultsContainer.innerHTML = '<li class="wpce-search-item">Error loading results.</li>';
            });
    }

    /**
     * Escape HTML.
     * @param {string} str - String to escape.
     * @return {string}
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initialize on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
