<?php

declare(strict_types=1);

use Aws\Signature\SignatureV4;
use Isolated\Symfony\Component\Finder\Finder;

// You can do your own things here, e.g. collecting symbols to expose dynamically
// or files to exclude.
// However beware that this file is executed by PHP-Scoper, hence if you are using
// the PHAR it will be loaded by the PHAR. So it is highly recommended to avoid
// to auto-load any code here: it can result in a conflict or even corrupt
// the PHP-Scoper analysis.

// Example of collecting files to include in the scoped build but to not scope
// leveraging the isolated finder.
$excludedFiles = array_map(
    static fn (SplFileInfo $fileInfo) => $fileInfo->getPathName(),
    iterator_to_array(
        Finder::create()->files()->in(__DIR__ . '/vendor-bin'),
        false,
    ),
);

return [
    // The prefix configuration. If a non-null value is used, a random prefix
    // will be generated instead.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#prefix
    'prefix' => "Laralord",

    // The base output directory for the prefixed files.
    // This will be overridden by the 'output-dir' command line option if present.
    'output-dir' => null,

    // By default when running php-scoper add-prefix, it will prefix all relevant code found in the current working
    // directory. You can however define which files should be scoped by defining a collection of Finders in the
    // following configuration key.
    //
    // This configuration entry is completely ignored when using Box.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#finders-and-paths
    'finders' => [
        Finder::create()->files()->in('src'),
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc',
                'test',
                'test_old',
                'tests',
                'Tests',
                'vendor-bin',
                'build',
            ])
            ->in('vendor'),
        Finder::create()->append([
            'composer.json',
        ]),

    ],

    // List of excluded files, i.e. files for which the content will be left untouched.
    // Paths are relative to the configuration file unless if they are already absolute
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'exclude-files' => [
        'docker-compose.yaml',
        '.dockerfile',
        'vendor/symfony/polyfill-php83/Resources/stubs/*',
        "src/autoload.php",
        "vendor/illuminate/support/helpers.php",
        'vendor/phpunit/*',
        'vendor/bin/*',
        // ...$excludedFiles,
    ],

    // When scoping PHP files, there will be scenarios where some of the code being scoped indirectly references the
    // original namespace. These will include, for example, strings or string manipulations. PHP-Scoper has limited
    // support for prefixing such strings. To circumvent that, you can define patchers to manipulate the file to your
    // heart contents.
    //
    // For more see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#patchers
    'patchers' => [
        static function (string $filePath, string $prefix, string $contents): string {
            // Change the contents here.
            if ($filePath === 'src/bootstrap.php') {
                echo "patcher: $filePath \n";
                return str_replace("namespace $prefix;", '', $contents);
            }

            if ($filePath ===  'vendor/aws/aws-sdk-php/src/AwsClient.php') {
                $contents = str_replace(
                    'Aws\\\\{$service}\\\\Exception\\\\{$service}Exception',
                    $prefix . '\\\\Aws\\\\{$service}\\\\Exception\\\\{$service}Exception',
                    $contents
                );

                return $contents;
            }

            if (in_array($filePath, ['vendor/aws/aws-sdk-php/src/Sdk.php', 'vendor/aws/aws-sdk-php/src/MultiRegionClient.php'])) {
                $contents = str_replace(
                    'Aws\\\\{$namespace}',
                    $prefix . '\\\\Aws\\\\{$namespace}',
                    $contents,
                );

                return $contents;
            }

            if ($filePath == 'vendor/aws/aws-sdk-php/src/Signature/SignatureV4.php') {
                $contents = str_replace(
                    'Laralord\\\\Ymd\\\\THis\\\\Z',
                    'Ymd\\THis\\Z',
                    $contents,
                );
            }

            if ($filePath === 'src/license.php') {
                echo "patcher: $filePath \n";
                $license = file_get_contents('./LICENSE');

                $contents = str_replace('%license%', $license, $contents);
            }

            if ($filePath === 'src/CliProcessor.php') {
                $appVersion = $_ENV['APP_VERSION'] ?? $_ENV['LARALORD_VERSION'] ?? '0.1.0-local';
                echo "Application Version: $appVersion " . PHP_EOL;

                $contents = str_replace('@version@', $appVersion, $contents);
            }

            return $contents;
        },
    ],

    // List of symbols to consider internal i.e. to leave untouched.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#excluded-symbols
    'exclude-namespaces' => [
        'OpenSwoole',
        'Ymd\THis\Z',
        // 'Aws\S3\Exception',
        // 'PHPUnit',
        // 'Tests'
        // 'Symfony\Polyfill',
        // 'Acme\Foo'                     // The Acme\Foo namespace (and sub-namespaces)
        // '~^PHPUnit\\\\Framework$~',    // The whole namespace PHPUnit\Framework (but not sub-namespaces)
        // '~^$~',                        // The root namespace only
        // '',                            // Any namespace
    ],
    'exclude-classes' => [
        "Ymd\THis\Z",
        // SignatureV4::class,
        // 'ReflectionClassConstant',
    ],
    'exclude-functions' => [
        // 'mb_str_split',
        'app'
    ],
    'exclude-constants' => [
        'STDIN',
        'Ymd\THis\Z',
    ],

    // List of symbols to expose.
    //
    // For more information see: https://github.com/humbug/php-scoper/blob/master/docs/configuration.md#exposed-symbols
    'expose-global-constants' => true,
    'expose-global-classes' => true,
    'expose-global-functions' => true,
    'expose-namespaces' => [
        // 'Acme\Foo'                     // The Acme\Foo namespace (and sub-namespaces)
        // '~^PHPUnit\\\\Framework$~',    // The whole namespace PHPUnit\Framework (but not sub-namespaces)
        // '~^$~',                        // The root namespace only
        // '',                            // Any namespace
    ],
    'expose-classes' => [],
    'expose-functions' => [],
    'expose-constants' => [],
];
