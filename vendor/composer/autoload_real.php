<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit7f2aa2e643d29ca4571f1dc7a37f32dc
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInit7f2aa2e643d29ca4571f1dc7a37f32dc', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit7f2aa2e643d29ca4571f1dc7a37f32dc', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit7f2aa2e643d29ca4571f1dc7a37f32dc::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}