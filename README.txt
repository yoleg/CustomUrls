CustomUrls
Description: Create flexible, custom friendly urls for any number of database tables. Successor to UserUrls.
License: GPL v2 or later
Contact: oleg@websitezen.com
Starting date: June 30, 2011

Installation: 
* Copy and paste the plugin and set to the events 'OnPageNotFound','OnLoadWebDocument','OnWebPagePrerender'
* Add the system settings "customurls.schemas" and "customurls.defaults" with JSON arrays (see below)
* Read forum post: http://modxcms.com/forums/index.php/topic,66269.0.html

Configuration:
All of the settings for the urls are stored in two system settings in JSON format. I recommend you generate your own JSON from a PHP array in a snippet. 
For example:
return htmlentities($modx->fromJSON($php_array));

Structure of system setting "customurls.schemes" (allows multiple url schemes this way):
array(
    'scheme_name' => array(
        'setting' => 'value',
        'setting' => 'value',
        'setting' => 'value',
    ),
    'another_scheme_name' => array(
        'setting' => 'value',
        'setting' => 'value',
        'setting' => 'value',
    ),
    etc...
);

Structure of system setting "customurls.defaults" (values will be used if not otherwise specified):
array(
    'setting' => 'value',
    'setting' => 'value',
    'setting' => 'value',
);


Defaults:
    See the top of core/components/customurls/model/customurls/customurls.class.php for the most current defaults and an array of all possible settings.

Usage:
    Other than setting up the url schemas, there's not much to do...
    - Run the debug snippet to see the REQUEST parameters and placeholders available on the landing page
    - Unless you disable it, the original resource's URL will be automatically replaced everywhere on the page with the current CustomUrl. It's a good idea to set the resource's alias to something long and unusual to prevent regular words from being replaced. 
    - see forum for more usage tips: http://modxcms.com/forums/index.php/topic,66269.0.html

(Hopeful) Roadmap:
* Add helper snippets to link from other pages
* Add a JSON generator tool to the manager to generate URL schemes