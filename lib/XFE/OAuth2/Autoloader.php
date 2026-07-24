<?php
/**
 * XFE_OAuth2 - bundled library autoloader
 *
 * Registers PSR-4 autoloader for bshaffer/oauth2-server-php.
 * Called once when the module first initializes.
 */
class XFE_OAuth2_Autoloader
{
    private static $_registered = false;

    /**
     * Register autoloaders for all bundled namespaces
     *
     * @return void
     */
    public static function register()
    {
        if (self::$_registered) {
            return;
        }
        self::$_registered = true;

        $libDir = dirname(dirname(dirname(__FILE__))); // points to lib/

        // bshaffer/oauth2-server-php namespace prefix 'OAuth2\'
        spl_autoload_register(function ($class) use ($libDir) {
            if (strncmp('OAuth2\\', $class, 7) !== 0) {
                return;
            }

            $relativeClass = substr($class, 7);
            $file = $libDir . '/OAuth2/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
