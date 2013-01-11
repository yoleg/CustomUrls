<?php
/**
 * @package: Transporter
 * @author: Oleg Pryadko <oleg@websitezen.com>
 * @createdon: 8/22/12
 * @license: GPL v.3 or later
 */
class Transporter {
    /** @var modX A reference to the modX object. */
    public $modx = null;
    /** @var array A collection of properties to adjust behaviour. */
    public $config = array();
    /** @var array */
    public $sources = array();
    /** @var array */
    public $has = array();
    /** @var array */
    public $attributes = array();
    /** @var array */
    public $resolvers = array();
    /** @var modPackageBuilder */
    public $builder = null;
    /** @var array */
    public $default_attributes = array();
    /** @var array */
    public $object_attributes = array();
    /** @var array */
    public $category_attributes = array();
    /** @var modCategory */
    public $category = null;
    /** @var array */
    public $files_config = array();
    /** @var TransportDataProcessor */
    public $processor = null;

    function __construct(modX &$modx, array $config=array() ) {
        $this->modx =& $modx;
        $this->config = $config;
        $this->_initialize();
    }
    public function setConfig(array $config = array()) {
        $this->default_attributes = $this->arrayMergeRecursive(array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ), $config['default_attributes']);
        $default_attr = $this->default_attributes;
        $this->object_attributes = $this->arrayMergeRecursive(array(
            'Resources' => array(
                'attributes' => array_merge($default_attr, array(
                    xPDOTransport::UNIQUE_KEY => 'pagetitle',
                    xPDOTransport::RELATED_OBJECTS => true,
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                        'ContentType' => $default_attr,
                    ),
                )),
            ),
            'Menus' => array(
                'file' => 'menu',
                'attributes' => array_merge($default_attr, array(
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UNIQUE_KEY => 'text',
                    xPDOTransport::RELATED_OBJECTS => true,
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                        'Action' => array_merge($default_attr, array(
                            xPDOTransport::UNIQUE_KEY => array('namespace', 'controller'),
                        )),
                    ),
                )),
            ),
            'Settings' => array(
                'attributes' => array(
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => false,
                    xPDOTransport::UNIQUE_KEY => 'key',
                ),
            ),
            'PolicyTemplates' => array(
                'attributes' => array_merge($default_attr, array(
                    xPDOTransport::RELATED_OBJECTS => true,
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                        'Permissions' => array_merge($default_attr, array(
                            xPDOTransport::UNIQUE_KEY => array('template', 'name'),
                        )),
                    )
                )),
            ),
            'AccessPolicies' => array(
                'file' => 'policies',
                'attributes' => $default_attr,
            ),
            'Plugins' => array(
                'attributes' => array_merge($default_attr, array(
                    xPDOTransport::RELATED_OBJECTS => true,
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                        'PluginEvents' => array_merge($default_attr, array(
                            xPDOTransport::PRESERVE_KEYS => true,
                            xPDOTransport::UPDATE_OBJECT => false,
                            xPDOTransport::UNIQUE_KEY => array('pluginid', 'event'),
                        )),
                    ),
                )),
                'category_attributes' => $default_attr,
            ),
            'Snippets' => array(
                'category' => 'Snippets',
                'category_attributes' => $default_attr,
            ),
            'Chunks' => array(
                'category' => 'Chunks',
                'category_attributes' => array_merge($default_attr, array(
                    xPDOTransport::UPDATE_OBJECT => false,
                )),
            ),
            'Templates' => array(
                'category' => 'Templates',
                'category_attributes' => array_merge($default_attr, array(
                    xPDOTransport::UNIQUE_KEY => 'templatename',
                )),
            ),
            'TemplateVars' => array(
                'file' => 'tvs',
                'category' => 'TemplateVars',
                'category_attributes' => $default_attr,
            ),
            'PropertySets' => array(
                'category' => 'PropertySets',
                'category_attributes' => $default_attr,
            ),

        ), $config['object_attributes']);
        $this->category_attributes = $this->arrayMergeRecursive(array(
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::RELATED_OBJECTS => true,
        ), $config['category_attributes']);
        $root = $config['sources']['root'];
        $this->sources = array_merge(array(
            'root' => $root,
            'build' => $root . '_build/',
            'resolvers' => $root . '_build/resolvers/',
            'data' => $root . '_build/data/',
            'events' => $root . '_build/data/events/',
            'permissions' => $root . '_build/data/permissions/',
            'properties' => $root . '_build/data/properties/',
            'validators' => $root . '_build/validators/',
            'install_options' => $root . '_build/install.options/',
            'source_packages' => $root . 'core/packages/',
            'source_core' => $root . 'core/components/' . PKG_NAME_LOWER,
            'source_assets' => $root . 'assets/components/' . PKG_NAME_LOWER,
            'chunks' => $root . 'core/components/' . PKG_NAME_LOWER . '/elements/chunks/',
            'plugins' => $root . 'core/components/' . PKG_NAME_LOWER . '/elements/plugins/',
            'snippets' => $root . 'core/components/' . PKG_NAME_LOWER . '/elements/snippets/',
            'lexicon' => $root . 'core/components/' . PKG_NAME_LOWER . '/lexicon/',
            'docs' => $root . 'core/components/' . PKG_NAME_LOWER . '/docs/',
            'model' => $root . 'core/components/' . PKG_NAME_LOWER . '/model/',
        ), $config['sources']);
        $this->has = $config['has'];
        $this->attributes = array_merge(array(
            'license' => $this->getSnippetContent($this->sources['docs'] . 'license.txt'),
            'readme' => $this->getSnippetContent($this->sources['docs'] . 'readme.txt'),
            'changelog' => $this->getSnippetContent($this->sources['docs'] . 'changelog.txt'),
        ), $config['attributes']);
        $this->resolvers = $config['resolvers'];
        $this->files_config = $this->arrayMergeRecursive(array(
            'Core' => array(
                'source' => $this->sources['source_core'],
                'target' => "return MODX_CORE_PATH . 'components/';",
            ),
            'Assets' => array(
                'source' => $this->sources['source_assets'],
                'target' => "return MODX_ASSETS_PATH . 'components/';",
            ),
            // todo-important: copies entire packages directory into backup! Not good.
            'Packages' => array(
                'source' => $this->sources['source_packages'],
                'target' => "return MODX_CORE_PATH . '';",
            ),
        ),$config['files_config']);

    }
    public function _initialize() {
        $modx = $this->modx;
        set_time_limit(0);
        $modx->initialize('mgr');
        $modx->setLogLevel(modX::LOG_LEVEL_INFO);
        $modx->setLogTarget('ECHO');
        echo '<pre>';
        /* load package builder */
        $modx->loadClass('transport.modPackageBuilder', '', false, true);
        $this->builder = new modPackageBuilder($modx);
        $this->builder->createPackage(PKG_NAME_LOWER, PKG_VERSION, PKG_RELEASE);
        $this->builder->registerNamespace(PKG_NAME_LOWER, false, true, '{core_path}components/' . PKG_NAME_LOWER . '/');
        $modx->getService('lexicon', 'modLexicon');
        $modx->lexicon->load('customurls:properties');
    }

    public function run() {
        $modx = $this->modx;
        /* create new category w/ package name - required */
        /** @var $category modCategory */
        $category = $modx->newObject('modCategory');
        $category->set('id', 1);
        $category->set('category', PKG_CATEGORY);
        $this->category = $category;
        $this->logInfo( 'Packaged in category.');
        if ($this->has['SetupOptions']) {
            $this->attributes['setup-options'] = array();
            $this->attributes['setup-options']['source'] = $this->sources['install_options'] . 'user.input.php';
            $this->logInfo( 'Allowing user options.');
        }
        /* Create Category attributes array dynamically
         * based on which elements are present
         */
        if ($this->has['Validator']) {
            $this->category_attributes[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
        }
        foreach ($this->object_attributes as $key => $config) {
            $this->processClassConfig($key, $config);
        }
        /* create a vehicle for the category and all the things we've added to it. */
        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);
        foreach ($this->files_config as $key => $config) {
            if($this->has[$key]) {
                $this->logInfo( 'Adding in ' . $key . '.');
                $vehicle->resolve('file', $config);
            }
        }
        if ($this->has['Validator']) {
            $this->logInfo( 'Adding in Script Validator.');
            $vehicle->validate('php', array(
                'source' => $this->sources['validators'] . 'preinstall.script.php',
            ));
        }
        /* resolvers */
        if ($this->has['Resolvers']) {
            // add as many other resolvers as necessary
            foreach ($this->resolvers as $filename) {
                $this->logInfo( 'Adding in resolver: ' . $filename);
                $vehicle->resolve('php', array(
                    'source' => $this->sources['resolvers'] . $filename,
                ));
            }
            $this->logInfo('Packaged in ' . count($this->resolvers) . ' resolvers.');
        }
        /* Put the category vehicle (with all the stuff we added to the
         * category) into the package
         */
        $this->builder->putVehicle($vehicle);
        /* now pack in the license file, readme and setup options */
        $this->builder->setPackageAttributes($this->attributes);
        $this->logInfo('Packaged in package attributes.');
        $this->logInfo('Packing...');
        $this->builder->pack();
        return true;
    }

    public function processClassConfig($key, $config) {
        if (!$this->has[$key]) {
            return;
        }
        $config = $this->arrayMergeRecursive(array(
            'file' => strtolower($key),
            'category' => null,
            'attributes' => array(),
            'category_attributes' => array(),
        ), $config);
        $attributes = $config['attributes'];
        $file = isset($config['file']) ? $config['file'] : strtolower($key);
        $object_config = $this->arrayFromFile($file);
        $objects_upgrade = array();
        if(isset($object_config['install']) && isset($object_config['upgrade'])) {
            $objects_upgrade = array_keys($object_config['upgrade']);
            $object_config = array_merge($object_config['install'],$object_config['upgrade']);
        }
        $processor = $this->getDataProcessor();
        $method_name = 'process' . ucfirst($key);
        if(!method_exists($processor,$method_name)) {
            $this->logError("Object Processor {$method_name} not found for ".$key);
            return;
        }
        if ($config['category_attributes']) {
            $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES][$key] = $config['category_attributes'];
        }
        $idx = 0;
        $allow_upgrade = array();
        $objects = array();
        foreach($object_config as $k => $o) {
            $idx++;
            if(!is_int($k) && in_array($k,$objects_upgrade)) {
                $allow_upgrade[] = $idx;
            }
            $objects[$idx] = $processor->$method_name($k,$o,$idx);
        }
        if ($config['category'] && empty($config['attributes'])) {
            if (!$this->category->addMany($objects)) {
                $this->logError( 'addMany failed with ' . $key . '.');
            }
            $this->logInfo('Packaged with category ' . count($objects) . ' ' . $key . '.');
        } else {
            foreach ($objects as $i => $object) {
                $a = $attributes;
                // allow override of upgradability
                if(in_array($i,$allow_upgrade)) {
                    $a[xPDOTransport::UPDATE_OBJECT] = true;
                }
                $vehicle = $this->builder->createVehicle($object, $a);
                $this->builder->putVehicle($vehicle);
            }
            $this->logInfo('Packaged in ' . count($objects) . ' ' . $key . '.');
        }
    }

    public function arrayMergeRecursive(array $defaults, array $array) {
        $output = $defaults;
        // like array_merge but prevent duplication of values
        foreach($array as $k => $v) {
            if(is_int($k)) {
                if (!in_array($v,$output)) {
                    $output[] = $v;
                }
            } else {
                $output[$k] = $v;
            }
        }
        // recursion for arrays
        foreach ($output as $k => $v) {
            if (!is_array($v)) continue;
            if (isset($defaults[$k])) {
                $default = $defaults[$k];
                $output[$k] = $this->arrayMergeRecursive($default, $v);
            } else {
                $output[$k] = $v;
            }
        }
        return $output;
    }
    public function arrayFromFile($file, $source = 'data', $prefix = 'transport.', $suffix = '.php') {
        $file = $this->sources[$source]. $prefix . $file . $suffix;
        $array = $this->includeFile($file);
        if (is_array($array)) {
            return $array;
        } else {
            $this->logError( "Could not load array from file {$file} in source {$source}.");
            return array();
        }
    }

    public function includeFile($file){
        if (!file_exists($file)) {
            $this->logError('Could not find file: '.$file);
            return array();
        }
//        $this->logInfo('loading '.$file);
        $output = include $file;
        return $output;
    }
    public function logError($msg) {
        $this->log(modX::LOG_LEVEL_ERROR,$msg);
    }
    public function logInfo($msg) {
        $this->log(modX::LOG_LEVEL_INFO,$msg);
    }
    public function log($level, $msg){
        $this->modx->log($level,$msg);
        flush();
    }
    public function getDataProcessor() {
        if (!($this->processor instanceof TransportDataProcessor)) {
            $this->processor = new TransportDataProcessor($this,$this->config);
        }
        return $this->processor;
    }
    /**
     * Loads and trims the PHP code from a file, stripping BOM and php tags.
     *
     * @param string $filename The path of the file.
     * @return string
     * @throws Exception
     */
    public function getSnippetContent($filename) {
        if (!file_exists($filename)) {
            throw new Exception("getSnippetContent: $filename does not exist.");
        }
        $previous_encoding = mb_internal_encoding();
        //Set the encoding to UTF-8, so when reading files it ignores the BOM
        mb_internal_encoding('UTF-8');
        //Process the  files...
        $o = file_get_contents($filename);
        if(substr($filename, -strlen('.php')) === '.php') {
            $o = preg_replace('/\<\?php/', '', $o, 1);
            $o = preg_replace('/\?\>/', '', $o, 1);
            $o = trim($o);
        }
        //Finally, return to the previous encoding
        mb_internal_encoding($previous_encoding);
        return $o;
    }
    /**
     * Like array_merge, except only keeps the keys of the first array.
     *
     * @param array $defaults
     * @param array $array
     * @return array
     */
    public function defaultsMerge(array $defaults, array $array) {
        $output = $defaults;
        foreach($output as $k => $v) {
            if(isset($array[$k])) {
                $output[$k] = $array[$k];
            }
        }
        return $output;
    }
}

