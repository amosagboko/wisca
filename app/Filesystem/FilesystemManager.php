<?php

namespace App\Filesystem;

use Illuminate\Filesystem\FilesystemManager as BaseFilesystemManager;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter as FlysystemLocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;

class FilesystemManager extends BaseFilesystemManager
{
    /**
     * Create an instance of the local driver.
     *
     * Some shared hosts disable PHP's fileinfo extension, which breaks Flysystem's
     * default FinfoMimeTypeDetector. Fall back to extension-based detection instead.
     *
     * @param  array<string, mixed>  $config
     */
    public function createLocalDriver(array $config, string $name = 'local')
    {
        $visibility = PortableVisibilityConverter::fromArray(
            $config['permissions'] ?? [],
            $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE
        );

        $links = ($config['links'] ?? null) === 'skip'
            ? FlysystemLocalAdapter::SKIP_LINKS
            : FlysystemLocalAdapter::DISALLOW_LINKS;

        $mimeTypeDetector = class_exists(\finfo::class)
            ? null
            : new ExtensionMimeTypeDetector();

        $adapter = new FlysystemLocalAdapter(
            $config['root'],
            $visibility,
            $config['lock'] ?? LOCK_EX,
            $links,
            $mimeTypeDetector,
        );

        return (new LocalFilesystemAdapter(
            $this->createFlysystem($adapter, $config), $adapter, $config
        ))->diskName(
            $name
        )->shouldServeSignedUrls(
            $config['serve'] ?? false,
            fn () => $this->app['url'],
        );
    }
}
