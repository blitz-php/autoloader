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

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Un autoloader utilisant l'autoloading PSR4 autoloading et les classmaps traditionels.
 *
 * @credit 		<a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Autoloader\Autoloader</a>
 */
class Autoloader
{
    /**
     * Sauvegarde les namespaces comme cle et les chemins correspondants comme valeurs.
     *
     * @var array<non-empty-string, list<non-empty-string>>
     */
    protected array $prefixes = [];

    /**
     * Sauvegarde les noms de classes comme cle et les chemins correspondants comme valeurs.
     *
     * @var array<class-string, non-empty-string>
     */
    protected array $classmap = [];

    /**
     * Sauvegarde la liste des fichiers.
     *
     * @var list<non-empty-string>
     */
    protected array $files = [];

    /**
     * Constructor.
     *
     * @param list<non-empty-string> $helpers Sauvegarde la liste des helpers.
     */
    public function __construct(protected array $config = [], protected array $helpers = [])
    {
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function setHelpers(array $helpers): static
    {
        $this->helpers = $helpers;

        return $this;
    }

    /**
     * Lit dans le tableau de configuration et garde les parties valides dont on a besoin.
     */
    public function initialize(): static
    {
        $this->prefixes = [];
        $this->classmap = [];
        $this->files    = [];
        $config         = (object) array_merge([
            'psr4'     => [],
            'classmap' => [],
            'files'    => [],
        ], $this->config);

        // Nous devons avoir au moins un, au cas contraire,
        // on leve une exception pour forcer le programmeur a renseigner.
        if ($config->psr4 === [] && $config->classmap === []) {
            throw new InvalidArgumentException('Le tableau de configuration doit contenir soit la clé \'psr4\' soit la clé \'classmap\'.');
        }

        if ($config->psr4 !== []) {
            $this->addNamespace($config->psr4);
        }

        if ($config->classmap !== []) {
            $this->classmap = $config->classmap;
        }

        if ($config->files !== []) {
            $this->files = $config->files;
        }

        if (isset($config->helpers)) { // @phpstan-ignore-line
            $this->helpers = array_merge($this->helpers, (array) $config->helpers);
        }

        if (is_file(self::composerPath())) {
            $this->loadComposerAutoloader();
        }

        return $this;
    }

    private function loadComposerAutoloader(): void
    {
        /** @var ClassLoader $composer */
        $composer = include self::composerPath();

        $composer_config = $this->config['composer'] ?? [];

        // Devrions-nous également charger via les namspaces de Composer ?
        if (true === ($composer_config['discover'] ?? true)) {
            // @phpstan-ignore-next-line
            $this->loadComposerNamespaces($composer, $composer_config['packages'] ?? []);
        }

        unset($composer);
    }

    /**
     * Enregistre le chargeur avec la pile SPL autoloader.
     * Dans l'ordre suivant:
     *
     * 1. Chargement via la Classmap
     * 2. Autoloader PSR-4
     * 3. Fichiers non-classe
     */
    public function register()
    {
        // Enregistre l'autoloader pour les fichiers de notre classmap.
        spl_autoload_register($this->loadClassmap(...), true);

        // Ajoute l'autoloader PSR4.
        spl_autoload_register($this->loadClass(...), true);

        // Charge les fichiers non-class
        foreach ($this->files as $file) {
            $this->includeFile($file);
        }
    }

    /**
     * Désenregistrer le chargeur automatique.
     *
     * @internal Cette méthode est destinée aux tests.
     */
    public function unregister(): void
    {
        spl_autoload_unregister($this->loadClass(...));
        spl_autoload_unregister($this->loadClassmap(...));
    }

    /**
     * Enregistre les namespaces avec l'autoloader.
     *
     * @param array<non-empty-string, list<non-empty-string>|non-empty-string>|non-empty-string $namespace
     */
    public function addNamespace($namespace, ?string $path = null): self
    {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $namespacedPath) {
                $prefix = trim($prefix, '\\');

                if (is_array($namespacedPath)) {
                    foreach ($namespacedPath as $dir) {
                        $this->prefixes[$prefix][] = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR;
                    }

                    continue;
                }

                $this->prefixes[$prefix][] = rtrim($namespacedPath, '\\/') . DIRECTORY_SEPARATOR;
            }
        } else {
            $this->prefixes[trim($namespace, '\\')][] = rtrim($path, '\\/') . DIRECTORY_SEPARATOR;
        }