/**
 * Method names of the format processKEY (where KEY is an object alias) to create an object from an object config.
 */
class TransportDataProcessor {
    /** @var Transporter A reference to the Transporter object. */
    public $transporter = null;
    /** @var modX A reference to the modX object. */
    public $modx = null;
    /** @var array A collection of properties to adjust behaviour. */
    public $config = array();
    function __construct(Transporter &$transporter, array $config = array()) {
        $this->transporter =& $transporter;
        $this->modx =& $transporter->modx;
        $this->sources =& $transporter->sources;
    }

    public function processSettings($key,$config,$idx=0){
        /** @var $setting modSystemSetting */
        if (is_array($config)) {
            $value = isset($config['value']) ? $config['value'] : '';
        } else {
            $value = $config;
        }
        if (is_bool($value)) {
            $xtype = 'combo-boolean';
        } else {
            $xtype = 'textfield';
        }
        $setting = $this->_processObject($config,array(
            'default_field' => 'value',
            'class' => 'modSystemSetting',
            'defaults' => array(
                'key' => $key,
                'value' => '',
                'area' => '',
                'namespace' => PKG_NAME_LOWER,
                'xtype' => $xtype,
            ),
        ));
        return $setting;
    }
    public function processResources($key,$config,$idx=0){
        $this->logInfo('Packaging resource: ('.$key.')');
        $resource = $this->_processObject($config,array(
            'default_field' => 'content',
            'class' => 'modResource',
            'defaults' => array(
                'class_key' => 'modDocument',
                'context_key' => 'web',
                'type' => 'document',
                'contentType' => 'HTML',
                'published' => 1,
                'cacheable' => 1,
                /* good to change */
                'pagetitle' => $key,
                'longtitle' => '',
                'description' => '',
                'introtext' => '',
                'menutitle' => '',
                'content' => '',
                'menuindex' => ($idx * 10),
                /* often changed */
                'richtext' => 1,
                'searchable' => 1,
                'hidemenu' => 0,
                'parent' => 0,
                'isfolder' => 0,
                'alias' => str_replace(' ', '-', strtolower($config['pagetitle'])),
            ),
        ));
        return $resource;
    }
    public function processPolicyTemplateGroups($key,$config,$idx=0){
        /** @var $group modMenu */
        return $this->_processObject($config,array(
            'default_field' => 'description',
            'class' => 'modAccessPolicyTemplateGroup',
            'defaults' => array(
                'name' => $key,
                'description' => '',
            ),
            'related' => array(
                'policy_templates' => array(
                    'method' => 'processPolicyTemplates',
                    'alias' => 'Templates',
                )
            )
        ));
    }
    public function processPolicyTemplates($key,$config,$idx=0){
        /** @var $template modAccessPolicyTemplate */
        return $this->_processObject($config,array(
            'default_field' => 'permissions',
            'class' => 'modAccessPolicyTemplate',
            'defaults' => array(
                'id' => $idx,
                'name' => $key,
                'description' => '',
                'lexicon' => 'permissions',
                'template_group' => PKG_NAME, // todo: convert to int
                'permissions' => array(),
                'policies' => array(),
            ),
            'related' => array(
                'policies' => array(
                    'method' => 'processAccessPolicy',
                    'alias' => 'Policies',
                ),
                'permissions' => array(
                    'method' => 'processAccessPermission',
                    'alias' => 'Permissions',
                )
            ),
        ));
    }
    public function processAccessPolicy($key,$config,$idx=0){
        $policy = $this->_processObject($config,array(
            'default_field' => 'permissions',
            'class' => 'modAccessPolicy',
            'defaults' => array(
                'name' => $key,
                'description' => '',
                'parent' => 0,
                'class' => '',
                'data' => '{}',
                'lexicon' => 'permissions',
            ),
            'related' => array(
                'children' => array(
                    'method' => 'processAccessPolicy',
                    'alias' => 'Children',
                ),
            ),
        ));
        //todo: $policy->set('data',JSONPERMISSIONs)
        return $policy;
    }
    public function processMenus($key,$config,$idx=0){
        /** @var $menu modMenu */
        $menu = $this->_processObject($config,array(
            'default_field' => 'handler',
            'class' => 'modMenu',
            'defaults' => array(
                'parent' => '',
                'text' => PKG_NAME_LOWER.'.'.$key,
                'description' => PKG_NAME_LOWER.'.'.$key.'_desc',
                'icon' => 'images/icons/plugin.gif',
                'menuindex' => 100+($idx*10),
                'params' => '',
                'handler' => '',
            ),
            'related' => array(
                'action' => array(
                    'method' => 'processMenuActions',
                    'alias' => 'Action',
                    'type' => 'one',
                )
            ),
        ));
        return $menu;
    }
    public function processMenuActions($key,$config,$idx=0){
        $action = $this->_processObject($config,array(
            'default_field' => 'controller',
            'class' => 'modAction',
            'defaults' => array(
                'id' => $idx,
                'namespace' => PKG_NAME_LOWER,
                'parent' => 0,
                'controller' => '',
                'haslayout' => 1,
                'lang_topics' => PKG_NAME_LOWER.':default,file',
                'assets' => '',
            ),
        ));
        return $action;
    }
    /**
     * Creates a chunk object.
     *
     * @param string $key
     * @param array|string $config
     * @param int $idx
     * @return modPlugin
     */
    public function processChunks($key,$config,$idx=0) {
        /** @var $snippet modChunk */
        $chunk = $this->_processObject($config,array(
            'default_field' => 'snippet',
            'class' => 'modChunk',
            'defaults' => array(
                'id' => $idx,
                'name' => $key,
                'description' => '',
                'snippet' => '',
                'properties' => '',
            ),
            'field_from_file' => 'snippet',
            'filename' => $this->sources['chunks'].strtolower($key).'.chunk.tpl',
            'has_properties' => true,
            'primary_field' => 'name',
        ));
        return $chunk;
    }
    public function processPropertySets($key,$config,$idx=0){
        /** @var $snippet modPropertySet */
        $snippet = $this->_processObject($config,array(
            'default_field' => 'properties',
            'class' => 'modPropertySet',
            'defaults' => array(
                'id' => $idx,
                'name' => $key,
                'description' => '',
            ),
            'has_properties' => true,
            'primary_field' => null, // do not generate defaults such as description, lexicon, type, etc...
        ));
        return $snippet;
    }
    public function processSnippets($key,$config,$idx=0){
        /** @var $snippet modSnippet */
        $snippet = $this->_processObject($config,array(
            'default_field' => 'snippet',
            'class' => 'modSnippet',
            'defaults' => array(
                'id' => $idx,
                'name' => $key,
                'description' => '',
            ),
            'field_from_file' => 'snippet',
            'filename' => $this->sources['snippets'].strtolower($key).'.snippet.php',
            'has_properties' => true,
            'primary_field' => 'name',
        ));
        return $snippet;
    }
    public function processTemplates($key,$config,$idx=0){
        /** @var $template modTemplate */
        $template = $this->_processObject($config,array(
            'default_field' => 'description',
            'class' => 'modTemplate',
            'defaults' => array(
                'id' => $idx,
                'templatename' => $key,
                'description' => '',
                'content' => '',
            ),
            'field_from_file' => 'content',
            'filename' => $this->sources['templates'].strtolower($key).'.template.tpl',
            'has_properties' => true,
            'primary_field' => 'templatename',
        ));
        return $template;
    }
    public function processTemplateVars($key,$config,$idx=0){
        /** @var $template modTemplate */
        $template = $this->_processObject($config,array(
            'default_field' => 'caption',
            'class' => 'modTemplate',
            'defaults' => array(
                'id' => $idx,
                'type' => 'textfield',
                'name' => $key,
                'caption' => '',
                'description' => '',
                'display' => 'default',
                'elements' => '',  /* input option values */
                'locked' => 0,
                'rank' => 0,
                'display_params' => '',
                'default_text' => '',
            ),
            'has_properties' => true,
            'primary_field' => 'name',
        ));
        return $template;
    }
    // todo-important: events not being added!
    /**
     * Creates a Plugin object.
     *
     * @param string $key
     * @param array $config
     * @param int $idx
     * @return modPlugin
     */
    public function processPlugins($key,$config,$idx=0) {
        $plugin = $this->_processObject($config,array(
            'default_field' => 'plugincode',
            'class' => 'modPlugin',
            'defaults' => array(
                'id' => $idx,
                'name' => $key,
                'description' => '',
                'category' => 0,
            ),
            'field_from_file' => 'plugincode',
            'filename' => $this->sources['plugins'].strtolower($key).'.plugin.php',
            'has_properties' => true,
            'primary_field' => 'name',
            'related' => array(
                'events' => array(
                    'method' => 'processPluginEvents',
                    'alias' => 'PluginEvents',
                )
            )
        ));
        return $plugin;
    }

