<?php
/**
 * CustomUrls
 *
 * Copyright 2011 by Oleg Pryadko (websitezen.com)
 *
 * This file is part of CustomUrls, a quick-start site package for MODx Revolution.
 *
 * CustomUrls is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * CustomUrls is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * CustomUrls; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package CustomUrls
 */
/**
 * @package CustomUrls
 * @subpackage build
*/
$prefix = PKG_NAME_LOWER;
require_once (dirname(dirname(dirname(__FILE__))).
        '/core/components/customurls/model/customurls/customurls.class.php');
$install_settings = array();
$upgrade_settings = array();

foreach(CUSTOMURLS_DEFAULTS as $key => $value) {
    $install_settings[CUSTOMURLS_DEFAULTS_PREFIX.$key] = $value;
}

$settings = array(
    'install' => $install_settings,
    'upgrade' => $upgrade_settings,
);
return $settings;
