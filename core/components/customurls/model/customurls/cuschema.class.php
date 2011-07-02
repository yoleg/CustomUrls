<?php
/*
 * cuScheme
 * Represents a single URL scheme.
 * 
 * @uses HTTP_UPLOAD
 * @package ugmedia
 * @subpackage ugmedia
 */
include_once dirname(__FILE__) . '/cuschema.class.php';
class cuScheme {
    /**
     * @access public
     * @var customUrls A reference to the customUrls object
     */
    public $cu = null;
    /**
     * @access public
     * @var modX A reference to the modX object.
     */
    public $modx = null;
    /**
     * @access public
     * @var array A config array.
     */
    protected  $config = array();
    /**
     * @access public
     * @var string The unique schema identifier
     */
    protected  $key = '';
    /**
     * @access public
     * @var array Stores the data found for this schema for the active resource
     */
    protected  $data = array();
    /**
     * The cuScheme Constructor.
     *
     * This method is used to create a new cuScheme object.
     *
     * @param customUrls &$customurls A reference to the customUrls object.
     * @param array $config A collection of properties that modify cuScheme
     * behaviour.
     * @return cuScheme A unique cuScheme instance.
     */
    function __construct(customUrls &$customurls,array $config = array()) {
        $this->cu =& $customurls;
        $this->modx =& $customurls->modx;
        if (!($this->modx instanceof modX)) {
            $this->modx = new modX();           // for debugging
        }
        $this->config =& $config;
        $this->key = $this->config['key'];
    }
    
    /**
     * Returns stored data
     * @param string $key The data key
     * @param mixed $default Default to use if no data is found
     * @return mixed The data value
     */
    public function getData($key,$default = null) {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
    /**
     * Sets data
     * @param string $key The data key
     * @param mixed $value The value to set
     * @return bool True if successful
     */
    public function setData($key,$value) {
        $this->data[$key] = $value;
        return $value;
    }
    /**
     * Returns config value
     * @param string $key The config key
     * @param mixed $default Default to use if no config is found
     * @return mixed The config value
     */
    public function get($key,$default = null) {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
    /**
     * Sets config
     * @param string $key The config key
     * @param mixed $value The value to set
     * @return bool True if successful
     */
    public function set($key,$value) {
        $this->config[$key] = $value;
        return $value;
    }

    /**
     * Parses a particular URL to see if it matches this schema
     * @param string $url The url to parse
     * @return bool True if match found, false otherwise
     */
    public function parseUrl($url) {
        // Load ugm class
        // ToDO: add error checking
        if (!empty($this->config['load_modx_service'])) {
            $service = $this->loadService();
            if (!$service) return false;
        }

        // Exit right away if required prefix is missing
        if (!empty($this->config['url_prefix'])) {
            if ($this->config['url_prefix_required'] && !$this->cu->findFromStart($this->config['url_prefix'],$url)) return false;
            $url = $this->cu->replaceFromStart($this->config['url_prefix'],'',$url);
        }

        // Divide by delimiter (slash by default) - returns array
        $url_parts = explode($this->config['url_delimiter'], $url);
        if (empty($url_parts[0])) return false;
        $url = $url_parts[0];

        // Exit right away if required suffix is missing
        if (!empty($this->config['url_suffix'])) {
            if ($this->config['url_suffix_required'] && !$this->cu->findFromEnd($this->config['url_suffix'],$url)) return false;
            $url = $this->cu->replaceFromEnd($this->config['url_suffix'],'',$url);
        }
        /* Check if object exists and object is active */
        $target = urldecode($url);
        $object = $this->getObject('search_field', $target);
        if (!$object) {return false;}
        if (!empty($this->config['search_class_test_method']) && !$object->$this->config['search_class_test_method']()) {return false;}
        $object_id = $object->get($this->config['search_result_field']);
        if (empty($object_id)) return false;
        $landing_resource_id = $this->config['landing_resource_id'];  // ToDo: check resource exists?

        /* check for a object sub-action if URL in the form of "object/action" */
        $object_action = null;
        $action_map = $this->config['action_map'];
        if (!empty($action_map) && count($url_parts) > 1) {
            // action is the part after the delimiter
            $url_action = $this->cu->replaceFromStart($url_parts[0],'',$url);
            $landing_resource_id = false;
            // Parse the wall_action_ids
            foreach ($action_map as $action_name => $resource_id) {
                /* if match is found, set redirect to proper resource */
                if (!empty($action_name) && !empty($resource_id) && !empty($url_action) && $url_action == $action_name) {
                    $landing_resource_id = (int) $resource_id;
                    $object_action = $action_name;
                    break;
                }
            }
            if (!$object_action || !$landing_resource_id) return false;
        }
        if ($object_id) {
            $this->setData('object',$object);
            $this->setData('object_id',$object_id);
        }
        if (strval($object_action)) {
            $this->setData('action',$object_action);
        }
        return true;
    }
    /**
     * Generates a URL for a particular schema
     * @param string $field_name_config_key The configuration key that holds the name of the field to search by in the database table
     * @param string $field_value The value that $field should be equal to
     * @return null|object The object found
     */
    public function getObject($field_name_config_key,$field_value) {
        $object = $this->modx->getObject($this->config['search_class'],array_merge(array(
            $this->config[$field_name_config_key] => $field_value,
        ),$this->config['search_where']));
        if (!($object instanceof $this->config['search_class'])) return null;
        return $object;
    }
    /**
     * loads the service for this schema
     * @return object The object fetched from the modx sercice
     */
    public function loadService() {
        $load_modx_service_object = $this->modx->getService($this->config['load_modx_service']['name'],$this->config['load_modx_service']['class'],$this->modx->getOption($this->config['load_modx_service']['package'].'.core_path',null,$this->modx->getOption('core_path').'components/'.$this->config['load_modx_service']['package'].'/').'model/'.$this->config['load_modx_service']['package'].'/',$this->config['load_modx_service']['config']);
        if (!($load_modx_service_object instanceof $this->config['load_modx_service']['class'])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not load FURL custom service: '.$this->config['load_modx_service']['name']);
            return null;
        }
        return $load_modx_service_object;
    }
    /**
     * Generates a URL for a particular schema
     * @param string $object An instance of the object
     * @param string $action The optional action for the url
     * @return string The url
     */
    public function makeUrl($object,$action = '') {
        if (!($object instanceof $this->config['search_class'])) return '';
        $alias = urlencode($object->get($this->config['search_field']));
        $output = $this->config['base_url'].$this->config['url_prefix'].$alias.$this->config['url_suffix'];
        if (!empty($action) && $this->validateAction($action)) {
            $output .= $this->config['url_delimiter'].$action;
        }
        return $output;
    }
    /**
     * Checks that the action is in the action map for this scheme
     * @param string $action An instance of the object
     * @return bool True if successful
     */
    public function validateAction($action) {
        $success = array_key_exists($action,$this->config['action_map']);
        return $success;
    }

    /**
     * gets the REQUEST key for the schema
     * @param string $type The type of the request key
     * @return string True if successful
     */
    public function getRequestKey($type = 'id') {
        $output = isset($this->config['request_name_'.$type]) ? $this->config['request_prefix']. $this->config['request_name_'.$type] : '';
        return $output;
    }
}