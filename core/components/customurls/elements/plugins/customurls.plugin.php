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
// for debugging only - never happens
/**
 * @var MODx $modx
 * @var customUrls $customurls
 * @var cuSchema $schema
 * @var array $scriptProperties
 */

if ($modx->context->get('key') == 'mgr') {return;}

// check event in allowed events
$event = $modx->event->name;
$events = array('OnPageNotFound','OnLoadWebDocument','OnWebPagePrerender');
if (!in_array($event,$events)) return '';

// Do not parse if friendly urls are off
$friendly_urls = $modx->getOption('friendly_urls',null,false);
if (!$friendly_urls)    return '';

// load the custom urls service
$customurls = $modx->getService('customurls','customUrls',$modx->getOption('customurls.core_path',null,$modx->getOption('core_path').'components/customurls/').'model/customurls/',$scriptProperties);
if (!($customurls instanceof customUrls)) {
    $modx->log(modX::LOG_LEVEL_ERROR,'[customurls plugin] Could not load customurls service.');
    return '';
}

if (!$customurls->canUse()) return '';

// get the event name
switch($event) {
    case 'OnPageNotFound':          // as soon as URL did not match any existing resource aliases
        $alias = $_SERVER['REQUEST_URI'];
        $base_url = $modx->getOption('base_url');
        $alias = $customurls->replaceFromStart($base_url,'',$alias);    // removes base_url - but only if found in beginning of URL
        $alias = strtok($alias, "?");                                   // grabs everything before the first '?'
        $schema = $customurls->parseUrl($alias);
        if (!($schema instanceof cuSchema)) break;
        $schema->setRequest();                                          // Adds data to the $_REQUEST parameter
        $landing = (int) $schema->getLanding();                         // prefers child landings over parent landings
        $customurls->setCurrentSchema($schema);                         // saves for use by later events
        $modx->sendForward($landing);
        break;

    case 'OnLoadWebDocument' :      // after resource object loaded but before it is processed
        // Validate if a schema has been detected by the 404 event.
        // If redirect is required, returns 'redirect'. Sets current schema to null if failed validation
        $validation = $customurls->validateCurrentSchema();
        if ($validation === 'fail' || $validation === 'redirect') {
            // Try to auto-detect URL from REQUEST params and redirect to the friendly URL
            $url = $customurls->detectUrlFromRequest();
            if (!empty($url)) {
                $modx->sendRedirect($url);
            }
            // Redirect to error page if required
            if ($validation === 'redirect') {
                $customurls->disable();  // disable execution for any subsequent events
                $modx->sendErrorPage();
            }
        }
        if ($validation !== 'pass') break;
        $schema = $customurls->getCurrentSchema();                      // gets the schema set by the OnPageNotFound event
        if (empty($schema) || !($schema instanceof cuSchema)) break;
        // set placeholders
        $set_ph = $schema->get('set_placeholders');
        if ($set_ph) {
            $placeholders = $schema->toArray();
            $modx->setPlaceholders($placeholders);
        }
        break;

    case 'OnWebPagePrerender' :     // after output is processed
        // die();
        $schema = $customurls->getCurrentSchema();                      // gets the schema set by the OnPageNotFound event
        if (empty($schema) || !($schema instanceof cuSchema)) break;
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
