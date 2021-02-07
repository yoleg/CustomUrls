<?php
/**
 * CustomUrls build script
 *
 * @license GPL v.3 or later
 * @package CustomUrls
 * @subpackage build
 */
/* start timestamp */
$tstart = explode(" ", microtime());

/* define package */
/* Set package info be sure to set all of these */
define('PKG_NAME','CustomUrls');
define('PKG_NAME_LOWER','customurls');
define('PKG_VERSION','1.3.1');
define('PKG_RELEASE','beta');
define('PKG_CATEGORY','CustomUrls');

/* load config and required classes */
$root = dirname(dirname(__FILE__)).'/';
$build_root = $root .'_build/';
require_once ($build_root.'build.config.php');
require_once ($build_root.'includes/transporter.class.php');
require_once MODX_CORE_PATH.'model/modx/modx.class.php';

/* start of work - get MODx, create Transporter class and process */
try {
    $modx= new modX();
    $transporter = new Transporter($modx);
    /* Transporter package-specific configuration. */
    $config = array(
        'has' => array(
            'Assets' => true,
            'Core' => true,
            'Packages' => false, // copies entire packages directory into backup! Not good.
            'Settings' => true,
            'Chunks' => true,
            'Snippets' => true,
            'Plugins' => true,
            'Menus' => true,
            'SetupOptions' => true,
            'Validator' => true, // checks for installed packages
            'Resolvers' => true,
            /* not yet working */
            'AccessPolicies' => false,
            'PolicyTemplates' => false,
            /* does not have */
            'Resources' => false,
            'Templates' => false,
            'PropertySets' => false,
            'TemplateVars' => false,
        ),
        'sources' => array(
            'root' => $root,
            'build' => $build_root,
        ),
        'attributes' => array(
            'license' => 'GPL version 3 or (at your option) any later version of GPL (http://www.gnu.org/licenses/gpl.html). A copy of the GPL v. 3 license is included in core/components/'.PKG_NAME_LOWER.'/docs/license.txt.',
        ),
        'resolvers' => array(
            'resources.resolver.php',
            'system_settings.resolver.php',
            'finish.resolver.php',
        ),
        'files_config' => array(),
        'default_attributes' => array(),
        'category_attributes' => array(
            xPDOTransport::UPDATE_OBJECT => true,
        ),
        'object_attributes' => array(
			'Settings' => array(
				'attributes' => array(
				   xPDOTransport::UPDATE_OBJECT => true,
				),
		    ),
            'Plugins' => array(
                'attributes' => array(
                    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
                        'PluginEvents' => array(
                            xPDOTransport::UPDATE_OBJECT => true,
                        ),
                    ),
                ),
            ),
        ),
    );
    $transporter->setConfig($config);
    $transporter->run();
} catch(Exception $e) {
    echo "\n\n<br /><br />EXECUTION FAILED: ".$e->getMessage();
    exit();
}
/* end timestamp */
$tend = explode(" ", microtime());
$totalTime= (($tend[1] + $tend[0]) - ($tstart[1] + $tstart[0]));
$totalTime= sprintf("%2.4f s", $totalTime);
$transporter->logInfo("\n<br />Package Built.<br />\nExecution time: {$totalTime}\n");
exit ();
