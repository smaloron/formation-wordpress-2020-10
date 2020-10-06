# Regen. Thumbs

Regen. Thumbs - regenerate Worpdpress post thumbnails per post in one click!

This plugin adds a button to the WordPress post edit screen to regenerate all the thumbnails associated to the post using ajax.  
Compatible with Woocommerce.

### Installation
Upload the plugin files to the `/wp-content/plugins/regen-thumbs` directory of your WordPress installation.

## Hooks - filters

Regen. Thumbs gives developers the possibilty to debug the plugin using a filter (use un-minified javascript).
___ 

```php
apply_filters( 'regen_thumbs_debug', bool $debug );
```

**Description**  
Filter wether to activate debug mode (use un-minified javascript).  

**Parameters**  
$debug
> (bool) true if debug mode is activated, false otherwise - default false
___ 
