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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

abstract class Helper
{
    /**
     * Obtenir les noms de fichiers
     *
     * Lit le répertoire spécifié et construit un tableau contenant les noms de fichiers.
     * Tous les sous-dossiers contenus dans le chemin spécifié sont également lus.
     *
     * @param string $sourceDir Chemin d'accès à la source
     * @param bool|null $includePath S'il faut inclure le chemin dans le nom du fichier ; false pour aucun chemin, null pour un chemin relatif, true pour un chemin complet
     * @param bool $hidden Indique s'il faut inclure les fichiers cachés (fichiers commençant par un point)
     * @param bool $includeDir Indique s'il faut inclure des répertoires
     */
    public static function getFilenames(
        string $sourceDir,
        ?bool $includePath = false,
        bool $hidden = false,
        bool $includeDir = true
    ): array {
        $files = [];

        $sourceDir = realpath($sourceDir) ?: $sourceDir;
        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        try {
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            ) as $name => $object) {
                $basename = pathinfo($name, PATHINFO_BASENAME);
                if (! $hidden && $basename[0] === '.') {
                    continue;
                }

                if ($includeDir || ! $object->isDir()) {
                    if ($includePath === false) {
                        $files[] = $basename;
                    } elseif ($includePath === null) {
                        $files[] = str_replace($sourceDir, '', $name);
                    } else {
                        $files[] = $name;
                    }
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        sort($files);

        return $files;
    }
}
