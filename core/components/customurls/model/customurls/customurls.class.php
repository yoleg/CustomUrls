<?php
/*
 * customUrls
 * Helps with parsing URLs
 * 
 * @uses HTTP_UPLOAD
 * @package ugmedia
 * @subpackage ugmedia
 *
 */
include_once dirname(__FILE__).'/cuschema.class.php';
class customUrls {
    /**
     * @access public
     * @var modX A reference to the modX object.
     */
    public $modx = null;
    /**
     * @access public
     * @var array A config array.
     */
    public $config = array();
    /**
     * @access protected
     * @var array The array of default url schema options
     */
    protected $defaults = array(
        'landing_resource_id' => 0,             // the resource id to redirect to
        'set_request' => true,                  // If true, sets $_REQUEST parameters
        'request_prefix' => 'user_',            // $_REQUEST parameter prefix
        'request_name_id' => 'id',              // $_REQUEST parameter for the value of the search_result_field
        'request_name_action' => 'action',      // $_REQUEST parameter for action (if found in the action map)
        'set_get' => true,                      // If true, sets $_GET parameters (using same settings as $_REQUEST)
        'base_url' => '',                       // NOT the system base_url, just a prefix to add to all generated URLs
        'lowercase_url' => true,                // Generates lowercase urls. Does not affect searches.
        'url_prefix' => '',                     // A prefix to append to the start of all urls
        'url_prefix_required' => true,          // Resolve the URL without the prefix?
        'url_suffix' => '',                     // A suffix to append to the end of all urls
        'url_suffix_required' => true,          // Resolve the URL without the suffix?
        'url_delimiter' => '/',                 // The separator between the main URL and the action
        'load_modx_service' => array(),         // loads as service if not empty. Must have the following keys set: 'name', 'class', 'package' OR 'path', and 'config' (config is an array). If you specify a lowercase package name, the path will be generated automatically for you based on either package.core_path setting or the default component structure.
        'search_class' => 'modUser',            // the xPDOObject class for the database table to search through
        'search_field' => 'username',           // the field to use in the URL
        'search_result_field' => 'id',          // the field to pass to the resource via the request_name_id
        'search_display_field' => '',           // the field to set in a special "display" placeholder to allow you to use the same placeholder for multiple schemas (defaults to search field)
        'search_where' => array('active' => 1), // an additional filter for the database query (xpdo where)
        'search_class_test_method' => '',       // a method name of the class to run. If resolves to false, will not continue. Useful for permissions checking.
        'action_map' => array(),                // an array of keys (action names) and values (resource ids) to use for the sub-actions
        'redirect_if_accessed_directly' => true,// will redirect to the error page if visited without CustomUrls
        'redirect_if_object_not_found' => true, // will redirect to the error page if the object is not found
        'set_placeholders' => true,             // will generate some placeholders on the page storing the object field values
        'placeholder_prefix' => 'customurls',   // the placeholder prefix to use if set_placeholders is true
        'display_placeholder' => 'display',     // the placeholder for the display value to use if set_placeholders is true
        'custom_search_replace' => array(),     // an array of search => replace pairs to str_replace the output with
        'run_without_parent' => true,           // if set to false, will not be run unless called by another schema in the child_schemas array
        'child_schemas' => array(),             // an array of schema_names OR schema_name => (array) overrides to run the URL remainder through
        'url_from_params' => true,              // if the page is accessed directly but with the proper GET parameters, the plugin will try to detect the schema from the GET or REQUEST params and forward to the friendly Url. Useful for Quip and similar components that redirect directly to the current page afterwards.
    );

    /**
     * @access public
     * @var array The array of url schemas, already merged with defaults
     */
    public $schemas = array();
    /**
     * @access public
     * @var array A an array of resource ids to their corresponding array of schema names
     */
    public $resources = array();
    /**
     * @access public
     * @var cuSchema The instance of the schema being currently used on the document
     */
    public $current_schema = null;
    /**
     * @var bool If set to true, will disable the plugin execution. Useful for preventing multiple firings (if sending to an error page for instance).
     */
    public $disable = false;

