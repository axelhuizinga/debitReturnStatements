<?php
class Autoloader {
    /**
     * @type string
     */
    protected $namespacePrefix = '';

    /**
     * @type string
     */
    protected $baseDir = '';

    /**
     * Sets the namespace's prefix.
     * Only classes with this namespace will be autoloaded.
     * 
     * @param string $prefix
     * @return \Autoloader
     */
    public function setNamespacePrefix($prefix) {
        $this->namespacePrefix = $prefix;
        return $this;
    }

    /**
     * Sets the base directory, where we can find our php files.
     * 
     * @param string $dir
     * @return \Autoloader
     */
    public function setBaseDir($dir) {
        $this->baseDir = $dir;
        return $this;
    }

    /**
     * Register our autoloader.
     * 
     * @return void
     */
    public function register() {
        spl_autoload_register(function($class) {
			#echo "looking4 $class\n";
            /*if (!preg_match('/^' . preg_quote($this->namespacePrefix) . '/', $class)) {
				// Don't register a class without our namespace's prefix
				echo("{$this->namespacePrefix} $class\n");
                return;
            }*/
            
            $relativeClass = substr($class, strlen($this->namespacePrefix));

            $file = $this->baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
			#echo "loading $file\n";

            if (file_exists($file)) {
                require_once($file);
            }
        });
    }
}