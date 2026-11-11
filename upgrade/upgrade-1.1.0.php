<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    foreach (Josrapaginacionpedidos::defaults() as $k => $v) {
        if (Configuration::get($k) === false) {
            Configuration::updateValue($k, $v);
        }
    }
    $ok = true;
    if (!$module->isRegisteredInHook('displayBackOfficeHeader')) {
        $ok = $module->registerHook('displayBackOfficeHeader');
    }

    return $module->getUpdater()->registerHooks() && $ok;
}