    /**
     * The customUrls Constructor.
     *
     * This method is used to create a new customUrls object.
     *
     * @param modX $modx A reference to the modX object.
     * @param array $config A collection of properties that modify customUrls
     * behaviour.
     * @return customUrls A unique customUrls instance.
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $this->config = $config;
        $defaults = $this->defaults;
        $custom_defaults = $this->modx->fromJSON($this->modx->getOption('customurls.defaults',null,'[]'));
        $defaults = array_merge($defaults,$custom_defaults);
        $url_schemas = $this->modx->fromJSON($this->modx->getOption('customurls.schemas',null,'{"users":{"request_prefix":"uu_","request_name_id":"userid"}}'));
        // clean and register url schemas
        foreach ($url_schemas as $schema_name => $config) {
            // merge config with defaults
            $config = array_merge($defaults,$config);
            // register landing page in resource map
            $landing = $config['landing_resource_id'];
            if (empty($landing)) continue;
            $this->addLanding($landing,$schema_name);
            $config['key'] = $schema_name;
            // skip child-only schemas
            $top_level = $config['run_without_parent'];
            if (!$top_level) continue;
            // calculate children
            $children = $config['child_schemas'];
            $processed_children = array();
            if (!empty($children)) {
                foreach ($children as $child => $child_config) {
                    if (is_numeric($child)) {
                        $child = $child_config;                          // allow simple array of schema names
                        $child_config = array();
                    }
                    if (!isset($url_schemas[$child])) continue;
                    $child_config = array_merge($defaults,$url_schemas[$child],$child_config);
                    $child_landing = $child_config['landing_resource_id'];
                    if (empty($child_landing)) continue;
                    $this->addLanding($landing,$schema_name);   // parent is registered as the owner of the landing page
                    $child_config['key'] = $child;
                    // create the child objects
                    $processed_children[$child] = new cuSchema($this,$child_config);
                }
            }
            // save the object
            $schema = new cuSchema($this,$config);
            if (!empty($processed_children)) $schema->children = $processed_children;
            $this->schemas[$schema_name] = $schema;
        }
    }
    /**
     * Disables the plugin
     * @return bool
     */
    public function disable() {
        $this->disable = true;
        return true;
    }
    /**
     * Enables the plugin
     * @return bool
     */
    public function enable() {
        $this->disable = false;
        return true;
    }
    /**
     * Checks if user has permissions to use the plugin
     * Only (enabled/ disabled) checking is currently implemented.
     * @return bool
     */
    public function canUse() {
        if ($this->disable) return false;
        return true;
    }
    /**
     * Registers a landing-page, schema name pair
     * @param int $landing The landing resource
     * @param string $schema_name The name of the schema
     * @return array The current array of landing pages
     */
    protected function addLanding($landing,$schema_name) {
        if (!isset($this->resources[$landing])) {
            $this->resources[$landing] = array();
        }
        $this->resources[$landing][] = $schema_name;
        return $this->resources[$landing];
    }
    /**
     * Gets the schemas using a particular resource as a landing page
     * @param int $landing The landing resource
     * @return array An array of schema names for this resource
     */
    public function getSchemasByLanding($landing) {
        if (!isset($this->resources[$landing])) return array();
        return $this->resources[$landing];
    }
    /**
     * Gets the schema object currently in use by the page
     * @return cuSchema|null The schema object or null
     */
    public function getCurrentSchema() {
        if (empty($this->current_schema)) return null;
        return $this->current_schema;
    }
    /**
     * Attempts to generate a CustomUrl from the REQUEST parameters set on the page
     * ToDo: add support for child classes
     * @return string The generated url
     */
    public function detectUrlFromRequest() {
        $current_resource_id = ''.$this->modx->resource->get('id');
        $possible_schemas = $this->getSchemasByLanding($current_resource_id);
        $possible_schemas = array_unique($possible_schemas);
        foreach ($possible_schemas as $key => $schema_name) {
            $schema = $this->getSchema($schema_name);
            if (false) $schema = new cuSchema($this);       // debug only - never used
            if (!$schema instanceof cuSchema) continue;
            if (!$schema->get('url_from_params')) continue;
            $param = $schema->getRequestKey('id');
            if (empty($param)) continue;
            if (!isset($_REQUEST[$param])) continue;
            $object = $schema->getObject('search_result_field', $_REQUEST[$param]);
            $class =  $schema->get('search_class');
            if ($object instanceof $class) {
                $action_param = $schema->getRequestKey('action');
                $action = isset($_REQUEST[$action_param]) ? $_REQUEST[$action_param] : '';
                $url = $schema->makeUrl($object,$action);
                if (!empty($url)) {
                    $delimiter = "?";
                    if (isset($_GET[$param])) unset($_GET[$param]);
                    $q = $this->modx->getOption('request_param_alias');
                    $id = $this->modx->getOption('request_param_id');
                    if (isset($_GET['q'])) unset($_GET[$q]);
                    if (isset($_GET['id'])) unset($_GET[$id]);
                    foreach($_GET as $key => $value) {
                        $url .= $delimiter.$key."=".$value;
                        $delimiter = "&";
                    }
                    return $url;
                }
            }
        }
        return '';
    }
    /**
     * Validates the schema object currently in use by the page
     * @return string One of 'pass','fail', or 'redirect'
     */
    public function validateCurrentSchema() {
        $current_resource_id = ''.$this->modx->resource->get('id');
        $possible_schemas = $this->getSchemasByLanding($current_resource_id);
        $possible_schemas = array_unique($possible_schemas);
        $the_only_poss_schema = (count($possible_schemas) == 1) ? $possible_schemas[0] : '';
        if (empty($possible_schemas) || !is_array($possible_schemas)) {
            $this->setCurrentSchema(null);
            return 'fail';
        }
        $schema = $this->getCurrentSchema();
        $schema_name = ($schema instanceof cuSchema) ? $schema->get('key') : '';
        if (empty($schema_name) || !in_array($schema_name,$possible_schemas)) {
            $this->setCurrentSchema(null);
            if ($the_only_poss_schema) {
                foreach($possible_schemas as $schema_name) {
                    $schema = $this->getSchema($schema_name);
                    $redirect = (bool) $schema->get('redirect_if_accessed_directly');
                    if ($redirect) return 'redirect';
                }
            }
            return 'fail';
        }
        $config = $schema->config;
        $resource = $schema->getData('resource');   // only set if redirected by onPageNotFound event
        if (empty($resource)) {
            $this->setCurrentSchema(null);
            if ($the_only_poss_schema) {
                $redirect = (bool) $schema->get('redirect_if_accessed_directly');
                if ($redirect) return 'redirect';
            }
            return 'fail';
        }
        if ($resource != $this->modx->resource->get('id')) {
            $this->setCurrentSchema(null);    // prevents the next event from executing
            return 'fail';
        }
        // check object
        $object = $schema->getData('object');
        if (!$object) {
            if ($config['redirect_if_object_not_found']) $this->modx->sendErrorPage();
            $this->setCurrentSchema(null);
            return 'fail';
        }
        if (!empty($config['search_class_test_method']) && !$object->$config['search_class_test_method']()) {
            $this->setCurrentSchema(null);
            return 'fail';
        }
        return 'pass';
    }
    /**
     * Sets the schema object currently in use by the page
     * @param cuSchema|null $schema The schema object to set
     * @return cuSchema|false The schema object or false if failed
     */
    public function setCurrentSchema($schema) {
        if (!($schema instanceof cuSchema)) return false;
        $this->current_schema = $schema;
        return $this->current_schema;
    }

