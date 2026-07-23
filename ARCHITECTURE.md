# 🏗️ Arquitectura del Sistema: Registro EICAL 2026

## 1. Resumen del Proyecto

El sistema es una plataforma de gestión integral diseñada para centralizar la logística, el inventario y la acreditación del evento **Registro EICAL**. Sustituye flujos de trabajo manuales y formularios dispersos por una arquitectura relacional robusta que garantiza la integridad de los datos y la trazabilidad de los insumos.

---

## 2. Stack Tecnológico

- **Framework:** Laravel 12 (PHP 8.2+)
- **Base de Datos:** MySQL / SQLite (configurable)
- **Autenticación:** Laravel Fortify (2FA, verificación de email)
- **Frontend:** Vue 3 + Inertia.js + TypeScript
- **UI:** Tailwind CSS 4 + Reka UI + Lucide Icons
- **Reportes:** (pendiente de implementar)

---

## 3. Modelo de Datos (Esquema Relacional)

### 👥 Módulo 1: Identidad y Acceso (IAM)

Gestión de usuarios con jerarquía de permisos y SoftDeletes para conservar historial.

- **`roles`**: Define niveles de acceso (Admin, Staff, Tallerista, Participante).
- **`participation_roles`**: Roles específicos de participación (ej. Niño, Joven, Adulto).
- **`users`**: Tabla central de usuarios. Incluye `first_name`, `last_name`, `dni` (unique), `email` (unique), `affiliation`, `country`, `profile_photo_path`, `role_id` (FK requerida), `is_active`. Soporta 2FA con columnas `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`.

### 🏗️ Módulo 2: Infraestructura y Espacios

Administración física del recinto.

- **`stand_types`**: Catálogo de dimensiones y servicios (ej. 2x3 con luz).
- **`stands`**: Registro de actividades (Talleres, Teatro, etc.) vinculadas a un responsable.
- **`qr_codes`**: Tabla polimórfica que genera identificadores UUID para usuarios y stands.

### 📦 Módulo 3: Inventario y Variaciones

Motor de stock con soporte para productos complejos.

- **`products`**: Definición base de artículos (ej. "Playera").
- **`attributes`** & **`attribute_values`**: Manejo de variantes (Talla: S, M, L).
- **`product_skus`**: Control de stock físico y comprometido por cada variante única.
- **`sku_values`**: Pivote con **PK compuesta** para definir la relación SKU-Atributo.

### 🚚 Módulo 4: Logística y Auditoría

Unificación de pedidos y control de movimientos de almacén.

- **`product_requests`**: Tabla unificada para pedidos normales y especiales. Incluye trazabilidad de quién solicita, quién aprueba y quién entrega.
- **`stock_movements`**: Libro mayor de auditoría. Registra `previous_stock` y `new_stock` con referencias polimórficas a los pedidos.
- **`stand_type_quotas`**: Reglas de negocio y límites por tipo de espacio.

### 🎓 Módulo 5: Acreditación y Configuración

Control de asistencia y generación de salidas.

- **`attendances`**: Registro de entrada por día con **Unique Constraint** (`user_id`, `event_day`).
- **`templates`** & **`template_fields`**: Sistema de coordenadas para imprimir datos sobre PDFs de constancias.
- **`settings`**: Configuración dinámica de llaves/valores para controlar fechas de cierre y permisos de edición.

### 🏢 Módulo 6: Estructura Organizacional

Definición de la jerarquía institucional.

- **`departments`**: Departamentos o áreas de la organización.
- **`units`**: Unidades o subáreas dentro de cada departamento.

---

## 4. Reglas de Negocio Críticas

### 🔐 Jerarquía de Gestión

1.  **Administrador:** Crea stands, asigna responsables y gestiona el inventario global.
2.  **Responsable (Tallerista):** Gestiona a su propio equipo (Participantes) y solicita materiales.
3.  **Participante:** Rol de privilegios mínimos para validar datos personales, elegir talla de playera y descargar su constancia.

### 📝 Registro de Usuarios

- **Campos requeridos en registro:** `first_name`, `last_name`, `dni` (único), `email` (único), `password`
- **Asignación automática:** Nuevos usuarios reciben el rol "Participante" por defecto
- **Validaciones:** `dni` debe ser único en el sistema, `email` debe ser único y válido

### 🔧 GitHub Actions

- **PHP version:** 8.2 (compatible con `composer.json` que requiere `^8.2`)
- **Node.js version:** 22
- **Workflows:** `tests.yml` y `lint.yml` configurados correctamente

### 📦 Lógica de Stock

- **Stock Comprometido:** Al registrar un usuario o hacer un pedido, el sistema aparta las unidades sin restarlas del físico.
- **Descuento Real:** Solo ocurre cuando el Staff marca el pedido como `delivered`, generando el movimiento contable correspondiente.

### ⚙️ Flexibilidad de Configuración

El sistema utiliza la tabla `settings` para validar procesos en tiempo real:

- Si `tshirt_edit_deadline` es nulo, la edición permanece abierta.
- Si el switch `allow_participant_edit_name` está en `false`, el participante solo puede ver su nombre pero no corregirlo.

### 🔒 Seguridad Adicional

- **2FA:** Autenticación de dos factores via Laravel Fortify (columnas: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`)
- **Verificación de Email:** Campo `email_verified_at` para tracking de verificación
- **Appearance:** Sistema de tema oscuro/claro configurable por usuario
- **Navigación SPA:** Inertia.js para experiencia fluida sin recargas

---

## 5. Diagrama de Flujo: Ciclo de Vida de un Participante

1.  **Alta:** El Responsable lo registra en el stand.
2.  **Validación:** El Participante entra, sube su foto y confirma su talla.
3.  **Acreditación:** El Staff escanea su QR en el evento y marca la asistencia.
4.  **Certificación:** Si tiene asistencia completa, el sistema habilita la descarga del PDF basado en el `template` asignado.

---

## 6. Seguridad y Auditoría

- **SoftDeletes:** Ningún usuario o pedido se elimina físicamente para evitar "huecos" en los reportes de inventario.
- **Trazabilidad Polimórfica:** Cada cambio en el stock está ligado a un modelo de referencia (Pedido o Ajuste Manual) y a un usuario responsable (`created_by`).

---

_Documentación generada para el equipo de desarrollo - Abril 2026._
