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

/**
 * Fourni un chargeur pour les fichiers qui ne sont pas des classes dans un namespace.
 * Fonctionne avec les Helpers, Views, etc.
 *
 * @credit 		<a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Autoloader\FileLocator</a>
 */
class Locator implements LocatorInterface
{
    /**
     * Autoloader a utiliser.
     */
    protected Autoloader $autoloader;

    /**
     * Liste des noms de classe qui n'existent pas.
     *
     * @var list<class-string>
     */
    private array $invalidClassnames = [];

    public function __construct(Autoloader $autoloader)
    {
        $this->setAutoloader($autoloader);
    }

    public function setAutoloader(Autoloader $autoloader): self
    {
        $this->autoloader = $autoloader;

        return $this;
    }

    /**
     * Tente de localiser un fichier en examinant le nom d'un espace de noms
     * et en parcourant les fichiers d'espace de noms PSR-4 que nous connaissons.
     *
     * @param string      $file   Le fichier d'espace de noms à localiser
     * @param string|null $folder Le dossier dans l'espace de noms où nous devons rechercher le fichier.
     * @param string      $ext    L'extension de fichier que le fichier doit avoir.
     *
     * @return false|string Le chemin d'accès au fichier, ou false s'il n'est pas trouvé.
     */
    public function locateFile(string $file, ?string $folder = null, string $ext = 'php')
    {
        $file = $this->ensureExt($file, $ext);

        // Efface le nom du dossier s'il se trouve au début du nom de fichier
        if (! empty($folder) && str_starts_with($file, $folder)) {
            $file = substr($file, strlen($folder . '/'));
        }

        // N'est-il pas namespaced ? Essayez le dossier d'application.
        if (! str_contains($file, '\\')) {
            return $this->legacyLocate($file, $folder);
        }

        // Standardize slashes to handle nested directories.
        $file = strtr($file, '/', '\\');
        $file = ltrim($file, '\\');

        $segments = explode('\\', $file);

        // Le premier segment sera vide si une barre oblique commence le nom du fichier.
        if ($segments[0] === '') {
            unset($segments[0]);
        }

        $paths    = [];
        $filename = '';

        // Les espaces de noms sont toujours accompagnés de tableaux de chemins
        $namespaces = $this->autoloader->getNamespace();
        $keys       = array_keys($namespaces);
        sort($keys);

        foreach ($keys as $namespace) {
            if (substr($file, 0, strlen($namespace) + 1) === $namespace . '\\') {
                $fileWithoutNamespace = substr($file, strlen($namespace));

                // Il peut y avoir des sous-espaces de noms du même fournisseur,
                // donc écrasez-les avec des espaces de noms trouvés plus tard.
                $paths    = $namespaces[$namespace];
                $filename = ltrim(str_replace('\\', '/', $fileWithoutNamespace), '/');
            }
        }

        // si aucun espace de noms ne correspond, quittez
        if ($paths === []) {
            return false;
        }

        // Vérifier chaque chemin dans l'espace de noms
        foreach ($paths as $path) {
            // Assurez-vous que la barre oblique finale
            $path = rtrim($path, '/') . '/';

            // Si nous avons un nom de dossier, la fonction appelante s'attend à ce que ce fichier se trouve
            // dans ce dossier, comme "Views" ou "Librairies".
            if (! empty($folder) && ! str_contains($path . $filename, '/' . $folder . '/')) {
                $path .= trim($folder, '/') . '/';
            }

            $path .= $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Scane les namespace definis, retourne une liste de tous les fichiers
     * contenant la sous partie specifiee par $path.
     *
     * @return string[] Liste des fichiers du chemins
     */
    public function listFiles(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $files = [];

        foreach ($this->getNamespaces() as $namespace) {
            $fullPath = $namespace['path'] . $path;
            $fullPath = realpath($fullPath) ?: $fullPath;

            if (! is_dir($fullPath)) {
                continue;
            }

            $tempFiles = Helper::getFilenames($fullPath, true, false, false);

            if ($tempFiles !== []) {
                $files = array_merge($files, $tempFiles);
            }
        }

        return $files;
    }

    /**
     * Analyse l'espace de noms fourni, renvoyant une liste de tous les fichiers
     * contenus dans le sous-chemin spécifié par $path.
     *
     * @return string[] Liste des chemins des fichiers
     */
    public function listNamespaceFiles(string $prefix, string $path): array
    {
        if ($path === '' || $prefix === '') {
            return [];
        }

        $files = [];

        // autoloader->getNamespace($prefix) renvoie un tableau de chemins pour cet espace de noms
        foreach ($this->autoloader->getNamespace($prefix) as $namespacePath) {
            $fullPath = rtrim($namespacePath, '/') . '/' . $path;
            $fullPath = realpath($fullPath) ?: $fullPath;

            if (! is_dir($fullPath)) {
                continue;
            }

            $tempFiles = Helper::getFilenames($fullPath, true, false, false);

            if ($tempFiles !== []) {
                $files = array_merge($files, $tempFiles);
            }
        }

        return $files;
    }

    /**
     * Examine une fichier et retourne le FQCN.
     */
    public function getClassname(string $file): string
    {
        if (is_dir($file)) {
            return '';
        }
        
        $php       = file_get_contents($file);
        $tokens    = token_get_all($php);
        $dlm       = false;
        $namespace = '';
        $className = '';

        foreach ($tokens as $i => $token) {
            if ($i < 2) {
                continue;
            }

            if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] === 'phpnamespace' || $tokens[$i - 2][1] === 'namespace')) || ($dlm && $tokens[$i - 1][0] === T_NS_SEPARATOR && $token[0] === T_STRING)) {
                if (! $dlm) {
                    $namespace = 0;
                }
                if (isset($token[1])) {
                    $namespace = $namespace ? $namespace . '\\' . $token[1] : $token[1];
                    $dlm       = true;
                }
            } elseif ($dlm && ($token[0] !== T_NS_SEPARATOR) && ($token[0] !== T_STRING)) {
                $dlm = false;
            }

