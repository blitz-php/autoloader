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

/**
 * Permet de charger des fichiers non-classes avec un espace de noms.
 * Fonctionne avec les helpers, les vues, etc.
 */
interface LocatorInterface
{
    /**
     * Tente de localiser un fichier en examinant le nom d'un espace de noms
     * et en parcourant les fichiers d'espace de noms PSR-4 que nous connaissons.
     *
     * @param string      $file   Le fichier d'espace de noms à localiser
     * @param string|null $folder Le dossier dans l'espace de noms où nous devons rechercher le fichier.
     * @param string $ext L'extension de fichier que le fichier doit avoir.
     *
     * @return false|string Le chemin d'accès au fichier, ou false s'il n'est pas trouvé.
     */
    public function locateFile(string $file, ?string $folder = null, string $ext = 'php');

    /**
     * Examine une fichier et retourne le FQCN.
     */
    public function getClassname(string $file): string;

    /**
     * Recherche dans tous les espaces de noms définis à la recherche d'un fichier.
     * Renvoie un tableau de tous les emplacements trouvés pour le fichier défini.
     *
     * Exemple:
     *
     *  $locator->search('Config/Routes.php');
     *  // Assuming PSR4 namespaces include foo and bar, might return:
     *  [
     *      'app/Modules/foo/Config/Routes.php',
     *      'app/Modules/bar/Config/Routes.php',
     *  ]
     */
    public function search(string $path, string $ext = 'php', bool $prioritizeApp = true): array;

    /**
     * Recherchez le nom qualifié d'un fichier en fonction de l'espace de noms du premier chemin d'espace de noms correspondant.
     *
     * @return false|string Le nom qualifié ou false si le chemin n'est pas trouvé
     */
    public function findQualifiedNameFromPath(string $path);

    /**
     * Scane les namespace definis, retourne une liste de tous les fichiers
     * contenant la sous partie specifiee par $path.
     *
     * @return string[] Liste des fichiers du chemins
     */
    public function listFiles(string $path): array;

    /**
     * Analyse l'espace de noms fourni, renvoyant une liste de tous les fichiers 
     * contenus dans le sous-chemin spécifié par $path.
     *
     * @return string[] Liste des chemins des fichiers
     */
    public function listNamespaceFiles(string $prefix, string $path): array;
}
