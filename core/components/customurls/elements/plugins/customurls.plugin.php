<?php
/**
 * customUrls
 *
 * Copyright 2011 by Oleg Pryadko (websitezen.com)
 *
 * This file is part of customUrls, a flexible friendly-url component for MODx Revolution.
 * Revolution. This file is loosely based off of ArchivistFURL by Shaun McCormick and UserUrls by Oleg Pryadko.
 *
 * customUrls is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * customUrls is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * customUrls; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 * @package customurls
 */
/**
 * Url format: [[++base_url]][[+prefix]]databaseSearchField[[+suffix]]
 * Action url: [[++base_url]][[+prefix]]databaseSearchField[[+suffix]][[+delimiter]]action
 * ToDo: use RegExp or other method to add flexibility to URLs
*/
$event = $modx->event->name;

$events = array('OnPageNotFound','OnLoadWebDocument','OnWebPagePrerender');
if (!in_array($event,$events)) return '';

// Do not parse if friendly urls are off
$friendly_urls = $modx->getOption('friendly_urls',null,false);
if (empty($friendly_urls)) {
    return '';
}
// load the custom urls service
$customurls = $modx->getService('customurls','customUrls',$modx->getOption('customurls.core_path',null,$modx->getOption('core_path').'components/customurls/').'model/customurls/',$scriptProperties);
if (!($customurls instanceof customUrls)) {
    return '';
}
// see class file for defaults
$url_schemes = $customurls->getUrlSchemas();
$resource_may_redirect = array();
foreach ($url_schemes as $name => $config) {
    $resource_may_redirect[$config['landing_resource_id']] = isset($resource_may_redirect[$config['landing_resource_id']]) ? false : true;
}
    // get the event name
    switch($event) {
        case 'OnPageNotFound':
            $alias = $_SERVER['REQUEST_URI'];
            $base_url = $modx->getOption('base_url');
            $alias = $customurls->replaceFromStart($base_url,'',$alias);
            $alias = strtok($alias, "?");     // grabs everything before the first '?'
            $alias = trim($alias, '/');
            $wall_objectname = $alias;
            $schema = $customurls->parseUrl($alias);
            if (empty($schema)) return '';

            $object = $schema->getData('object');
            $object_action = $schema->getData('object_action');
            $object_id = $schema->getData('object_id');
            if ($object_id && $object) {
                $this->setData('object',$object);
                $this->setData('object_id',$object_id);
                $_REQUEST[$this->getRequestKey('id')] = $object_id;
                $scheme_param = $this->modx->getOption('customurls.scheme_param_name',null,'customurls_scheme_name');
                $_REQUEST[$scheme_param] = $this->key;
            }
            if (strval($object_action)) {
                $_REQUEST[$this->getRequestKey('action')] = $object_action;
            }
            $this->modx->sendForward($this->config['landing_resource_id']);

            /* pass variables to target resource as GET parameters */
            break;

        case 'OnLoadWebDocument' :      // after resource object loaded but before it is processed
            $redirector = isset($redirector) ? $redirector : true;
            $ph_prefix = !empty($config['placeholder_prefix']) ? $config['placeholder_prefix'].'.' : '';
            if ($config['set_placeholders']) {
                $placeholders = array();
                $placeholders[$config['display_placeholder']] = '';
            }
        case 'OnWebPagePrerender' :     // after output is processed
            // ToDO: pass variables along in a class or something
            $redirector = isset($redirector) ? $redirector : false;
            $redirect = false;
            $resource_id = $modx->resource->get('id');

            // only parse links on landing pages

            if ($resource_id != $config['landing_resource_id']) continue;
            if ($redirector && !$config['redirect_if_accessed_directly'] && !$config['redirect_if_object_not_found'] && !$config['replace_page_titles']) continue;
            // get the request info
            $request_param = $customurls->getRequestKey($url_scheme_name,'id');
            $objectid = isset($_REQUEST[$request_param]) ? $_REQUEST[$request_param] : 0;
            // get the object request refers to
            if (empty($objectid)) {
                if ($redirector && $config['redirect_if_accessed_directly']){
                    $redirect = true;
                }
                continue;
            }
            $object = $customurls->getObject($url_scheme_name,'search_result_field',$objectid);
            if (!$object) {
                if ($redirector && $config['redirect_if_object_not_found']) {
                    $redirect = true;
                }
                continue;
            }
            if ($redirector) {
                if ($config['set_placeholders']) {
                    $display_field = !empty($config['search_display_field']) ? $config['search_display_field'] : $config['search_field'];
                    $placeholders[$config['display_placeholder']] = $object->get($display_field);
                    $object_array = $object->toArray();
                    $placeholders = array_merge($placeholders,$object_array);
                    $modx->setPlaceholders($placeholders,$ph_prefix);
                    // ToDo: set action placeholders (with verification that action exists)
                }
                continue;   // no more processing needed for this event...
            }
            if (!empty($config['search_class_test_method']) && !$object->$config['search_class_test_method']()) {continue;}
            // get the old (resource) and new (object) url
            $resource_url = $modx->makeUrl($resource_id,'','');
            $new_url = $customurls->makeUrl($url_scheme_name,$object);
            // exchange the output string with the replaced one
            $output = $modx->resource->_output;
            $output = str_replace($resource_url,$new_url,$output);
            if (!empty($config['custom_search_replace'])) {
                foreach($config['custom_search_replace'] as $search => $replace) {
                    $output = str_replace($search,$replace,$output);
                }
            }
            $modx->resource->_output = $output;
            return '';
            if ($redirector && $redirect && $resource_may_redirect[$resource_id] == true) {
                $modx->sendErrorPage();
            }
            break;
        default:
            break;
}
return '';