    public function processPluginEvents($key, $config, $idx = 0) {
        $event = $this->_processObject($config,array(
            'default_field' => 'priority',
            'class' => 'modPluginEvent',
            'defaults' => array(
                'event' => $key,
                'priority' => 10,
                'propertyset' => 0,
            ),
        ));
        return $event;
    }

    /**
     * Takes an array of specific object values and uses an array of class-related settings to turn them into arrays ready to be used in fromArray().
     *
     * @param array|mixed $fields_raw
     * @param array $settings
     * @return array Array of class (string), object fields (array for fromArray),
     *         properties (array for setProperties),
     *         related objects (array of alias=>object|array: object for addOne, array for addMany)
     */
    public function _processObjectConfig($fields_raw, array $settings) { // init config
        $class = $settings['class'];
        $default_field = $settings['default_field'];
        if (!is_array($fields_raw)) {
            $fields_raw = array($default_field => $fields_raw);
        }
        // related Objects
        $related = array();
        $related_raw = $this->modx->getOption('related', $settings, array());
        foreach ($related_raw as $k => $related_settings) {
            $related_fields = array();
            if (isset($fields_raw[$k])) {
                $related_fields = $fields_raw[$k];
                unset($fields_raw[$k]);
            } else {
                continue;
            }
            $error_name = $k.' for '.$class;
            $related[$related_settings['alias']] = $this->_processRelated($related_fields, $related_settings, $error_name);
        }
        // init properties
        $properties_raw = array();
        if ($this->modx->getOption('has_properties', $settings, null) && isset($fields_raw['properties'])) {
            $properties_raw = $fields_raw['properties'];
            unset($fields_raw['properties']);
        }
        $fields = array_merge($settings['defaults'], $fields_raw);
        // load from file
        $filename = $this->modx->getOption('filename', $settings, null);
        if ($filename && file_exists($filename)) {
            $field_from_file = $this->modx->getOption('field_from_file', $settings, $default_field);
            $content = $this->modx->getOption($field_from_file, $fields, null);
            if (empty($content)) {
                $fields[$field_from_file] = $this->transporter->getSnippetContent($filename);
            }
        }
        $properties = array();
        if ($properties_raw) {
            $primary_field = $settings['primary_field'];
            $element_name = $primary_field ? strtolower($fields[$primary_field]) : null;
            $properties = array();
            foreach ($properties_raw as $j => $v) {
                $properties[] = $this->_processObjectProperty($j, $v, $element_name);
            }
        }
        return array($class, $fields, $properties, $related);
    }
    public function _processObject($fields_raw, $settings) {
        list($class, $fields_raw, $properties, $related) = $this->_processObjectConfig($fields_raw, $settings);
        /** @var $object xPDOObject */
        $object = $this->modx->newObject($class);
        $object->fromArray($fields_raw,'',true,true);
        if ($properties) {
            /** @var $object modElement */
            $object->setProperties($properties);
        }
        // add related objects
        foreach ($related as $alias => $rconf) {
            $error_name =( ($alias || !is_object($rconf)) ? $alias : get_class($rconf)).' for '.$class;
            if(is_array($rconf)) {
                $object->addMany($rconf,$alias);
                $count = count($rconf);
            } else {
                $object->addOne($rconf,$alias);
                $count = 'one';
            }
            $this->logInfo("Added ".$count." related {$error_name}");
        }
        // verify
        if (!($object instanceof $class)) {
            $this->logError("Failed to create {$class}");
            return null;
        }
        return $object;
    }

