<?php
/**
 * probe_oauth2_alias.php
 *
 * Discover exactly what class name Mage::getConfig()->getModelClassName()
 * resolves 'xfeoauth2/access_token' to, and compare it to the file on disk.
 */
umask(0);
require __DIR__ . '/app/Mage.php';
Mage::app();

$alias = 'xfeoauth2/access_token';
$cfg = Mage::getConfig();

$resolved = $cfg->getModelClassName($alias);
echo "alias    : $alias\n";
echo "resolved : $resolved\n";
echo "class_exists: " . var_export(class_exists($resolved), true) . "\n";
echo "file   : ";
$rc = new ReflectionClass($resolved);
echo $rc->getFileName() . "\n";

echo "\n=== xfeoauth2 model group config ===\n";
$node = $cfg->getNode('global/models/xfeoauth2');
echo "class attr: " . var_export((string)$node->class, true) . "\n";
echo "resourceModel: " . var_export((string)$node->resourceModel, true) . "\n";
echo "rewrites: " . print_r($node->rewrite, true) . "\n";

echo "\n=== Same for sibling aliases ===\n";
foreach (array(
    'xfeoauth2/client',
    'xfeoauth2/refresh_token',
    'xfeoauth2/authorization_code',
    'xfeoauth2/social_account',
    'xfeoauth2/order_channel',
) as $a) {
    $r = $cfg->getModelClassName($a);
    printf("  %-40s -> %s  (exists=%s)\n",
        $a, $r, class_exists($r) ? 'Y' : 'N');
}

echo "\nDONE\n";