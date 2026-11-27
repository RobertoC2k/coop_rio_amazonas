# Cambios a aplicar en el documento de tesis (Word)

Este archivo resume los cambios hechos en el sistema que deben reflejarse en la memoria escrita. Está ordenado por capítulo según la estructura pedida por el tutor en `correcciones.md`.

> Recordatorio del tutor: no usar guiones largos en el documento. Usar paréntesis o redacción natural. Todo párrafo del marco teórico debe llevar cita.

---

## 0. Reemplazos globales (buscar y reemplazar en todo el documento)

El sistema ya no se describe como "alquiler de vehículos". Es un sistema de **reserva de servicios de transporte pesado**: el camión de la cooperativa realiza un trabajo o viaje para el cliente; el cliente no se lleva el vehículo.

| Buscar | Reemplazar por |
|---|---|
| alquiler / alquileres / renta / rentas (como módulo del sistema) | servicio / servicios |
| préstamo, fecha de préstamo | inicio del servicio, fecha de inicio |
| devolución, fecha de devolución | fin del servicio, fecha de fin |
| fecha de recogida / fecha de entrega (reservas) | fecha de inicio / fecha de fin |
| Alquilado / Devuelto (estados) | En curso / Finalizado |
| Entrega del vehículo / Procesar entrega | Finalizar servicio |
| Equipaje | Peso de carga máximo |
| Rentas por mes / Rentas por semana (dashboard) | Ingresos por mes / Ingresos por semana |
| Documento de garantía / Documentos | (eliminar: ya no existe en el sistema) |
| Base de datos `rentacar` o `alquiler` | `coop_rio_amazonas` (cuando se aplique el cambio, ver sección 4.2) |

---

## Capítulo 1: Contexto del proyecto

- En **Introducción**, **Problemática** y **Objetivos**, revisar que el sistema se presente como gestión de **reservas y servicios de transporte pesado** (turnos de camiones de la cooperativa), no como alquiler de autos.
- Si algún objetivo específico menciona "registrar alquileres" o "documentos de garantía", cambiarlo por "registrar y dar seguimiento a los servicios de transporte" y eliminar lo de la garantía.

---

## Capítulo 2: Fundamentación teórica

Agregar (con cita bibliográfica propia) un párrafo breve en la parte de PHP/MySQL o seguridad:

- **Consultas preparadas con PDO:** el sistema envía los valores por separado de la instrucción SQL (marcadores `?`), lo que impide la inyección SQL. Se usan en todos los módulos a través de la clase `Query` (`Config/App/Query.php`).
- **Patrón MVC aplicado:** el **Modelo** contiene las reglas de negocio y el acceso a datos; el **Controlador** solo recibe la petición, llama al modelo y devuelve la respuesta; la **Vista** solo muestra la información.

---

## Capítulo 3: Análisis, diseño y planificación

### 3.2 Requisitos

**Requisitos funcionales a modificar o agregar** (ajustar el código RF-XX a la numeración del documento):

| Código | Requisito |
|---|---|
| RF-XX | El sistema debe permitir iniciar sesión con usuario o correo y contraseña, **detectando automáticamente el perfil** (personal de la cooperativa o cliente) sin que el usuario lo seleccione. |
| RF-XX | El sistema debe permitir al personal **registrar un servicio de transporte** indicando cliente, vehículo, tarifa (hora, día o mes), cantidad, abono, fecha y hora de inicio y observación. |
| RF-XX | El sistema debe **calcular automáticamente** la fecha de fin y el precio unitario del servicio según la tarifa elegida y los precios del vehículo. |
| RF-XX | El sistema debe **impedir registrar un servicio o una reserva** cuando el vehículo ya esté ocupado en un periodo que se cruce con otro servicio en curso o con la reserva de otro cliente. |
| RF-XX | El sistema debe permitir **finalizar un servicio**, mostrando antes el monto abonado y el saldo pendiente. |
| RF-XX | El sistema debe generar el **comprobante PDF** de cada servicio y un **reporte PDF** general de servicios. |
| RF-XX | El sistema debe permitir registrar el **peso de carga máximo** de cada vehículo. |
| RF-XX | El sistema debe mostrar en el panel principal los **ingresos por mes y por semana**. |

**Requisitos a eliminar:** cualquier RF sobre "gestionar documentos de garantía" o "seleccionar el tipo de usuario al iniciar sesión".

**Requisitos no funcionales de seguridad a agregar** (atributo: Seguridad):

