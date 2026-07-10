# WP Compare Engine

A production-ready WordPress comparison engine similar to Versus.com. Built for sites using **ACF Pro**, **GeneratePress**, and **GenerateBlocks**.

## Features

- ✅ **SEO-Friendly URLs**: `/compare/iphone-16-vs-galaxy-s25`
- ✅ **ACF Integration**: Dynamically compares all common ACF fields
- ✅ **No Custom Tables**: Uses existing WordPress posts and meta
- ✅ **GenerateBlocks Compatible**: Design your compare page with GenerateBlocks
- ✅ **LocalStorage**: Persistent compare list across browsing
- ✅ **Floating Bar**: Sticky comparison bar with search modal
- ✅ **Responsive**: Mobile-friendly tables and card layouts
- ✅ **Accessible**: WCAG AA compliant, keyboard navigation
- ✅ **Extensible**: Hooks and filters for customization

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Advanced Custom Fields (ACF) Pro
- GeneratePress Theme (recommended)
- GenerateBlocks (recommended)

## Installation

1. Upload the `wp-compare-engine` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress Admin > Plugins
3. Go to **Settings > Compare Engine** to configure
4. Visit **Settings > Permalinks** and click "Save Changes" to flush rewrite rules

## Setup

### 1. Create Compare Page

Create a new WordPress Page:
- **Title**: Compare
- **Slug**: `compare` (or your custom slug from settings)
- **Content**: `[wp_compare_table]`

You can now design this page using **GenerateBlocks** around the shortcode.

### 2. Add Compare Checkboxes to Archives

Add this HTML to your archive templates (via GenerateBlocks HTML block or theme files):

```html
<div class="wpce-compare-btn-wrapper">
    <button type="button" 
            data-wpce-action="toggle-compare" 
            data-wpce-slug="<?php echo get_post_field('post_name'); ?>" 
            data-wpce-title="<?php echo esc_attr(get_the_title()); ?>" 
            data-wpce-post-type="<?php echo get_post_type(); ?>"
            data-wpce-thumbnail="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'); ?>"
            class="wpce-compare-toggle">
        <span class="wpce-btn-text">Compare</span>
    </button>
</div>
```

Or use a checkbox:

```html
<input type="checkbox" 
       data-wpce-slug="<?php echo get_post_field('post_name'); ?>" 
       class="wpce-compare-checkbox" />
```

### 3. Configure Settings

Go to **Settings > Compare Engine**:
- Set base slug (default: `compare`)
- Configure min/max items (default: 2-3)
- Select allowed post types
- Enable/disable features

## URL Structure

Comparison URLs follow this pattern:

```
example.com/compare/slug-a-vs-slug-b
example.com/compare/slug-a-vs-slug-b-vs-slug-c
```

The plugin automatically:
- Parses slugs from the URL
- Validates item count
- Returns 404 for invalid comparisons
- Generates SEO titles and Schema.org markup

## Supported ACF Field Types

| Type | Rendering |
|------|-----------|
| Text, Number, Email, URL | Plain text |
| Textarea | Formatted text |
| True/False | ✔ Yes / ✖ No |
| Image | Thumbnail with lightbox link |
| Gallery | Grid of thumbnails |
| Select, Radio, Checkbox | Comma-separated values |
| Relationship, Post Object | Linked post titles |
| Taxonomy | Term names |
| Repeater | Bullet list |
| Group | Nested rows with group heading |
| Link | Clickable anchor |
| Google Map | Address text |
| Date, Time, DateTime | Formatted date/time |
| Color Picker | Color swatch |
| Range | Numeric value |

## Hooks & Filters

### Actions

```php
// Before compare header
do_action( 'wpce_before_compare_header', $posts );

// After compare header
do_action( 'wpce_after_compare_header', $posts );

// After compare table
do_action( 'wpce_after_compare_table', $posts );
```

### Filters

```php
// Modify field value before rendering
apply_filters( 'wpce_field_value', $value, $field_key, $post_id );

// Modify rendered field HTML
apply_filters( 'wpce_rendered_field', $output, $value, $type, $field_key, $post_id );

// Modify row CSS class
apply_filters( 'wpce_row_class', $row_class, $field_key, $posts );
```

## Theme Override

Override plugin templates by copying to your theme:

```
yourtheme/
└── wp-compare/
    ├── compare.php          # Main compare page template
    └── components/
        ├── table-main.php   # Shortcode table output
        └── selector-bar.php # Floating bar markup
```

## REST API

### Search Endpoint

```
GET /wp-json/wp-compare/v1/search?q=query&post_type=post
```

Response:
```json
{
  "results": [
    {
      "id": 123,
      "title": "iPhone 16",
      "slug": "iphone-16",
      "post_type": "product",
      "permalink": "...",
      "thumbnail": "..."
    }
  ]
}
```

### Validate Endpoint

```
POST /wp-json/wp-compare/v1/validate
Content-Type: application/json

{ "slugs": ["iphone-16", "galaxy-s25"] }
```

## Performance

- ACF field definitions cached per request
- Lazy loading images
- Minimal database queries
- No duplicate data storage
- Vanilla JavaScript (no jQuery)

## Accessibility

- Keyboard navigation support
- ARIA labels on interactive elements
- Focus management in modals
- Screen reader friendly
- High contrast colors

## Security

- Nonce verification for AJAX
- Capability checks in admin
- Escaped output everywhere
- Sanitized inputs
- Prepared statements where applicable

## License

GPL v2 or later

## Support

For issues and feature requests, please visit the GitHub repository.
