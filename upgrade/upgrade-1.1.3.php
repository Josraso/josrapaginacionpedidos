<?php
/**
 * josrapaginacionpedidos 1.1.3: aviso de licencia: el cartel con el botón sale desde la primera vez.
 *
 * @author josra
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_3($module)
{
    return $module->getUpdater()->registerHooks();
}
