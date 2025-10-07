# Sistema de gestión de remeras personalizadas

Aplicación web simple construida con **PHP**, **HTML** y **JavaScript** para administrar el flujo completo de ventas, compras y métricas administrativas de una empresa de remeras personalizadas en Argentina.

## Características principales

- **Autenticación por roles** con accesos iniciales para administración, ventas y compras.
- **Módulo de Ventas** para registrar pedidos detallados, tipos de prendas, talles, archivos y fechas de entrega. Cada carga notifica a compras y producción (registro simulado) y queda disponible para seguimiento del estado.
- **Módulo de Compras** para transformar los pedidos en órdenes de compra, marcar insumos como adquiridos, cargar costos y descargar un PDF diario con el consolidado de prendas pendientes por comprar.
- **Módulo de Administración** con métricas clave: ventas, ingresos, gastos, pendientes y ranking de productos más vendidos. Desde aquí también se pueden actualizar contraseñas de cualquier usuario.

Todos los datos se almacenan en archivos JSON dentro de `data/`, lo que facilita la instalación sin requerir base de datos.

## Requisitos

- PHP 8.1 o superior
- Servidor web con soporte para sesiones de PHP (Apache, Nginx + PHP-FPM, o el servidor embebido de PHP)

## Puesta en marcha

```bash
php -S localhost:8000
```

Luego ingresar a [http://localhost:8000](http://localhost:8000) y autenticarse con alguno de los accesos iniciales:

| Rol          | Usuario  | Contraseña |
|--------------|----------|------------|
| Administrador| `admin`  | `admin123` |
| Ventas       | `ventas` | `ventas123`|
| Compras      | `compras`| `compras123`|

> Se recomienda cambiar las contraseñas desde el módulo de Administración al poner el sistema en producción.

## Notificaciones simuladas

Cada nueva venta genera un registro en `data/email.log` simulando los correos que recibirían los equipos de compras y producción. Esto facilita la revisión de actividad incluso sin un servidor SMTP configurado.

## Exportación a PDF

Desde Compras es posible descargar un PDF diario consolidado con los insumos pendientes, agrupados por material, color, talle y tipo de prenda, junto con un detalle por cliente para facilitar las órdenes al proveedor.

## Estructura del proyecto

```
├── admin.php
├── assets/
│   ├── app.js
│   └── styles.css
├── compras.php
├── data/
│   ├── email.log
│   └── sales.json
├── export_purchase_list.php
├── includes/
│   ├── auth.php
│   └── data.php
├── index.php
├── lib/
│   └── SimplePDF.php
├── logout.php
└── ventas.php
```

Los archivos JSON se generan automáticamente al registrar la primera venta o actualizar usuarios. Asegúrese de que el directorio `data/` tenga permisos de escritura para el usuario que ejecuta PHP.
