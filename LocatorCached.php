<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Autoloader;

use BlitzPHP\Contracts\Autoloader\LocatorInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Localisateur de fichiers avec cache
 *
 * @credit 		<a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Autoloader\FileLocatorCached</a>
 */
final class LocatorCached implements LocatorInterface
{
    /**
     * Donnees mise en cach
     *
     * [method => data]
     * E.g.,
     * [
     *     'search' => [$path => $foundPaths],
     * ]
     */
    private array $cache = [];

    /**
     * Le cache est-il mis à jour ?
     */
    private bool $cacheUpdated = false;

    private string $cacheKey = 'FileLocatorCache';

    /**
     * Constructor
     */
    public function __construct(private Locator $locator, private CacheInterface $cacheHandler)
    {
        $this->loadCache();
    }

    private function loadCache(): void
    {
        $data = $this->cacheHandler->get($this->cacheKey);

        if (is_array($data)) {
            $this->cache = $data;
        }
    }

    public function __destruct()
    {
        $this->saveCache();
    }

    private function saveCache(): void
    {
        if ($this->cacheUpdated) {
            $this->cacheHandler->set($this->cacheKey, $this->cache, 3600 * 24);
        }
    }

    /**
     * Supprime les donnees de du cache
     */
    public function deleteCache(): void
    {
        $this->cacheHandler->delete($this->cacheKey);
    }

    /**
     * {@inheritDoc}
     */
    public function findQualifiedNameFromPath(string $path)
    {
        if (isset($this->cache['findQualifiedNameFromPath'][$path])) {
            return $this->cache['findQualifiedNameFromPath'][$path];
        }

        $classname = $this->locator->findQualifiedNameFromPath($path);

        $this->cache['findQualifiedNameFromPath'][$path] = $classname;
        $this->cacheUpdated                              = true;

        return $classname;
    }

    /**
     * {@inheritDoc}
     */
    public function getClassname(string $file): string
    {
        if (isset($this->cache['getClassname'][$file])) {
            return $this->cache['getClassname'][$file];
        }

        $classname = $this->locator->getClassname($file);

        $this->cache['getClassname'][$file] = $classname;
        $this->cacheUpdated                 = true;

        return $classname;
    }

    /**
     * {@inheritDoc}
     */
    public function search(string $path, string $ext = 'php', bool $prioritizeApp = true): array
    {
        if (isset($this->cache['search'][$path][$ext][$prioritizeApp])) {
            return $this->cache['search'][$path][$ext][$prioritizeApp];
        }

        $foundPaths = $this->locator->search($path, $ext, $prioritizeApp);

        $this->cache['search'][$path][$ext][$prioritizeApp] = $foundPaths;
        $this->cacheUpdated                                 = true;

        return $foundPaths;
    }

    /**
     * {@inheritDoc}
     */
    public function listFiles(string $path): array
    {
        if (isset($this->cache['listFiles'][$path])) {
            return $this->cache['listFiles'][$path];
        }

        $files = $this->locator->listFiles($path);

        $this->cache['listFiles'][$path] = $files;
        $this->cacheUpdated              = true;

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function listNamespaceFiles(string $prefix, string $path): array
    {
        if (isset($this->cache['listNamespaceFiles'][$prefix][$path])) {
            return $this->cache['listNamespaceFiles'][$prefix][$path];
        }

        $files = $this->locator->listNamespaceFiles($prefix, $path);

        $this->cache['listNamespaceFiles'][$prefix][$path] = $files;
        $this->cacheUpdated                                = true;

        return $files;
    }

    /**
     * {@inheritDoc}
     */
    public function locateFile(string $file, ?string $folder = null, string $ext = 'php'): false|string
    {
        if (isset($this->cache['locateFile'][$file][$folder][$ext])) {
            return $this->cache['locateFile'][$file][$folder][$ext];
        }

        $files = $this->locator->locateFile($file, $folder, $ext);

        $this->cache['locateFile'][$file][$folder][$ext] = $files;
        $this->cacheUpdated                              = true;

        return $files;
    }
}
