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
if (!$friendly_urls)    return '';

// load the custom urls service
$customurls = $modx->getService('customurls','customUrls',$modx->getOption('customurls.core_path',null,$modx->getOption('core_path').'components/customurls/').'model/customurls/',$scriptProperties);
if (!($customurls instanceof customUrls)) {
    $customurls = new customUrls($modx,array());
}
$schema_param = $modx->getOption('customurls.schema_param_name',null,'customurls_schema_name');
$validated_param = $modx->getOption('customurls.validated_param_name',null,'customurls_validated');

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
        if (!($schema instanceof cuSchema)) {
            return '';
            $schema = new cuSchema($customurls,array());        // for debugging
        }
        $object = $schema->getData('object');
        $object_action = $schema->getData('object_action');
        $object_id = $schema->getData('object_id');
        if ($object_id && $object) {
            $_REQUEST[$schema->getRequestKey('id')] = $object_id;
            $_REQUEST[$schema_param] = $schema->get('key');
        }
        if (strval($object_action)) {
            $_REQUEST[$schema->getRequestKey('action')] = $object_action;
        }
        $landing = (int) $schema->getData('resource');
        $customurls->setCurrentSchema($schema);
        $modx->sendForward($landing);
        break;

    case 'OnLoadWebDocument' :      // after resource object loaded but before it is processed
        return '';
        $customurls->validateCurrentSchema();       // may redirect to errorPage and/or set current schema to null

        $schema = $customurls->getCurrentSchema();
        if (empty($schema) || !($schema instanceof cuSchema)) return '';
        $config = $schema->config;
        $object = $schema->getData('object');
        // set placeholders
        $ph_prefix = !empty($config['placeholder_prefix']) ? $config['placeholder_prefix'].'.' : '';
        if ($config['set_placeholders']) {
            $placeholders = array();
            $placeholders[$config['display_placeholder']] = '';
            $display_field = !empty($config['search_display_field']) ? $config['search_display_field'] : $config['search_field'];
            $placeholders[$config['display_placeholder']] = $object->get($display_field);
            $object_array = $object->toArray();
            $placeholders = array_merge($placeholders,$object_array);
            $modx->setPlaceholders($placeholders,$ph_prefix);
            // ToDo: set action placeholders (with verification that action exists)
        }
        return '';

    case 'OnWebPagePrerender' :     // after output is processed
        // die();
        return '';
        $schema = $customurls->getCurrentSchema();
        if (empty($schema) || !($schema instanceof cuSchema)) return '';
        $config = $schema->config;
        $object = $schema->getData('object');
        // get the old (resource) and new (object) url
        $resource_url = $modx->makeUrl($modx->resource->get('id'),'','');
        $new_url = $schema->makeUrl($object);
        // exchange the output string with the replaced one
        $output = $modx->resource->_output;
        $output = str_replace($resource_url,$new_url,$output);
        if (!empty($config['custom_search_replace'])) {
            foreach($config['custom_search_replace'] as $search => $replace) {
                $output = str_replace($search,$replace,$output);
            }
        }
        $modx->resource->_output = $output;
        break;
    default:
        break;
}
return '';
