<?php
/**
 * Uninstall cleanup for Folio.
 *
 * Removes the plugin's own options when it is deleted from wp-admin. Nothing this
 * plugin writes belongs to the shop's content: it stores settings and nothing
 * else, and WooCommerce data is never touched.
 *
 * @package Folio
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Remove this plugin's options from the current site.
 */
function folio_uninstall_site(): void
{
    delete_option('folio_settings');
    delete_option('folio_db_version');
}

// Options are per site, so a network uninstall has to visit every site.
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $folio_site_id) {
        switch_to_blog((int) $folio_site_id);
        folio_uninstall_site();
        restore_current_blog();
    }
    unset($folio_site_id);
} else {
    folio_uninstall_site();
}
