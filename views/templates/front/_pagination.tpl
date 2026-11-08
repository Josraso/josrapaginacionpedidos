{*
 * Paginación (común a pedidos y direcciones)
 *}
{if $josrapp.pages > 1}
  <nav class="josrapp-pagination" aria-label="{l s='Paginación' mod='josrapaginacionpedidos'}">
    <ul>
      {if $josrapp.page > 1}
        <li><a href="{$josrapp.page_url|escape:'html':'UTF-8'}{$josrapp.page - 1}" rel="prev" aria-label="{l s='Anterior' mod='josrapaginacionpedidos'}">&lsaquo;</a></li>
      {/if}
      {* sin max()/min(): Smarty de PS 9 avisa de funciones PHP no registradas *}
      {assign var=jp_from value=$josrapp.page - 2}
      {if $jp_from < 1}{assign var=jp_from value=1}{/if}
      {assign var=jp_to value=$josrapp.page + 2}
      {if $jp_to > $josrapp.pages}{assign var=jp_to value=$josrapp.pages}{/if}
      {if $jp_from > 1}
        <li><a href="{$josrapp.page_url|escape:'html':'UTF-8'}1">1</a></li>
        {if $jp_from > 2}<li class="josrapp-dots">…</li>{/if}
      {/if}
      {for $p=$jp_from to $jp_to}
        <li{if $p == $josrapp.page} class="current"{/if}>{if $p == $josrapp.page}<span aria-current="page">{$p}</span>{else}<a href="{$josrapp.page_url|escape:'html':'UTF-8'}{$p}">{$p}</a>{/if}</li>
      {/for}
      {if $jp_to < $josrapp.pages}
        {if $jp_to < $josrapp.pages - 1}<li class="josrapp-dots">…</li>{/if}
        <li><a href="{$josrapp.page_url|escape:'html':'UTF-8'}{$josrapp.pages}">{$josrapp.pages}</a></li>
      {/if}
      {if $josrapp.page < $josrapp.pages}
        <li><a href="{$josrapp.page_url|escape:'html':'UTF-8'}{$josrapp.page + 1}" rel="next" aria-label="{l s='Siguiente' mod='josrapaginacionpedidos'}">&rsaquo;</a></li>
      {/if}
    </ul>
  </nav>
{/if}
