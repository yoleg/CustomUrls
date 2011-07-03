<?php
/*
 * cuScheme
 * Represents a single URL scheme.
 * 
 * @uses HTTP_UPLOAD
 * @package ugmedia
 * @subpackage ugmedia
 */
class cuSchema {
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
    public  $config = array();
    /**
     * @access protected
     * @var string The unique schema identifier
     */
    protected  $key = '';
    /**
     * @access public
     * @var array Stores the data found for this schema for the active resource
     */
    protected  $data = array();
    /**
     * @access public
     * @var array Stores the child schema objects of class cuSchema
     */
    public   $children = array();
    /**
     * The cuScheme Constructor.
     *
     * This method is used to create a new cuScheme object.
     *
     * @param customUrls $customurls A reference to the customUrls object.
     * @param array $config A collection of properties that modify cuScheme
     * behaviour.
     * @return cuSchema A unique cuScheme instance.
     */
    function __construct(customUrls &$customurls,array $config = array()) {
        $this->cu =& $customurls;
        $this->modx =& $customurls->modx;
        if (false) {
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
        $original_url = $url;
        // Load ugm class
        // ToDO: add error checking
        if (!empty($this->config['load_modx_service'])) {
            $service = $this->loadService();
            if (!$service) return false;
        }
        // URL = prefix.alias.suffix.delimiter.remainder
        // Exit right away if required prefix is missing
        if (!empty($this->config['url_prefix'])) {
            if ($this->config['url_prefix_required'] && !$this->cu->findFromStart($this->config['url_prefix'],$url)) return false;
            $url = $this->cu->replaceFromStart($this->config['url_prefix'],'',$url);
        }

        // URL = alias.suffix.delimiter.remainder
        // Divide by delimiter (slash by default) - returns array
        $url = trim($url,' /');
        $url_parts = explode($this->config['url_delimiter'], $url);
        if (empty($url_parts[0])) return false;
        $url = $url_parts[0];


        // URL = alias.suffix
        // Exit right away if required suffix is missing
        if (!empty($this->config['url_suffix'])) {
            if ($this->config['url_suffix_required'] && !$this->cu->findFromEnd($this->config['url_suffix'],$url)) return false;
            $url = $this->cu->replaceFromEnd($this->config['url_suffix'],'',$url);
        }
        // URL = alias
        // URL_Remainder = alias
        /* Check if object exists and object is active */
        $target = urldecode($url);
        $object = $this->getObject('search_field', $target);
        if (!$object) {return false;}
        $method = $this->config['search_class_test_method'];
        if (!empty($method) && !($object->$method())) {
            return false;
        }
        $object_id = $object->get($this->config['search_result_field']);
        if (empty($object_id)) return false;
        $landing_resource_id = $this->config['landing_resource_id'];  // ToDo: check resource exists?

        /* process remainder */
        $object_action = null;
        $prefix = !empty($this->config['url_prefix']) ? $this->config['url_prefix'] : '';
        $remainder = $this->cu->replaceFromStart($prefix.$url_parts[0],'',$original_url);
        $this->setData('remainder',$remainder);
        $children = $this->children;
        if (!empty($children) && !empty($remainder)) {
            foreach ($children as $key => $child) {
                $success = $child->parseUrl($remainder);
                if ($success) {
                    $this->setData('child',$child);
                    break;
                }
            }
        }
        $action_map = $this->config['action_map'];
        if (!empty($action_map) && !empty($remainder)) {
            // action is the part after the delimiter
            $url_action = $remainder;
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
            $this->setData('resource',$landing_resource_id);
            $this->setData('object_id',$object_id);
        }
        if (strval($object_action)) {
            $this->setData('action',$object_action);
        }
        return true;
    }
    /**
     * Sets the main object identifier and action to the REQUEST
     * @param string $prefix An optional prefix to add.
     * @return bool True
     */
    public function setRequest($prefix = '') {
        $object = $this->getData('object');
        $object_action = $this->getData('object_action');
        $object_id = $this->getData('object_id');
        if ($object_id && $object) {
            $_REQUEST[$prefix.$this->getRequestKey('id')] = $object_id;
        }
        if (strval($object_action)) {
            $_REQUEST[$prefix.$this->getRequestKey('action')] = $object_action;
        }
        $child = $this->getData('child');
        if (!empty($child)) {
            $child->setRequest();
        }
        return true;
    }
    /**
     * Returns placeholders.
     * The object fields are prefixed with the schema name.
     * @param string $prefix An optional prefix to add
     * @return array An array of placeholder keys and values
     */
    public function toArray($prefix = '') {
        $config = $this->config;
        // set prefix
        $ph_prefix = !empty($config['placeholder_prefix']) ? $config['placeholder_prefix'].'.' : '';
        $prefix = $ph_prefix.$prefix;
        // object to array
        if (false) $object = new xPDOSimpleObject($this->modx);     // debug only
        $object = $this->getData('object');
        $array = $object->toArray($prefix.$this->get('key').'.');
        // Set the display placeholder
        $display_field = !empty($config['search_display_field']) ? $config['search_display_field'] : $config['search_field'];
        $array[$prefix.$config['display_placeholder']] = $object->get($display_field);
        // merge with children
        $child = $this->getData('child');
        if (!empty($child)) {
            $child_array = $child->toArray();
            $array = !empty($child_array) ? array_merge($array,$child_array): array();
        }
        return $array;
    }
    /**
     * Returns the landing resource (prefers child landings over parent landings)
     * @return int The landing resource URL
     */
    public function getLanding() {
        $landing = (int) $this->getData('resource');
        $child = $this->getData('child');
        if (!empty($child)) {
            $child_landing = $child->getLanding();
            $landing = !empty($child_landing) ? $child_landing : $landing;
        }
        return $landing;
    }
    /**
     * Recursively runs the search-replace if the custom_search_replace is set for this or child schemas
     * @param string $input The input string
     * @return string The output string
     */
    public function customSearchReplace($input) {
        $output = $input;
        if (!empty($this->config['custom_search_replace'])) {
            foreach($this->config['custom_search_replace'] as $search => $replace) {
                $output = str_replace($search,$replace,$output);
            }
        }
        $child = $this->getData('child');
        if (!empty($child)) {
            $output = $child->customSearchReplace($output);
        }
        return $output;
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
     * @return object The object fetched from the modx service
     */
    public function loadService() {
        $service_config = $this->config['load_modx_service'];
        if (!is_array($service_config)) return null;
        $name = $service_config['name'];
        $class = $service_config['class'];
        $package = $service_config['package'];
        $config = $service_config['config'];
        $path = $service_config['path'];
        $path = !empty($path) ? $path : $this->modx->getOption($package.'.core_path',null,$this->modx->getOption('core_path').'components/'.$package.'/').'model/'.$package.'/';
        $service = $this->modx->getService($name,$class,$path,$config);
        if (!($service instanceof $class)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not load FURL custom service: '.$this->config['load_modx_service']['name']);
            return null;
        }
        return $service;
    }
    /**
     * Generates a URL for a particular schema
     * @param string|array $object An instance of the object, or an array of objects (first will be used as main, all others as children)
     * @param string $action The optional action for the url
     * @param array $children An array of child objects (ordered by depth)
     * @return string The url
     */
    public function makeUrl($object = null,$action = '',$children=array()) {
        if (empty($object)) {
            $object = $this->getData('object');
        }
        if (is_array($object)) {
            $children = $object;
            $object = array_shift($children);
        }
        if (!($object instanceof $this->config['search_class'])) return '';
        $alias = urlencode($object->get($this->config['search_field']));
        $output = $this->config['base_url'].$this->config['url_prefix'].$alias.$this->config['url_suffix'];
        $delimiter = $this->get('url_delimiter');
        $child = empty($children) ? $this->getData('child') : array_shift($children);
        if ($child instanceof cuSchema) {
            $output .= $delimiter.$child->makeUrl(null,$action,$children);
        }
        if (!empty($action) && $this->validateAction($action)) {
            $output .= $delimiter.$this->config['url_delimiter'].$action;
        }
        return $output;
    }
    /**
     * Checks that the action is in the action map for this schema
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