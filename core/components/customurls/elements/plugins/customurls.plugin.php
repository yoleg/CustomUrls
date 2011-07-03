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
 * Action url: [[++base_url]][[+prefix]]databaseSearchField[[+suffix]][[+delimiter]]action-or-child-schema
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

// get the event name
switch($event) {
    case 'OnPageNotFound':          // as soon as URL did not match any existing resource aliases
        $alias = $_SERVER['REQUEST_URI'];
        $base_url = $modx->getOption('base_url');
        $alias = $customurls->replaceFromStart($base_url,'',$alias);    // removes base_url - but only if found in beginning of URL
        $alias = strtok($alias, "?");                                   // grabs everything before the first '?'
        $schema = $customurls->parseUrl($alias);
        if (false) $schema = new cuSchema($customurls,array());         // for debugging only - never happens
        if (!($schema instanceof cuSchema)) return '';
        $schema->setRequest();                                          // Adds data to the $_REQUEST parameter
        $landing = (int) $schema->getLanding();                         // prefers child landings over parent landings
        $customurls->setCurrentSchema($schema);                         // saves for use by later events
        $modx->sendForward($landing);
        break;

    case 'OnLoadWebDocument' :      // after resource object loaded but before it is processed
        $validation = $customurls->validateCurrentSchema();             // if redirect is required, returns 'redirect'. Sets current schema to null if failed validation
        switch($validation) {
            case 'pass': break;
            case 'fail': return '';
            case 'redirect': $modx->sendErrorPage();
            default: return '';
        }
        $schema = $customurls->getCurrentSchema();                      // gets the schema set by the OnPageNotFound event
        if (empty($schema) || !($schema instanceof cuSchema)) return '';
        // set placeholders
        $set_ph = $schema->get('set_placeholders');
        if ($set_ph) {
            $placeholders = $schema->toArray();
            $modx->setPlaceholders($placeholders);
        }
        return '';

    case 'OnWebPagePrerender' :     // after output is processed
        // die();
        $schema = $customurls->getCurrentSchema();                      // gets the schema set by the OnPageNotFound event
        if (empty($schema) || !($schema instanceof cuSchema)) return '';
        // get the old (resource) and new (object) url
        $resource_url = $modx->makeUrl($modx->resource->get('id'),'','');
        $new_url = $schema->makeUrl();
        // exchange the output string with the replaced one
        $output = $modx->resource->_output;
        $output = str_replace($resource_url,$new_url,$output);
        $output = $schema->customSearchReplace($output);
        $modx->resource->_output = $output;
        break;
    default:
        break;
}
return '';
