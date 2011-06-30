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
    );
    /**
     * @access protected
     * @var array The array of url schemes, already merged with defaults
     */
    protected $schemes = array();

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
        $this->config =& $config;
    }

    /**
     * Checks if subject starts with search
     * @param string $search
     * @param string $subject
     */
    public function getUrlSchemas() {
        if (empty($this->schemes)) {
            $defaults = $this->defaults;
            $custom_defaults = $this->modx->fromJSON($this->modx->getOption('customurls.defaults',null,'[]'));
            $defaults = array_merge($defaults,$custom_defaults);
            $url_schemes = $this->modx->fromJSON($this->modx->getOption('customurls.schemes',null,'{"users":{"request_prefix":"uu_","request_name_id":"userid"}}'));

            // clean url_schemes
            $resource_may_redirect = array();
            $new_url_schemes = array();
            foreach ($url_schemes as $name => $config) {
                // ensure all options are set
                $config = array_merge($defaults,$config);
                $new_url_schemes[$name] = $config;
            }
             $url_schemes = $new_url_schemes;
             $this->schemes = $url_schemes;
        }
        return $this->schemes;
    }

    /**
     * Generates a URL for a particular schema
     * @param string $schema_name
     * @param string $field_name_config_key The configuration key that holds the name of the field to search by in the database table
     * @param string $value The value that $field should be equal to
     */
    public function getObject($schema_name,$field_name_config_key,$field_value) {
        $schemas = $this->getUrlSchemas();
        $schema = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($schema)) return '';
        $object = $this->modx->getObject($schema['search_class'],array_merge(array(
            $schema[$field_name_config_key] => $field_value,
        ),$schema['search_where']));
        if (!($object instanceof $schema['search_class'])) return null;
        return $object;
    }
    /**
     * loads the service for a particular schema
     * @param string $schema_name
     */
    public function loadService($schema_name) {
        $schemas = $this->getUrlSchemas();
        $config = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($config)) return null;
        $load_modx_service_object = $this->modx->getService($config['load_modx_service']['name'],$config['load_modx_service']['class'],$this->modx->getOption($config['load_modx_service']['package'].'.core_path',null,$this->modx->getOption('core_path').'components/'.$config['load_modx_service']['package'].'/').'model/'.$config['load_modx_service']['package'].'/',$config['load_modx_service']['config']);
        if (!($load_modx_service_object instanceof $config['load_modx_service']['class'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not load FURL custom service: '.$config['load_modx_service']['name']);
            return null;
        }
        return $load_modx_service_object;
    }
    /**
     * Generates a URL for a particular schema
     * @param string $schema_name
     * @param string $object An instance of the object
     */
    public function makeUrl($schema_name,$object) {
        $schemas = $this->getUrlSchemas();
        $schema = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($schema)) return '';
        if (!($object instanceof $schema['search_class'])) return '';
        $alias = urlencode($object->get($schema['search_field']));
        $output = $schema['base_url'].$schema['url_prefix'].$alias.$schema['url_suffix'];
        return $output;
    }
    /**
     * gets the REQUEST key for the schema
     * @param string $schema_name
     */
    public function getRequestKey($schema_name,$type = 'id') {
        $schemas = $this->getUrlSchemas();
        $config = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($config)) return '';
        $output = isset($config['request_name_'.$type]) ? $config['request_prefix']. $config['request_name_'.$type] : '';
        return $output;
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