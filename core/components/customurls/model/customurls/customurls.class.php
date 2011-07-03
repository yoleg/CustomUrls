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
     * @var array The array of default url scheme options
     */
    protected $defaults = array(
        'landing_resource_id' => 0,             // the resource id to redirect to
        'request_prefix' => 'user_',            // $_REQUEST parameter prefix
        'request_name_id' => 'id',              // $_REQUEST parameter for the value of the search_result_field
        'request_name_action' => 'action',      // $_REQUEST parameter for action (if found in the action map)
        'base_url' => '',                       // NOT the system base_url, just a prefix to add to all generated URLs
        'lowercase_url' => true,                // Generates lowercase urls. Does not affect searches.
        'url_prefix' => '',                     // A prefix to append to the start of all urls
        'url_prefix_required' => true,          // Resolve the URL without the prefix?
        'url_suffix' => '',                     // A suffix to append to the end of all urls
        'url_suffix_required' => true,          // Resolve the URL without the suffix?
        'url_delimiter' => '/',                 // The separator between the main URL and the action
        'load_modx_service' => array(),         // loads as service if not empty. Must have the following keys set: 'name', 'class', 'package', and 'config' (config is an array)
        'search_class' => 'modUser',            // the xpdo class for the database table to search through
        'search_field' => 'username',           // the field to use in the URL
        'search_result_field' => 'id',          // the field to pass to the resource via the request_name_id
        'search_display_field' => '',           // the field to set in a special "display" placeholder to allow you to use the same placeholder for multiple schemes (defaults to search field)
        'search_where' => array('active' => 1), // an additional filter for the database query (xpdo where)
        'search_class_test_method' => '',       // a method name of the class to run. If resolves to false, will not continue. Useful for permissions checking.
        'action_map' => array(),                // an array of keys (action names) and values (resource ids) to use for the sub-actions
        'redirect_if_accessed_directly' => true,// will redirect to the error page if visited without CustomUrls
        'redirect_if_object_not_found' => true, // will redirect to the error page if the object is not found
        'set_placeholders' => true,             // will generate some placeholders on the page storing the object field values
        'placeholder_prefix' => 'customurls',   // the placeholder prefix to use if set_placeholders is true
        'display_placeholder' => 'display',     // the placeholder for the display value to use if set_placeholders is true
        'custom_search_replace' => array(),     //
    );
    /**
     * @access public
     * @var array The array of url schemes, already merged with defaults
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
     * The customUrls Constructor.
     *
     * This method is used to create a new customUrls object.
     *
     * @param modX &$this->modx A reference to the modX object.
     * @param array $config A collection of properties that modify customUrls
     * behaviour.
     * @return customUrls A unique customUrls instance.
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $this->config = array_merge(array(
            'scheme_param' => $modx->getOption('customurls.schema_param_name',null,'customurls_schema_name'),
            'validated_param' => $modx->getOption('customurls.validated_param_name',null,'customurls_validated'),
        ),$config);
        $defaults = $this->defaults;
        $custom_defaults = $this->modx->fromJSON($this->modx->getOption('customurls.defaults',null,'[]'));
        $defaults = array_merge($defaults,$custom_defaults);
        $url_schemes = $this->modx->fromJSON($this->modx->getOption('customurls.schemes',null,'{"users":{"request_prefix":"uu_","request_name_id":"userid"}}'));
        // clean and register url schemes
        foreach ($url_schemes as $schema_name => $config) {
            // merge config with defaults
            $config = array_merge($defaults,$config);
            // register landing page in resource array
            $landing = $config['landing_resource_id'];
            if (empty($landing)) continue;
            $this->addLanding($landing,$schema_name);
            $config['key'] = $schema_name;
            $this->schemas[$schema_name] = new cuSchema($this,$config);
        }
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
        $this->resources[$landing] = array($schema_name);
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
     * Gets and validates the schema object currently in use by the page
     * @return cuSchema|null The schema object or null
     */
    public function getCurrentSchema() {
        if (empty($this->current_schema)) return null;
        return $this->current_schema;
    }
    /**
     * Sets the schema object currently in use by the page
     * @param cuSchema|null $schema The schema object or null
     * @return cuSchema|null The schema object or null
     */
    public function setCurrentSchema($schema) {
        if (empty($this->current_schema)) return null;
        return $this->current_schema;
    }

    /**
     * Parses the url to see if it matches any of the schemas
     * @param string $url The url to parse
     * @return string|false The key of the schema or false if schema not fount
     */
    public function parseUrl($url) {
        foreach ($this->schemas as $key => $schema) {
            if ($schema->parseUrl($url)) {
                return $schema;
            }
        }
        return false;
    }
    /**
     * return the schema object
     * @param string $key The schema key
     * @return cuSchema The schema object
     */
    public function getSchema($key) {
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