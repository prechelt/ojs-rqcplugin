<?php

/**
 * @defgroup plugins_generic_reviewqualitycollector Review Quality Collector Plugin
 */

/**
 * @file plugins/generic/reviewqualitycollector/index.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @ingroup plugins_generic_reviewqualitycollector
 * @brief Wrapper for reviewqualitycollector plugin.
 *
 */

require_once('RqcPlugin.inc.php');

return new RqcPlugin();

?>