| Código | Requisito |
|---|---|
| RNF-XX | Todas las consultas a la base de datos deben usar consultas preparadas (PDO) para prevenir inyección SQL. |
| RNF-XX | Al iniciar sesión, el sistema debe generar un nuevo identificador de sesión (`session_regenerate_id`) y descartar cualquier sesión anterior. |
| RNF-XX | Los módulos de administración deben ser accesibles solo para el personal (perfil 1); cualquier otro acceso debe redirigir al login y detener la ejecución. |
| RNF-XX | La página de inicio de sesión no debe quedar en la caché del navegador ni mostrarse a un usuario que ya tiene sesión abierta. |

**Regla de negocio de disponibilidad** (puede ir como nota del RF de cruce de fechas):

> Dos periodos se cruzan cuando uno empieza antes de que el otro termine y termina después de que el otro empiece (`inicio_existente < fin_nuevo` y `fin_existente > inicio_nuevo`). Un servicio que empieza exactamente cuando otro termina sí se permite. La reserva del mismo cliente no bloquea el registro de su servicio, porque es la reserva que se está concretando.

### 3.3 Casos de uso

- Renombrar **"Registrar alquiler"** por **"Registrar servicio"**.
- Renombrar **"Registrar devolución / Entrega del vehículo"** por **"Finalizar servicio"**.
- Eliminar el caso de uso **"Gestionar documentos"** y la relación con "Registrar servicio".
- En **"Iniciar sesión"**, quitar el paso "seleccionar rol"; el flujo queda: ingresar usuario o correo y contraseña, el sistema valida y detecta el perfil.
- En **"Registrar servicio"** y **"Solicitar reserva"**, agregar el flujo alterno: *"Si el vehículo está ocupado en esas fechas, el sistema muestra el periodo ocupado y no registra."* (puede modelarse como `<<include>>` de un caso "Verificar disponibilidad").
- Actualizar el **diagrama de casos de uso** con estos nombres.

### 3.4 Arquitectura y diseño del sistema

**Flujo de ejecución MVC (ejemplo con el módulo Servicios):**

1. El navegador pide `http://localhost/coop_rio_amazonas/servicios/registrar`.
2. `.htaccess` envía toda petición a `index.php` con `?url=servicios/registrar`.
3. `index.php` separa la URL: controlador `Servicios`, método `registrar`. Carga `Controllers/Servicios.php` y ejecuta el método.
4. El constructor de `Controller` (`Config/App/Controller.php`) carga automáticamente el modelo `Models/ServiciosModel.php`.
5. El controlador recibe los datos del formulario y llama a `$this->model->registrar($datos)`.
6. El modelo valida, calcula la tarifa, verifica disponibilidad y guarda en la tabla `servicios` usando `Query` (PDO).
7. Para pantallas, el controlador llama a `$this->views->getView("servicios/index", $data)`, que incluye `Views/servicios/index.php`; esa vista carga `header.php` al inicio (`cargarHeader`) y `footer.php` al final (`cargarFooter`). Para acciones AJAX, el controlador responde en JSON.

**Modelo de datos (MER / relacional): cambios**

- Tabla `alquiler` renombrada a **`servicios`**.
- Tabla **`documentos` eliminada**, junto con su relación con servicios.
- Tabla `vehiculos`: columna `equipaje` renombrada a **`peso_carga_max`**.
- Tabla `reservas`: columnas `f_recogida` y `f_entrega` renombradas a **`f_inicio`** y **`f_fin`**.

Diccionario de datos de la tabla `servicios`:

| Campo | Tipo | Descripción |
|---|---|---|
| id | INT, PK, autoincremental | Identificador del servicio |
| cantidad | INT | Número de horas, días o meses contratados |
| tipo_precio | INT | 1 = por hora, 2 = por día, 3 = por mes |
| monto | DECIMAL(10,2) | Precio unitario según la tarifa |
| abono | DECIMAL(10,2) | Monto abonado por el cliente |
| fecha_inicio | DATETIME | Inicio del servicio |
| fecha_fin | DATETIME | Fin del servicio (calculado) |
| observacion | TEXT | Detalle del trabajo o viaje |
| estado | INT | 1 = En curso, 0 = Finalizado |
| id_cliente | INT, FK a clientes | Cliente que contrata |
| id_vehiculo | INT, FK a vehiculos | Vehículo asignado |

Relaciones: `clientes 1 : N servicios`, `vehiculos 1 : N servicios`, `clientes 1 : N reservas`, `vehiculos 1 : N reservas`.

**Diagrama de componentes: nombres actualizados**