            if (($tokens[$i - 2][0] === T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] === 'phpclass'))
                && $tokens[$i - 1][0] === T_WHITESPACE
                && $token[0] === T_STRING) {
                $className = $token[1];
                break;
            }
        }

        if ($className === '') {
            return '';
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Recherchez le nom qualifié d'un fichier en fonction de l'espace de noms du premier chemin d'espace de noms correspondant.
     *
     * @return false|string Le nom qualifié ou false si le chemin n'est pas trouvé
     */
    public function findQualifiedNameFromPath(string $path)
    {
        $path = realpath($path) ?: $path;

        if (! is_file($path)) {
            return false;
        }

        foreach ($this->getNamespaces() as $namespace) {
            $namespace['path'] = realpath($namespace['path']) ?: $namespace['path'];

            if ($namespace['path'] === '') {
                continue;
            }

            if (mb_strpos($path, $namespace['path']) === 0) {
                $className = $namespace['prefix'] . '\\' .
                    ltrim(
                        str_replace(
                            '/',
                            '\\',
                            mb_substr($path, mb_strlen($namespace['path']))
                        ),
                        '\\'
                    );

                // Retirons l'extension du fichier (.php)
                $className = mb_substr($className, 0, -4);

                if (in_array($className, $this->invalidClassnames, true)) {
                    continue;
                }

                // Verifions si la classe existe
                if (class_exists($className)) {
                    return $className;
                }

                // Si la classe n'existe pas, il s'agit d'un nom de classe non valide.
                $this->invalidClassnames[] = $className;
            }
        }

        return false;
    }

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
    public function search(string $path, string $ext = 'php', bool $prioritizeApp = true): array
    {
        $path = $this->ensureExt($path, $ext);

        $foundPaths = [];
        $appPaths   = [];

        foreach ($this->getNamespaces() as $namespace) {
            if (isset($namespace['path']) && is_file($namespace['path'] . $path)) {
                $fullPath = $namespace['path'] . $path;
                $fullPath = realpath($fullPath) ?: $fullPath;

                if ($prioritizeApp) {
                    $foundPaths[] = $fullPath;
                } elseif (defined('APP_PATH') && str_starts_with($fullPath, constant('APP_PATH'))) {
                    $appPaths[] = $fullPath;
                } else {
                    $foundPaths[] = $fullPath;
                }
            }
        }

        if (! $prioritizeApp && $appPaths !== []) {
            $foundPaths = [...$foundPaths, ...$appPaths];
        }

        // Supprimer tous les doublons
        return array_values(array_unique($foundPaths));
    }

    /**
     * Retourne les namespace mappees qu'on connait
     *
     * @return array<int, array<string, string>>
     */
    protected function getNamespaces(): array
    {
        $namespaces = [];

        $system = [];

        foreach ($this->autoloader->getNamespace() as $prefix => $paths) {
            foreach ($paths as $path) {
                if ($prefix === 'BlitzPHP') {
                    $system[] = [
                        'prefix' => $prefix,
                        'path'   => rtrim($path, '\\/') . DIRECTORY_SEPARATOR,
                    ];

                    continue;
                }

                $namespaces[] = [
                    'prefix' => $prefix,
                    'path'   => rtrim($path, '\\/') . DIRECTORY_SEPARATOR,
                ];
            }
        }

        return array_merge($namespaces, $system);
    }

    /**
     * Vérifie le dossier de l'application pour voir si le fichier peut être trouvé.
     * Uniquement pour une utilisation avec des noms de fichiers qui n'incluent PAS d'espacement de noms.
     *
     * @return false|string Le chemin d'accès au fichier, ou false s'il n'est pas trouvé.
     */
    protected function legacyLocate(string $file, ?string $folder = null)
    {
        $path = defined('APP_PATH') ? constant('APP_PATH') : '';
        $path .= (empty($folder) ? $file : $folder . '/' . $file);
        $path = realpath($path) ?: $path;

        if (is_file($path)) {
            return $path;
        }

        return false;
    }

    /**
     * Garantit qu'une extension se trouve à la fin d'un nom de fichier
     */
    protected function ensureExt(string $path, string $ext): string
    {
        if ($ext !== '') {
            $ext = '.' . $ext;

            if (! str_ends_with($path, $ext)) {
                $path .= $ext;
            }
        }

        return $path;
    }
}
