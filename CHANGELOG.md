# Changelog - josrapaginacionpedidos

## 1.0.2 - 2026-11-08
- Paginación: quitadas las funciones `max()` y `min()` de `_pagination.tpl`. En PrestaShop 9 Smarty mostraba
  «Deprecated: Using unregistered function "max"/"min" in a template» en el historial de pedidos y en direcciones.
  El cálculo de páginas es el mismo.

## 1.0.1
- Versión en producción en sevimotor (PS 9.2).
