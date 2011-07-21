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
     * @var object A reference to the MODx service object that needs to be loaded.
     */
    public $service = null;
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
        // calculate children
        $children = $this->config['child_schemas'];
        $processed_children = array();
        if (!empty($children)) {
            foreach ($children as $child => $child_config) {
                if (is_numeric($child)) {
                    $child = $child_config;                          // allow simple array of schema names
                    $child_config = array();
                }
                if (!isset($customurls->unprocessed[$child])) continue;
                $child_config = array_merge($customurls->defaults,$customurls->unprocessed[$child],$child_config);
                if ($this->get('children_inherit_landing')) {
                    $child_config['landing_resource_id'] = $this->get('landing_resource_id');
                }
                $child_landing = $child_config['landing_resource_id'];
                if (empty($child_landing)) continue;
                $customurls->addLanding($child_landing,$this->key);   // parent is registered as the owner of the landing page
                $child_config['key'] = $child;
                // create the child objects
                $processed_children[$child] = new cuSchema($customurls,$child_config);
            }
        }
        if (!empty($this->config['action_map']) && is_array($this->config['action_map'])) {
            foreach ($this->config['action_map'] as $action_name => $resource_id) {
                /* if match is found, set redirect to proper resource */
                if (!empty($action_name) && !empty($resource_id)) {
                    $resource_id = (int) $resource_id;
                    $this->cu->addLanding($resource_id,$this->get('key'));
                }
            }
        }
        if (!empty($processed_children)) $this->children = $processed_children;
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
        $trimchars = ' /';
        $url = trim($url,$trimchars);
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
        $url = trim($url,$trimchars);
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
        $parsed_url = $prefix.$url_parts[0];
        $this->setData('parsed_url',$parsed_url);
        $remainder = $this->cu->replaceFromStart($parsed_url,'',$original_url);
        $this->setData('remainder',$remainder);
        $children = $this->children;
        $haschild = false;
        if (!empty($children) && !empty($remainder)) {
            foreach ($children as $key => $child) {
                $haschild = $child->parseUrl($remainder);
                if ($haschild) {
                    $this->setData('child',$child);
                    break;
                }
            }
        }
        $action_map = $this->config['action_map'];
        $object_action = '';
        $remainder = $this->cu->replaceFromStart($this->config['url_delimiter'],'',$remainder);
        if (!$haschild && !empty($remainder) && !empty($action_map)) {
            // action is the part after the delimiter
            $url_action = trim($remainder,$trimchars);
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
            if (!$object_action || !$landing_resource_id) {
                $object_action = '';
            }
        }
        // if anything is left, return false in strict mode
        $strict = $this->get('strict');
        if ($strict && empty($object_action) && !$haschild && !empty($remainder)) {
            return false;
        }
        if ($object_id) {
            $this->setData('object',$object);
            $this->setData('resource',$landing_resource_id);
            $this->setData('object_id',$object_id);
        }
        if ($object_action) {
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
        $object_action = $this->getData('action');
        $object_id = $this->getData('object_id');
        $request = array();
        if ($object_id && $object) {
            $request[$prefix.$this->getRequestKey('id')] = $object_id;
        }
        if (strval($object_action)) {
            $request[$prefix.$this->getRequestKey('action')] = $object_action;
        }
        foreach ($request as $key => $value) {
            if ($this->get('set_get')) {
                $_GET[$key] = $value;
            }
            if ($this->get('set_request')) {
                $_REQUEST[$key] = $value;
            }
        }
        $child = $this->getData('child');
        if (!empty($child)) {
            $child->setRequest();
        }
        return true;
    }
    /**
     * Returns the last child in a currently active schema
     * E.g. if a URL has already been validated, if any children are set, will return the last child in the chain of descendants.
     * @return cuSchema The last descendant if found, or self if not.
     */
    public function getLastChild() {
        $child = $this->getData('child');
        if (!empty($child) && ($child instanceof cuSchema)) {
            return $child->getLastChild();
        }
        return $this;
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
     * Gets an instance of
     * @param string $field_name_config_key The configuration key that holds the name of the field to search by in the database table
     * @param string $field_value The value that $field should be equal to
     * @return null|object The object found
     */
    public function getObject($field_name_config_key,$field_value) {
        $this->loadService();
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
        $service = $this->service;
        if (!is_null($service)) return $service;
        $service_config = $this->config['load_modx_service'];
        if (!is_array($service_config) || empty($service_config)) return null;
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
     * Returns an array of schemas
     * @return array
     */
    public function getChildChain() {
        $chain = array($this);
        $child = $this->getData('child');
        if (!empty($child)) {
            $child_chain = $child->getChildChain();
            if (!empty($child_chain)) {
                $chain = array_merge($chain,$child_chain);
            }
        }
        return $chain;
    }
    /**
     * Generates a URL for a particular schema
     * If objects is left empty, tries to generate a URL to the current object chain (if this is the current schema on the page).
     * @param object|array $objects An instance of the object, or an array of objects (first will be used as main, all others as children)
     * @param string|array $actions The optional action for the url or array of actions (with the same order as the array of objects
     * @param array $children An optional array of child schemas (ordered by depth)
     * @return string The url
     */
    public function makeUrl($objects = null,$actions = '',array $children=array()) {
        // get the current object - remaining array of objects used as kids
        if (empty($objects)) {
            $object = $this->getData('object');
            if (empty($object)) return '';
            if (empty($children)) {
                $children = $this->getChildChain();
                $fakethis = !empty($children) ? array_shift($children) : '';
            }
            $objects = array($object);
            foreach ($children as $schema) {
                $child_object = $schema->getData('object');
                if (empty($child_object)) break;
                $objects[] = $child_object;
            }
        }
        if (empty($actions)) {
            $actions = $this->getData('action','');
        }
        $object = (is_array($objects)) ? array_shift($objects) : $objects;
        if (!($object instanceof $this->config['search_class'])) return '';
        // set the action
        $action = (is_array($actions) && !empty($actions)) ? array_shift($actions) : $actions;
        // if any are left, save them to pass onto kids
        if (!is_array($actions) || empty($actions)) $actions = '';
        // generate the current url portion
        $alias = urlencode($object->get($this->config['search_field']));
        $url = $this->config['base_url'].$this->config['url_prefix'].$alias.$this->config['url_suffix'];
        $next_url = '';
        $delimiter = $this->get('url_delimiter');
        // figure out children
        $child = null;
        if (!empty($objects) && is_array($objects)) {
            $child = empty($children)  ? $this->getData('child') : array_shift($children);
            // auto-detect child schema from object if empty
            if (empty($child) || !($child instanceof cuSchema)) {
                $child_objects = $objects;
                $child_object = array_shift($child_objects);
                $poss_child_schemas = $this->children;
                foreach ($poss_child_schemas as $schema) {
                    $test_class = $schema->get('search_class');
                    if ($child_object instanceof $test_class) {
                        $child = $schema;
                        break;
                    }
                }
            }
        }
        if ($child instanceof cuSchema) {
            $next_url .= $delimiter.$child->makeUrl($objects,$actions,$children);
        } elseif (!empty($action) && $this->validateAction($action)) {
            $next_url .= $delimiter.$this->config['url_delimiter'].$action;
        }
        $url = $url.$next_url;
        $url = str_replace($delimiter.$delimiter,$delimiter,$url);
        return $url;
    }
    /**
     * Tries to auto-detect the current object from the REQUEST parameters
     * @return null|object The object if found or null if not
     */
    public function detectObjectFromParams() {
        $param = $this->getRequestKey('id');
        if (empty($param)) return null;

        if (!isset($_REQUEST[$param])) return array();
        $object = $this->getObject('search_result_field', $_REQUEST[$param]);
        if (empty($object)) return null;
        return $object;
    }
    /**
     * Tries to auto-detect the current action from the REQUEST parameters
     * @return array|string|xPDOIterator
     */
    public function detectActionFromParams() {
        $param = $this->getRequestKey('action');
        if (empty($param)) return '';
        $action =  isset($_REQUEST[$param]) ? $_REQUEST[$param] : '';
        return $action;
    }
    /**
     * Tries to auto-detect if this schema (and which of its children) is being used on the page
     * If so, returns an array of arrays of information about each schema ('schema' holds the schema object,'object' holds the object, and 'action' holds the action).
     * The first array describes the current schema, and each subsequent array describes the descendants, in the order they appear in the chain.
     * @return array Array of arrays or empty array if no match. Array structure: array(array('schema','object','action'),array('first_child_schema','object','action'),...)
     */
    public function detectFromParams() {
        $info = $this->getData('params_info');
        if (!empty($info)) return $info;
        $info = array();
        $param = $this->getRequestKey('id');
        if (empty($param)) return array();
        if (!isset($_REQUEST[$param])) return array();
        $object = $this->detectObjectFromParams();
        if (empty($object)) return array();
        $action = $this->detectActionFromParams();
        $info[] = array(
            'schema' => $this,
            'object' => $object,
            'action' => $action,
        );
        $children = $this->children;
        foreach ($children as $child) {
            $child_info = $child->detectFromParams();
            if (!empty($child_info)) {
                $info = array_merge($info,$child_info);
                break;
            }
        }
        $this->setData('params_info',$info);
        return $info;
    }
    /**
     * Attempts to generate a URL from the request parameters detected on the page
     * @param array $get The array of GET values to append to the URL (uses $_GET by default)
     * @return string The url or empty if not found
     */
    public function makeUrlFromParams(array $get = array()) {
        $info_array = $this->detectFromParams();
        if (empty($info_array) || !is_array($info_array)) return '';
        $objects = array();
        $actions = array();
        foreach ($info_array as $key => $info) {
            $objects[] = $info['object'];
            $actions[] = $info['action'];
        }
        $url = $this->makeUrl($objects,$actions);
        if (empty($url)) return '';
        // prep the array of reserved parameters that should not be added to the URL
        $unset_params = array();
        if (empty($get)) {
            // Get the current GET parameters to append to the URL
            $get = $_GET;
            // Remove MODx reserved parameters
            $unset_params[] = $this->modx->getOption('request_param_alias');
            $unset_params[] = $this->modx->getOption('request_param_id');
        }
        // Remove params used byt he schema and children
        $schema_params = $this->getUsedParamsFromParams();
        $unset_params = array_merge($schema_params,$unset_params);
        foreach ($unset_params as $param) {
            if (isset($get[$param])) unset($get[$param]);
        }
        $delimiter = '?';
        foreach ($get as $key => $value) {
            $url = $url.$delimiter.$key.'='.$value;
            $delimiter = '&';
        }
        return $url;
    }
    
    /**
     * Tries to auto-detect the GET parameters that are being used from the request parameters detected on the page
     * @return array An array of GET parameter keys
     */
    public function getUsedParamsFromParams() {
        $info_array = $this->detectFromParams();
        $unset_params = array();
        foreach ($info_array as $key => $info) {
            $schema = $info['schema'];
            $unset_params[] = $schema->getRequestKey('id');
            $unset_params[] = $schema->getRequestKey('action');
        }
        $unset_params = array_unique($unset_params);
        return $unset_params;
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