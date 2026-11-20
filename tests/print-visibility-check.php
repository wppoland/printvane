<?php
// existing-checked: tests/product-query-check.php - it needs WordPress via wp eval-file; these are plain-php checks of mayPrint and stock.
// Run: php tests/print-visibility-check.php
// Repro for printvane PrintRoute::mayPrint and ProductData::stock. 
namespace { 
define('ABSPATH', '/');
$GLOBALS['protected'] = [];   // post ids with a password the visitor has not entered
$GLOBALS['caps'] = [];        // caps the current user holds
$GLOBALS['opt'] = [];
function apply_filters($h, $v) { return $v; }
function post_password_required($id) { return in_array($id, $GLOBALS['protected'], true); }
// Mirrors wp-includes/capabilities.php map_meta_cap 'read_post': a published post maps to 'read';
// 'edit_post' on a published product maps to edit_published_products/edit_others_products.
function current_user_can($cap, $id = 0) {
    if ('read_post' === $cap) { return in_array('read', $GLOBALS['caps'], true); }
    if ('edit_post' === $cap) { return in_array('edit_products', $GLOBALS['caps'], true); }
    return in_array($cap, $GLOBALS['caps'], true);
}
function get_option($k, $d = false) { return $GLOBALS['opt'][$k] ?? $d; }
function wc_get_low_stock_amount($p) { return 2; }
function __($s) { return $s; }
class WC_Product {
    public function __construct(public int $id, public int $parent = 0, public string $status = 'publish', public int $qty = 0, public bool $visible = true) {}
    public function get_id() { return $this->id; }
    public function get_parent_id() { return $this->parent; }
    public function get_status() { return $this->status; }
    // is_visible_core (abstract-wc-product.php:1641) checks catalog visibility and the parent STATUS only, never a password.
    public function is_visible() { return $this->visible; }
    public function get_stock_status() { return 'instock'; }
    public function get_stock_quantity() { return $this->qty; }
    public function managing_stock() { return true; }
}
}
namespace Folio\Service { class Texts { public static function get($k) { return $k; } } }
namespace {
$wt = dirname(__DIR__);
$src = file_get_contents("$wt/src/Service/PrintRoute.php");
preg_match('/private function mayPrint.*?\n    }\n/s', $src, $m);
eval('class R { ' . str_replace('private function', 'public function', $m[0]) . ' }');
$r = new R();
$fail = 0;
$GLOBALS['protected'] = [10];
$GLOBALS['caps'] = [];
$v = $r->mayPrint(new WC_Product(11, 10));
echo 'visitor prints variation of password-protected parent: ', var_export($v, true), "\n"; $fail += $v ? 1 : 0;
$GLOBALS['caps'] = ['read'];
$v = $r->mayPrint(new WC_Product(10));
echo 'logged-in customer prints password-protected product: ', var_export($v, true), "\n"; $fail += $v ? 1 : 0;
// A published variation under a draft parent: is_visible() is false, and read_post on the published variation is plain read.
$GLOBALS['protected'] = [];
$v = $r->mayPrint(new WC_Product(13, 14, 'publish', 0, false));
echo 'logged-in customer prints variation of a draft parent: ', var_export($v, true), "\n"; $fail += $v ? 1 : 0;
$GLOBALS['protected'] = [10];
$GLOBALS['caps'] = ['read', 'edit_products'];
$v = $r->mayPrint(new WC_Product(10));
echo 'shop manager prints password-protected product: ', var_export($v, true), "\n"; $fail += $v ? 0 : 1;
$GLOBALS['caps'] = []; $GLOBALS['protected'] = [];
$v = $r->mayPrint(new WC_Product(12));
echo 'visitor prints public product: ', var_export($v, true), "\n"; $fail += $v ? 0 : 1;

$src = file_get_contents("$wt/src/Document/ProductData.php");
preg_match('/private function stock.*?\n    }\n.*?private function statusLabel.*?\n    }\n/s', $src, $m);
eval('class S { ' . str_replace('private function stock', 'public function stock', str_replace('Texts::get', '\\Folio\\Service\\Texts::get', $m[0])) . ' }');
$s = new S();
// An option never saved (false) shows the amount, like core's loose switch in wc_format_stock_for_display().
foreach (['no_amount' => false, 'low_amount' => false, '' => true, 'unset' => true] as $fmt => $expectQty) {
    if ('unset' === $fmt) { unset($GLOBALS['opt']['woocommerce_stock_format']); } else { $GLOBALS['opt']['woocommerce_stock_format'] = $fmt; }
    $out = $s->stock(new WC_Product(1, 0, 'publish', 37));
    $has = str_contains($out, '37');
    echo "stock format '$fmt', qty 37: $out\n"; $fail += $has === $expectQty ? 0 : 1;
}
echo $fail ? "FAIL ($fail)\n" : "PASS\n";
exit($fail ? 1 : 0);
}
