{*
 * Mis direcciones en tarjetas: plantilla del tema con buscador y paginación.
 *}
{extends file='customer/addresses.tpl'}

{block name='page_content'}
  {include file='module:josrapaginacionpedidos/views/templates/front/_addresses_toolbar.tpl'}
  {$smarty.block.parent}
  {include file='module:josrapaginacionpedidos/views/templates/front/_pagination.tpl'}
{/block}
