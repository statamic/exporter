<?php

class Tasks_exporter extends Tasks
{
    /**
     * The Cache
     *
     * @var array
     */
    private $c;

    /**
     * The Migration
     *
     * @var array
     */
    private $migration = array();

    /**
     * Collection data
     *
     * Collection names as the keys, and order types as the values.
     *
     * @var array
     */
    private $collections;

    /**
     * Create the Migration
     *
     * @return array
     */
    public function migrate()
    {
        $this->c = $this->getCache();

        $this->collections = $this->getCollections();

        $this->createTaxonomies();
        $this->createGlobals();
        $this->createPagesAndEntries();

        return $this->migration;
    }

    /**
     * Get the cache
     *
     * @return array
     */
    private function getCache()
    {
        $cache = BASE_PATH . '/_cache/_app/content/content.php';

        $cache = File::get($cache);

        return unserialize($cache);
    }

    /**
     * Create pages and entries
     *
     * @return void
     */
    private function createPagesAndEntries()
    {
        foreach ($this->c['urls'] as $url => $data) {
            $key = $data['folder'] . ':' . $data['file'] . ':data';
            $data = $this->arrayGet($this->c['content'], $key);

            if ($this->isEntry($data)) {
                $this->addEntry($data);
            } else {
                $this->addPage($data);
            }
        }
    }

    /**
     * Create taxonomies
     *
     * @return void
     */
    private function createTaxonomies()
    {
        if (! isset($this->c['taxonomies'])) {
            $this->migration['taxonomies'] = [];
            return;
        }
        
        foreach ($this->c['taxonomies'] as $taxonomy_name => $terms) {
            $taxonomy = array();

            // Ignore empty taxonomies. They were probably just defined in
            // settings but never actually used.
            if (empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $taxonomy[] = $term['name'];
            }

            $this->migration['taxonomies'][$taxonomy_name] = $taxonomy;
        }
    }

    /**
     * Get collections
     *
     * This will look for any folders with fields.yaml
     *
     * @return array
     */
    private function getCollections()
    {
        $collections = array();

        $paths = File::globRecursively(BASE_PATH . '/' . Config::get('content_root'), 'yaml');

        foreach ($paths as $path) {
            if (! $this->endsWith($path, 'fields.yaml')) {
                continue;
            }

            $collection = substr(Path::clean(Path::trimFileSystemFromContent($path)), 1, -12);

            $contents = YAML::parseFile($path);

            $collections[$collection] = array_get($contents, 'type', 'alphabetical');
        }

        return $collections;
    }

    /**
     * Get Globals & Theme Variables
     *
     * Create global variables from v1 globals and theme variables
     *
     * @return array
     */
    private function createGlobals()
    {
        $globals = array(
            'settings' => array(),
            'global'   => array(),
            'theme'    => array(),
        );

        // Get a list of variables added to _config/settings.yaml
        // Anything not also in the defaults will be considered a global added manually.
        $defaults = array_keys(YAML::parseFile(Config::getAppConfigPath() . '/default.settings.yaml'));
        $settings = array_keys(YAML::parseFile(Config::getConfigPath() . '/settings.yaml'));
        $settings_globals = array_diff($settings, $defaults);
        foreach ($settings_globals as $setting) {
            $setting = ltrim($setting, '_');
            $globals['settings'][$setting] = Config::get($setting);
        }

        // Get a list of variables in _config/global.yaml
        $site_globals = Config::getConfigPath() . '/global.yaml';
        if (File::exists($site_globals)) {
            $globals['global'] = YAML::parse($site_globals);
        }

        // Get a list of variables in the theme.yaml
        $theme_globals = Config::getCurrentThemePath() . 'theme.yaml';
        if (File::exists($theme_globals)) {
            $globals['theme'] = YAML::parse($theme_globals);
        }

        $this->migration['globals'] = $globals;
    }

    /**
     * Is this content an entry?
     *
     * @param  array $data
     * @return boolean
     */
    private function isEntry($data)
    {
        $collections = $this->collections;
        $folder = $data['_folder'];

        // Firstly, if we're not in a collection folder, its safe to say it's a page.
        if (! in_array($folder, array_keys($collections))) {
            return false;
        }

        $type = $collections[$folder];

        // We're in a date collection folder but there's no entry date? It's a page.
        if ($type === 'date' && !$data['datestamp']) {
            return false;
        }

        return true;
    }

    /**
     * Add an entry to the migration
     *
     * @param array $data
     */
    private function addEntry($data)
    {
        $url = $data['url'];

        $folder = $data['_folder'];

        $collections = $this->getCollections();
        $type = $collections[$folder];

        if ($type === 'date') {
            $date_regex = '/^(.*)[\.-]' . $data['slug'] . '$/';
            preg_match($date_regex, $data['_filename'], $date_matches);
            $order = $date_matches[1];
        } elseif ($type === 'number') {
            $order = (int) $data['_order_key'];
        } else {
            $order = null;
        }

        $data = $this->createData($data);

        $this->migration['collections'][$folder][$url] = compact('data', 'order');
    }

    /**
     * Add a page to the migration
     *
     * @param array $data
     */
    private function addPage($data)
    {
        $url = $data['url'];

        $order = (is_numeric($data['_order_key'])) ? (int) $data['_order_key'] : null;

        $data = $this->createData($data);

        $this->migration['pages'][$url] = compact('data', 'order');
    }

    /**
     * Create the data for a piece of content
     *
     * @param  array $data  The cache data
     * @return array        The data for the content to be added to the migration
     */
    private function createData($data)
    {
        $file = $data['_file'];

        // Get and parse the file for YAML and content
        $content       = substr(File::get($file), 3);
        $divide        = strpos($content, "\n---");
        $front_matter  = trim(substr($content, 0, $divide));
        $content_raw   = trim(substr($content, $divide + 4));
        $yaml          = YAML::parse($front_matter);

        // We want the content in `content`, please.
        $yaml['content'] = $content_raw;

        $yaml = $this->removeUnderscores($yaml);

        return $yaml;
    }

    /**
     * Remove underscores from the beginning of keys
     *
     * For example, `_template` becomes `template`
     *
     * @param  array $yaml  The array to clean
     * @return array        The cleaned array
     */
    private function removeUnderscores($yaml)
    {
        $data = array();

        foreach ($yaml as $key => $value) {
            if (substr($key, 0, 1) === '_') {
                unset($yaml[$key]);
                $key = substr($key, 1);
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Get an item from an array using "colon" notation.
     *
     * Ported from Illuminate\Support\Arr and modified to use colons
     * since we're dealing with keys already containing periods.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    private function arrayGet($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode(':', $key) as $segment) {
            if (! is_array($array) || ! array_key_exists($segment, $array)) {
                return $this->value($default);
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Required by arrayGet
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Does a $string end with a given $test?
     *
     * @param  string $str
     * @param  string $test
     * @return boolean
     */
    private function endsWith($string, $test)
    {
        return substr_compare($string, $test, strlen($string)-strlen($test), strlen($test)) === 0;
    }
}
