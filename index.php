<?php

/**
 * @defgroup plugins_generic_reviewqualitycollector Review Quality Collector Plugin
 */
 
/**
 * @file plugins/generic/reviewqualitycollector/index.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_reviewqualitycollector
 * @brief Wrapper for reviewqualitycollector plugin.
 *
 */
require_once('RQCPlugin.inc.php');

return new RQCPlugin();

?>
