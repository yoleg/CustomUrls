<?php
/*
 * cuScheme
 * Represents a single URL scheme.
 * 
 * @uses HTTP_UPLOAD
 * @package ugmedia
 * @subpackage ugmedia
 */
include_once dirname(__FILE__).'/cuscheme.class.php';
class cuScheme {
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
     * @access public
     * @var string The unique schema identifier
     */
    public $key = '';
    /**
     * The cuScheme Constructor.
     *
     * This method is used to create a new cuScheme object.
     *
     * @param modX &$this->modx A reference to the modX object.
     * @param array $config A collection of properties that modify cuScheme
     * behaviour.
     * @return cuScheme A unique cuScheme instance.
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $this->config =& $config;
        $this->key = $config['key'];
    }

    /**
     * Parses a particular URL to see if it matches this schema
     * @return bool True if match found, false otherwise
     */
    public function parseUrl($url) {
        // Load ugm class
        // ToDO: add error checking
        if (!empty($config['load_modx_service'])) {
            $service = $customurls->loadService($url_scheme_name);
            if (!$service) continue;
        }
        /* handle redirects */
        /* remove the base url */

        // Exit right away if required prefix is missing
        if (!empty($config['url_prefix'])) {
            if ($config['url_prefix_required'] && !$customurls->findFromStart($config['url_prefix'],$wall_objectname)) return false;
            $wall_objectname = $customurls->replaceFromStart($config['url_prefix'],'',$wall_objectname);
        }

        // Divide by delimiter (slash by default) - returns array
        $url_parts = explode($config['url_delimiter'], $wall_objectname);
        if (empty($url_parts[0])) return false;
        $wall_objectname = $url_parts[0];

        // Exit right away if required suffix is missing
        if (!empty($config['url_suffix'])) {
            if ($config['url_suffix_required'] && !$customurls->findFromEnd($config['url_suffix'],$wall_objectname)) return false;
            $wall_objectname = $customurls->replaceFromEnd($config['url_suffix'],'',$wall_objectname);
        }
        /* Check if object exists and object is active */
        $target = urldecode($wall_objectname);
        $object = $customurls->getObject($url_scheme_name,'search_field', $target);
        if (!$object) {return false;}
        if (!empty($config['search_class_test_method']) && !$object->$config['search_class_test_method']()) {return false;}
        $object_id = $object->get($config['search_result_field']);
        if (empty($object_id)) return false;
        $landing_resource_id = $config['landing_resource_id'];  // ToDo: check resource exists?

        /* check for a object sub-action if URL in the form of "object/action" */
        $object_action = null;
        $action_map = $config['action_map'];
        if (!empty($action_map) && count($url_parts) > 1) {
            // action is the part after the delimiter
            $url_action = $customurls->replaceFromStart($url_parts[0],'',$alias);
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
            $_REQUEST[$customurls->getRequestKey($url_scheme_name,'id')] = $object_id;
            $scheme_param = $modx->getOption('customurls.scheme_param_name',null,'customurls_scheme_name');
            $_REQUEST[$scheme_param] = $url_scheme_name;
        }
        if (strval($object_action)) {
            $_REQUEST[$customurls->getRequestKey($url_scheme_name,'action')] = $object_action;
        }
        $modx->sendForward($config['landing_resource_id']);
        return true;
    }
    /**
     * Generates a URL for a particular schema
     * @param string $schema_name
     * @param string $field_name_config_key The configuration key that holds the name of the field to search by in the database table
     * @param string $value The value that $field should be equal to
     */
    public function getObject($schema_name,$field_name_config_key,$field_value) {
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
    public function makeUrl($schema_name,$object,$action = '') {
        $schemas = $this->getUrlSchemas();
        $schema = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($schema)) return '';
        if (!($object instanceof $schema['search_class'])) return '';
        $alias = urlencode($object->get($schema['search_field']));
        $output = $schema['base_url'].$schema['url_prefix'].$alias.$schema['url_suffix'];
        if (!empty($action) && $this->validateAction($schema_name,$action)) {
            $output .= $schema['url_delimiter'].$action;
        }
        return $output;
    }
    public function validateAction($schema_name,$action) {
        $schemas = $this->getUrlSchemas();
        $schema = isset($schemas[$schema_name]) ? $schemas[$schema_name] : '';
        if (empty($schema)) return '';
        $success = array_key_exists($action,$schema['action_map']);
        return $success;
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
}