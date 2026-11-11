# Changelog - josrapaginacionpedidos

## 1.1.1 - 2026-11-11
- Actualizador: si el servidor de actualizaciones cambia de dominio, el módulo aprende la dirección nueva solo.
- Actualizador preparado para licencias (desactivadas en este módulo).

## 1.1.0 - 2026-11-11
- Administración · Pedido manual (Pedidos > Añadir pedido): los carritos y los pedidos del cliente elegido se ven
  por páginas (5 filas por defecto, configurable), los más recientes primero. Antes PrestaShop los pintaba todos y
  con clientes de miles de carritos había que bajar muchísimo para llegar al buscador de productos. Opción para que
  el bloque aparezca plegado.
- Administración · Ficha de cliente: paginación en las listas que no la traen (mensajes, correos enviados, últimas
  conexiones; en versiones anteriores a la 9 también pedidos, carritos, productos y direcciones). Las rejillas que
  ya paginan no se tocan.
- La configuración queda separada en «Tienda» y «Administración». Todo lo nuevo se puede desactivar.
- Conectado al servidor de actualizaciones de josra: aviso de la instalación, bloque «Actualizaciones» en la
  configuración y, en PrestaShop 8.1 o superior, la versión nueva en Gestor de módulos > Actualizaciones.
- No cambia nada de la tienda (historial, direcciones y checkout siguen igual).

## 1.0.2 - 2026-11-08
- Paginación: quitadas las funciones `max()` y `min()` de `_pagination.tpl`. En PrestaShop 9 Smarty mostraba
  «Deprecated: Using unregistered function "max"/"min" in a template» en el historial de pedidos y en direcciones.
  El cálculo de páginas es el mismo.

## 1.0.1
- Versión en producción en sevimotor (PS 9.2).