| Módulo | Controlador | Modelo | Vista | JavaScript |
|---|---|---|---|---|
| Autenticación | `Controllers/Login.php` | `Models/LoginModel.php` | `Views/login.php` | `Assets/js/login.js` |
| Servicios | `Controllers/Servicios.php` | `Models/ServiciosModel.php` | `Views/servicios/index.php` | `Assets/js/pages/servicios.js` |
| Reservas | `Controllers/Reservas.php` | `Models/ReservasModel.php` | `Views/reservas/index.php`, `Views/reservas/verify.php` | `Assets/js/pages/reservas.js`, `verify.js` |
| Vehículos | `Controllers/Vehiculos.php` | `Models/VehiculosModel.php` | `Views/vehiculos/index.php` | `Assets/js/pages/vehiculos.js` |
| Panel | `Controllers/Dashboard.php` | `Models/DashboardModel.php` | `Views/dashboard/home.php` | `Assets/js/chart.js` |

Eliminar del diagrama el componente **Documentos**.

### 3.5 Planificación (épicas e historias de usuario)

- Renombrar la épica o HU de "Alquileres" por **"Gestión de servicios de transporte"**.
- Eliminar la HU de **gestión de documentos de garantía** y ajustar la trazabilidad RF a HU.
- Criterios de aceptación sugeridos:
  - **Iniciar sesión:** *Dado un usuario registrado, cuando ingresa su usuario o correo y contraseña correctos, entonces accede al panel según su perfil sin elegirlo; si ya tiene sesión abierta, el login lo redirige al panel.*
  - **Registrar servicio:** *Dado un vehículo libre en las fechas indicadas, cuando el personal registra el servicio, entonces se guarda, se calcula la fecha de fin y se abre el comprobante PDF. Si el vehículo está ocupado, se muestra el periodo ocupado y no se guarda.*
  - **Finalizar servicio:** *Dado un servicio en curso, cuando el personal lo finaliza, entonces su estado cambia a "Finalizado" y el saldo pendiente queda en 0.*
  - **Vehículos:** *El formulario permite registrar el peso de carga máximo del vehículo.*

### 3.6 Plan de pruebas: casos sugeridos (caja negra)

| N° | Caso | Entrada | Resultado esperado |
|---|---|---|---|
| CP-01 | Login correcto (personal) | usuario y clave válidos | Ingresa al panel de administración |
| CP-02 | Login correcto (cliente) | correo y clave válidos | Ingresa al módulo de reservas |
| CP-03 | Login incorrecto | clave errónea | "Usuario o contraseña incorrecta" |
| CP-04 | Intento de inyección SQL | usuario `admin' #` | Acceso rechazado |
| CP-05 | Login con sesión abierta | abrir `/login` estando dentro | Redirige al panel |
| CP-06 | Servicio sin cruce | vehículo libre | Servicio registrado y PDF generado |
| CP-07 | Servicio con cruce parcial | fechas que se solapan con otro servicio en curso | "El vehículo está ocupado: ..." |
| CP-08 | Fechas contiguas | inicio igual al fin de otro servicio | Se permite |
| CP-09 | Acceso no autorizado | cliente abre `/servicios/listar` | Redirige al login, no muestra datos |
| CP-10 | Finalizar servicio | botón Finalizar | Estado "Finalizado", pendiente 0.00 |

Para **caja blanca**, se puede documentar la prueba del método `ServiciosModel::buscarCruceDeFechas()` recorriendo sus caminos: cruce con reserva de otro cliente, cruce con servicio en curso y sin cruce.

---

## Capítulo 4: Desarrollo e implementación

### 4.1 Construcción (capturas a rehacer)

Las capturas anteriores ya no coinciden con el sistema. Rehacer:

- **Login:** ahora sin selector de perfil (solo usuario y contraseña).
- **Servicios:** pantalla "Servicios" y formulario "Nuevo Servicio" (sin documento de garantía).
- **Vehículos:** formulario con el campo "Peso de carga máx.".
- **Panel:** gráficos "Ingresos por mes / por semana".
- **Comprobante PDF del servicio** y **reporte PDF de servicios**.
- **Reservas > Aprobar:** ahora muestra "Fecha de inicio / Fecha de fin".

### 4.2 Despliegue (pasos actualizados)

1. Instalar XAMPP (Apache + PHP 8 + MariaDB).
2. Copiar el proyecto en `C:\xampp\htdocs\coop_rio_amazonas`.
3. Crear la base de datos `coop_rio_amazonas` e importar `coop_rio_amazonas.sql` desde phpMyAdmin.
4. Copiar `Config/Config.example.php` como `Config/Config.php` y completar los datos de conexión y SMTP.
5. Ejecutar `composer install` para instalar PHPMailer.
6. Ingresar a `http://localhost/coop_rio_amazonas/`.

---

## Pendiente (aún no aplicado en el código)

- Refactor MVC del módulo **Reservas** (mover lógica al modelo y aplicar la misma regla de cruce de fechas). Cuando se haga, actualizar el caso de uso "Solicitar reserva" y su HU.
- Cambio de nombre de la carpeta del proyecto y de la base de datos a `coop_rio_amazonas` (ver instrucciones entregadas aparte).
