<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit8035b29386120d424aa337a0a4a67a9a
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'FuerteWpDep\\Carbon_Fields\\' => 26,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'FuerteWpDep\\Carbon_Fields\\' => 
        array (
            0 => __DIR__ . '/..' . '/htmlburger/carbon-fields/core',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit8035b29386120d424aa337a0a4a67a9a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit8035b29386120d424aa337a0a4a67a9a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit8035b29386120d424aa337a0a4a67a9a::$classMap;

        }, null, ClassLoader::class);
    }
}
