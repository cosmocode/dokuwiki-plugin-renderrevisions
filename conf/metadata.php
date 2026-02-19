<?php

/**
 * Options for the renderrevisions plugin
 *
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */

$meta['maxfrequency'] = array('numeric', '_min' => 0);
$meta['store'] = array('onoff');
$meta['skipRegex'] = array('string');
$meta['matchRegex'] = array('string');
$meta['debug'] = array('onoff');
