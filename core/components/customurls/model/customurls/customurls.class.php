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
     * @var string The current url scheme for the resource
     */
    protected $scheme = '';

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