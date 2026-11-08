{*
 * Mis direcciones en tabla compacta (pensada para clientes con muchas direcciones).
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='Your addresses' d='Shop.Theme.Customeraccount'}
{/block}

{block name='page_content'}
  {include file='module:josrapaginacionpedidos/views/templates/front/_addresses_toolbar.tpl'}
  {if $josrapp.addresses}
    <div class="josrapp-table-wrap">
      <table class="josrapp-table">
        <thead>
          <tr>
            <th>{l s='Alias' mod='josrapaginacionpedidos'}</th>
            <th>{l s='Destinatario' mod='josrapaginacionpedidos'}</th>
            <th>{l s='Dirección' mod='josrapaginacionpedidos'}</th>
            <th>{l s='Teléfono' mod='josrapaginacionpedidos'}</th>
            <th>{l s='Uso' mod='josrapaginacionpedidos'}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        {foreach $josrapp.addresses as $a}
          <tr>
            <td data-label="{l s='Alias' mod='josrapaginacionpedidos'}"><strong>{$a.alias|escape:'html':'UTF-8'}</strong></td>
            <td data-label="{l s='Destinatario' mod='josrapaginacionpedidos'}">
              {if $a.company}{$a.company|escape:'html':'UTF-8'}<br>{/if}
              {$a.firstname|escape:'html':'UTF-8'} {$a.lastname|escape:'html':'UTF-8'}
              {if $a.dni}<br><small>{$a.dni|escape:'html':'UTF-8'}</small>{/if}
            </td>
            <td data-label="{l s='Dirección' mod='josrapaginacionpedidos'}">
              {$a.address1|escape:'html':'UTF-8'}{if $a.address2} {$a.address2|escape:'html':'UTF-8'}{/if}<br>
              {$a.postcode|escape:'html':'UTF-8'} {$a.city|escape:'html':'UTF-8'}{if $a.state} ({$a.state|escape:'html':'UTF-8'}){/if}
              <br><small>{$a.country|escape:'html':'UTF-8'}</small>
            </td>
            <td data-label="{l s='Teléfono' mod='josrapaginacionpedidos'}">{$a.phone|escape:'html':'UTF-8'}{if $a.phone_mobile}{if $a.phone}<br>{/if}{$a.phone_mobile|escape:'html':'UTF-8'}{/if}</td>
            <td data-label="{l s='Uso' mod='josrapaginacionpedidos'}">
              {if $a.n_orders}
                <small>{if $a.n_orders == 1}{l s='1 pedido' mod='josrapaginacionpedidos'}{else}{l s='%s pedidos' sprintf=[$a.n_orders] mod='josrapaginacionpedidos'}{/if}<br>{l s='último' mod='josrapaginacionpedidos'} {dateFormat date=$a.last_used full=0}</small>
              {else}<small class="josrapp-muted">{l s='Sin pedidos' mod='josrapaginacionpedidos'}</small>{/if}
            </td>
            <td class="josrapp-actions">
              <a href="{$a.edit_url|escape:'html':'UTF-8'}">{l s='Update' d='Shop.Theme.Actions'}</a>
              <a href="{$a.delete_url|escape:'html':'UTF-8'}" class="josrapp-delete" onclick="return confirm('{l s='¿Eliminar esta dirección?' mod='josrapaginacionpedidos' js=1}');">{l s='Delete' d='Shop.Theme.Actions'}</a>
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
    {include file='module:josrapaginacionpedidos/views/templates/front/_pagination.tpl'}
  {elseif !$josrapp.q}
    <p>{l s='No addresses are available.' d='Shop.Notifications.Success'}</p>
  {/if}
{/block}
