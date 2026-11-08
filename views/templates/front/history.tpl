{*
 * Historial de pedidos paginado: usa la plantilla de historial del tema y le añade buscador y paginación.
 *}
{extends file='customer/history.tpl'}

{block name='page_content'}
  <form method="get" action="{$josrapp.action|escape:'html':'UTF-8'}" class="josrapp-toolbar">
    <input type="text" name="q" class="form-control" value="{$josrapp.q|escape:'html':'UTF-8'}" placeholder="{l s='Buscar por referencia o nº de pedido' mod='josrapaginacionpedidos'}">
    {if $josrapp.years|count > 1}
      <select name="year" class="form-control">
        <option value="">{l s='Todos los años' mod='josrapaginacionpedidos'}</option>
        {foreach $josrapp.years as $y}<option value="{$y|intval}"{if $josrapp.year == $y} selected{/if}>{$y|intval}</option>{/foreach}
      </select>
    {/if}
    <button type="submit" class="btn btn-primary">{l s='Buscar' mod='josrapaginacionpedidos'}</button>
    {if $josrapp.q || $josrapp.year}<a class="josrapp-reset" href="{$josrapp.action|escape:'html':'UTF-8'}">{l s='Ver todos' mod='josrapaginacionpedidos'}</a>{/if}
  </form>
  {if $josrapp.total}
    <p class="josrapp-count">{l s='Pedidos %1$s a %2$s de %3$s' sprintf=[$josrapp.from, $josrapp.to, $josrapp.total] mod='josrapaginacionpedidos'}</p>
  {elseif $josrapp.q || $josrapp.year}
    <p class="josrapp-count">{l s='Ningún pedido coincide con la búsqueda.' mod='josrapaginacionpedidos'}</p>
  {/if}
  {$smarty.block.parent}
  {include file='module:josrapaginacionpedidos/views/templates/front/_pagination.tpl'}
{/block}
