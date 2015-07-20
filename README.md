# Ionata's Revisions Cleaner

A plugin for WordPress. Adds an individual new action to each post on the listing pages, alongside Edit, Trash, View, etc.

__NOTE:__ For mass-revision-removal there are better plugins available.

## Installation

* Install and activate the plugin.

## Configuration

By default, with a regular WordPress configuration, the plugin allows for all
the revisions of any post type to be removed, but these can be configured in
your code - either in `wp-config.php`, some plugin or the theme's
`functions.php`.

### Post type exceptions

You can add specific post types that this plugin will be enabled for:

```
global $revisions_cleaner;
$revisions_cleaner = array(
  'allowed_post_types' => 'page' # or array('page', 'post')
);
```

### Revisions to keep

You can specify how many revisions to keep within your `wp-config.php`:

```
define( 'WP_POST_REVISIONS', 3 );
```

Setting this constant to zero will disable WordPress' revision control system,
but won't disable the plugin - it will be able to delete any remaining revisions.

__NOTE:__ If you set this to zero, then once no posts have any revisions, it is
best advised to disable and uninstall the plugin.

<small>v1.0.0</small>