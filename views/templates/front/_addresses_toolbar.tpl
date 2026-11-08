{*
 * Buscador, orden y cambio de vista de "Mis direcciones"
 *}
<div class="josrapp-addr-head">
  <form method="get" action="{$josrapp.action|escape:'html':'UTF-8'}" class="josrapp-toolbar">
    <input type="text" name="q" class="form-control" value="{$josrapp.q|escape:'html':'UTF-8'}" placeholder="{l s='Buscar: nombre, empresa, calle, ciudad, CP, teléfono, DNI…' mod='josrapaginacionpedidos'}">
    <select name="sort" class="form-control">
      <option value="recent"{if $josrapp.sort == 'recent'} selected{/if}>{l s='Usadas recientemente' mod='josrapaginacionpedidos'}</option>
      <option value="alias"{if $josrapp.sort == 'alias'} selected{/if}>{l s='Por alias (A-Z)' mod='josrapaginacionpedidos'}</option>
    </select>
    <button type="submit" class="btn btn-primary">{l s='Buscar' mod='josrapaginacionpedidos'}</button>
    {if $josrapp.q}<a class="josrapp-reset" href="{$josrapp.action|escape:'html':'UTF-8'}">{l s='Ver todas' mod='josrapaginacionpedidos'}</a>{/if}
  </form>
  <div class="josrapp-head-actions">
    <span class="josrapp-views">
      <a href="{$josrapp.view_table|escape:'html':'UTF-8'}" class="{if $josrapp.view == 'table'}active{/if}">{l s='Tabla' mod='josrapaginacionpedidos'}</a>
      <a href="{$josrapp.view_cards|escape:'html':'UTF-8'}" class="{if $josrapp.view == 'cards'}active{/if}">{l s='Tarjetas' mod='josrapaginacionpedidos'}</a>
    </span>
    <a href="{$josrapp.new_url|escape:'html':'UTF-8'}" class="btn btn-primary josrapp-new">+ {l s='Añadir dirección' mod='josrapaginacionpedidos'}</a>
  </div>
</div>
{if $josrapp.total}
  <p class="josrapp-count">{l s='Direcciones %1$s a %2$s de %3$s' sprintf=[$josrapp.from, $josrapp.to, $josrapp.total] mod='josrapaginacionpedidos'}{if $josrapp.q} · {l s='%s en total' sprintf=[$josrapp.total_all] mod='josrapaginacionpedidos'}{/if}</p>
{elseif $josrapp.q}
  <p class="josrapp-count">{l s='Ninguna dirección coincide con la búsqueda.' mod='josrapaginacionpedidos'}</p>
{/if}
