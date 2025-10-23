# Gestor de remeras personalizadas

Aplicación web sencilla desarrollada con **PHP**, **HTML** y **JavaScript** para administrar el circuito completo de ventas de remeras personalizadas.

## Características principales

- **Autenticación con roles**: acceso para administrador, ventas, compras y producción. El administrador puede crear usuarios y resetear contraseñas.
- **Módulo de ventas**: registra clientes, prenda(s) solicitada(s), método de pago, fecha de entrega y observaciones. Envía aviso automático a compras y producción y deja trazabilidad del pedido.
- **Módulo de compras**: consolida las órdenes pendientes, permite confirmar la compra registrando el costo y genera un PDF con la lista unificada de insumos del día.
- **Módulo de producción**: visualiza el estado de cada trabajo y permite actualizar el avance (pendiente, en proceso, entregado) compartiendo la información con los demás módulos.
- **Panel administrador**: tablero con métricas de ingresos, gastos, egresos pendientes y artículos más vendidos; además de gestión de usuarios.

Toda la información se almacena en archivos JSON simples dentro del directorio `data/` para facilitar la puesta en marcha sin una base de datos adicional.

## Puesta en marcha

1. Asegurate de tener PHP 8 instalado.
2. Desde la raíz del proyecto ejecutá un servidor embebido:
   ```bash
   php -S localhost:8000
   ```
3. Abrí [http://localhost:8000](http://localhost:8000) en tu navegador y autenticá con alguna de las cuentas preconfiguradas.

## Usuarios iniciales

| Rol          | Usuario      | Contraseña      |
|--------------|--------------|-----------------|
| Administrador| `admin`      | `admin123`      |
| Ventas       | `ventas`     | `ventas123`     |
| Compras      | `compras`    | `compras123`    |
| Producción   | `produccion` | `produccion123` |

El administrador puede crear nuevos usuarios y cambiar contraseñas desde el módulo correspondiente.

## Estructura de datos

- `data/orders.json`: almacena las órdenes registradas, sus ítems y el historial de cambios.
- `data/users.json`: contiene los usuarios del sistema con contraseñas encriptadas.
- `data/email_log.txt`: registra las notificaciones generadas para Compras y Producción al cargar una nueva venta.

## Generación de PDF

La opción "Descargar lista consolidada en PDF" del módulo de Compras construye un archivo PDF ligero con la información de los pedidos pendientes de compra y un resumen por cliente e ítems.

## Notas

- La función `mail()` se invoca al registrar una venta para enviar avisos a los correos configurados en `config.php`. En entornos sin servidor de correo la información queda disponible en `data/email_log.txt`.
- Podés adaptar fácilmente las listas desplegables o agregar validaciones adicionales editando los archivos PHP correspondientes.
