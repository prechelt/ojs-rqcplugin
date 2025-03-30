<?php

/**
 * @defgroup plugins_generic_rqc Review Quality Collector Plugin
 */

/**
 * @file    plugins/generic/rqc/index.php
 *
 * Copyright (c) 2018-2023 Lutz Prechelt
 * Distributed under the GNU General Public License, Version 3.
 *
 * @ingroup plugins_generic_rqc
 * @brief   Wrapper for rqc plugin.
 *
 */

require_once('RqcPlugin.inc.php');

return new RqcPlugin();

?>