        return $this;
    }

    /**
     * Recupere les namespaces avec les prefixes en cles et les chemins en valeurs.
     *
     * Si le parametre prefix et defini, returne seulement le chemin correspondant au prefixe donnee.
     *
     * @return ($prefix is null ? array<non-empty-string, list<non-empty-string>> : list<non-empty-string>)
     */
    public function getNamespace(?string $prefix = null): array
    {
        if ($prefix === null) {
            return $this->prefixes;
        }

        return $this->prefixes[trim($prefix, '\\')] ?? [];
    }

    /**
     * Retire un seul namespace des configurations psr4.
     */
    public function removeNamespace(string $namespace): self
    {
        if (isset($this->prefixes[trim($namespace, '\\')])) {
            unset($this->prefixes[trim($namespace, '\\')]);
        }

        return $this;
    }

    /**
     * Charge une classe en utilisant le classmap disponible.
     *
     * @param class-string $clas Le nom complet (FQCN) de la classe.
     *
     * @return false|string
     */
    public function loadClassmap(string $class)
    {
        $file = $this->classmap[$class] ?? '';

        if (is_string($file) && $file !== '') {
            return $this->includeFile($file);
        }

        return false;
    }

    /**
     * Charge un fichier a partir du non de classe fourni.
     *
     * @internal Pour l'utilisation de `spl_autoload_register`.
     *
     * @param class-string $class Le nom complet (FQCN) de la calsse.
     */
    public function loadClass(string $class): void
    {
        $this->loadInNamespace($class);
    }

    /**
     * Charge les helpers
     */
    public function loadHelpers(): void
    {
        if (function_exists('helper')) {
            helper($this->helpers);
        }
    }

    /**
     * Charge un fichier a partir du non de classe fourni.
     *
     * @param class-string $class Le nom complet (FQCN) de la calsse.
     *
     * @return false|non-empty-string Le fichier correspondant en cas de succes, ou false en cas d'echec.
     */
    protected function loadInNamespace(string $class)
    {
        if (! str_contains($class, '\\')) {
            return false;
        }

        foreach ($this->prefixes as $namespace => $directories) {
            if (str_starts_with($class, $namespace)) {
                $relativeClassPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($namespace)));

                foreach ($directories as $directory) {
                    $directory = rtrim($directory, '\\/');

                    $filePath = $directory . $relativeClassPath . '.php';
                    $filename = $this->includeFile($filePath);

                    if ($filename !== false) {
                        return $filename;
                    }
                }
            }
        }

        // Aucun fichier trouve
        return false;
    }

    /**
     * La zone centrale pour inclure un fichier.
     *
     * @return false|non-empty-string Le filename en cas de succes, false si le fichier n'est pas charger
     */
    protected function includeFile(string $file)
    {
        if (is_file($file)) {
            include_once $file;

            return $file;
        }

        return false;
    }

    /**
     * @param array{only?: list<string>, exclude?: list<string>} $composerPackages
     */
    private function loadComposerNamespaces(ClassLoader $composer, array $composerPackages): void
    {
        $namespacePaths = $composer->getPrefixesPsr4();

        if (! method_exists(InstalledVersions::class, 'getAllRawData')) { // @phpstan-ignore function.alreadyNarrowedType
            throw new RuntimeException(
                'Votre version de Composer est trop ancienne.'
                . ' Veuillez mettre à jour Composer (exécutez `composer self-update`) vers la v2.0.14 ou une version ultérieure'
                . ' et supprimez votre répertoire vendor/ et lancez `composer update`.'
            );
        }

        $allData     = InstalledVersions::getAllRawData();
        $packageList = [];

        foreach ($allData as $list) {
            $packageList = array_merge($packageList, $list['versions']);
        }

        $only    = $composerPackages['only'] ?? [];
        $exclude = $composerPackages['exclude'] ?? [];
        if ($only !== [] && $exclude !== []) {
            throw new LogicException('Impossible d\'utiliser "only" et "exclude" en même temps dans "Config\autoload::composer>packages".');
        }

        // Recupere les chemins d'installation des packages pour ajouter les namespace pour la decouverte auto.
        $installPaths = [];
        if ($only !== []) {
            foreach ($packageList as $packageName => $data) {
                if (in_array($packageName, $only, true) && isset($data['install_path'])) {
                    $installPaths[] = $data['install_path'];
                }
            }
        } else {
            foreach ($packageList as $packageName => $data) {
                if (! in_array($packageName, $exclude, true) && isset($data['install_path'])) {
                    $installPaths[] = $data['install_path'];
                }
            }
        }

        $newPaths = [];

        foreach ($namespacePaths as $namespace => $srcPaths) {
            $add = false;

            foreach ($srcPaths as $path) {
                foreach ($installPaths as $installPath) {
                    if (str_starts_with($path, $installPath)) {
                        $add = true;
                        break 2;
                    }
                }
            }

            if ($add) {
                // Composer garde les namespaces avec les trailing slash. On en a pas besoin.
                $newPaths[rtrim($namespace, '\\ ')] = $srcPaths;
            }
        }

        $this->addNamespace($newPaths);
    }

    private static function composerPath(): string
    {
        if (defined('COMPOSER_PATH')) {
            return constant('COMPOSER_PATH');
        }

        $path = dirname(__DIR__, 2) . '/autoload.php';
        define('COMPOSER_PATH', $path);

        return $path;
    }
}