    /**
     * Parses the url to see if it matches any of the schemas
     * @param string $url The url to parse
     * @return cuSchema|null The schema if successful or null if not
     */
    public function parseUrl($url) {
        $output = null;
        foreach ($this->schemas as $key => $schema) {
            if ($schema instanceof cuSchema && $schema->parseUrl($url)) {
                $output = $schema;
            }
        }
        return $output;
    }
    
    /**
     * Returns a url from the object and schema name
     * Basically passes all other parameters to the makeUrl method of the schema object specified by $schema_name
     * @see cuSchema::makeUrl
     * @param string|array $object An instance of the object, or an array of objects (first will be used as main, all others as children)
     * @param string $schema_name The schema name
     * @param string $action The optional action for the url
     * @param array $children An array of child objects (ordered by depth)
     * @return string The url
     */
    public function makeUrl($object=null,$schema_name,$action = '',$children=array()) {
        $schema = $this->getSchema($schema_name);
        if (!($schema instanceof cuSchema)) return '';
        $output = $schema->makeUrl($object,$action,$children);
        return $output;
    }
    /**
     * return the schema object
     * @param string $key The schema key
     * @return cuSchema The schema object
     */
    public function getSchema($key) {
        $key = (string) $key;
        if (!isset($this->schemas[$key])) return null;
        if (!($this->schemas[$key] instanceof cuSchema)) {
            unset($this->schemas[$key]);
            return null;
        }
        return $this->schemas[$key];
    }

    /**
     * Checks if subject starts with search
     * @param string $search
     * @param string $subject
     * @return bool True if subject begins with search, false if not
     */
    public function findFromStart($search,$subject){
        if (empty($search)) return true;
        if (substr($subject,0, strlen($search))===$search) return true;
        return false;
    }

    /**
     * Checks if subject ends with search
     * @param string $search
     * @param string $subject
     * @return bool True if subject ends with search, false if not
     */
    public function findFromEnd($search,$subject){
        if (empty($search)) return true;
        if (substr($subject,-strlen($search))===$search) return true;
        return false;
    }
    /**
     * Replace a search in the first part of a string.
     * Like str_replace for strings, but only replaces if the search is exactly in the beginning of the subject string.
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string The replaced string
     */
    public function replaceFromStart($search, $replace, $subject){
        $output = '';
        if (empty($search)) return $subject;
        if (substr($subject,0, strlen($search))===$search) {
            $output = $replace.substr($subject, strlen($search));
        }
        return $output;
    }
    /**
     * Replace a search in the last part of a string.
     * Like str_replace for strings, but only replaces if the search is exactly in the end of the subject string.
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string The replaced string
     */
    public function replaceFromEnd($search, $replace, $subject){
        $output = '';
        if (empty($search)) return $subject;
        if (substr($subject,-strlen($search))===$search) {
            $output = substr($subject, 0, strlen($subject)-strlen($search)).$replace;
        }
        return $output;
    }
}