    /**
     * @param array $related_fields An array of the related object key => related object settings
     * @param array|mixed $related_settings
     * @param string $error_name
     * @return array|object An array of related objects if type is many, or an object otherwise
     */
    public function _processRelated($related_fields, $related_settings, $error_name) {
        $type = $this->modx->getOption('type', $related_settings, 'many');
        $method = $this->modx->getOption('method', $related_settings, null);
        if (!method_exists($this, $method)) {
            $method = null;
        }
        $objs = array();
        $idx = 0;
        foreach ($related_fields as $i => $c) {
            $idx++;
            if ($method) {
                $obj = $this->$method($i, $c, $idx);
                if (empty($obj)) {
                    $this->logError("Failed to generate related object for ".$error_name." with idx {$i}");
                    continue;
                }
            } else {
                $this->logError("Could not process related ".$error_name.".");
                continue;
            }
            if ($type == 'many') {
                $objs[] = $obj;
            } else {
                return $obj;
            }
        }
        if (empty($objs)) {
            $this->logError("No related objects generated for ".$error_name.".");
        }
        return $objs;
    }

    public function _processObjectProperty($key,$config,$element_name=null){
        if (!is_array($config)) {
            $config = array('value' => $config);
        }
        if (!isset($config['value'])) $config['value'] = '';
        $element_name = strtolower($element_name);
        $defaults = array(
            'name' => $key,
            'value' => '',
        );
        if ($element_name) {
            $defaults = array_merge(array(
                'desc' => PKG_NAME_LOWER . "_{$element_name}_{$key}_desc",
                'type' => is_string($config['value']) ? 'textfield' : 'combo-boolean',
                'options' => '',
                'lexicon' => PKG_NAME_LOWER . ':properties',
            ),$defaults);
        }
        $property = $this->transporter->defaultsMerge($defaults,$config);
        return $property;
    }
    public function logInfo($msg) {
        $this->transporter->logInfo($msg);
    }
    public function logError($msg) {
        $this->transporter->logError($msg);
    }

}
