# Plan de Correcciones de Tesis y Sistema Web (PHP/JS)

**Contexto del Proyecto:** Sistema web desarrollado en PHP nativo (POO/MVC), JavaScript (jQuery), CSS (Bootstrap) y MySQL para gestión de turnos/reservas en una cooperativa de transporte pesado.
**Objetivo:** Aplicar todas las observaciones solicitadas por el tutor en la memoria escrita, la arquitectura del código fuente y la presentación de sustentación.

---

## 🔴 PRIORIDAD 1: Arquitectura de Software y Código Fuente (PHP / JS)

El tutor detectó inconsistencias entre lo documentado (patrón MVC) y la implementación real, además de falencias al trazar el flujo de ejecución desde el `index.php`.

- [ ] **Separación estricta de responsabilidades en MVC (Modelo vs. Controlador):**
  - **Problema:** Actualmente los modelos (`models/`) solo contienen la conexión/consultas básicas a la base de datos, mientras que toda la lógica de negocio está concentrada en los controladores (`controllers/`).
  - **Acción:** Mover las reglas y lógica de negocio hacia las clases del **Modelo**, dejando al **Controlador** únicamente como intermediario entre las peticiones de la Vista y el Modelo.
- [ ] **Dominio y trazabilidad del flujo de ejecución (`index.php` -> Vista):**
  - **Problema:** Durante la revisión hubo dificultad para rastrear en vivo cómo `index.php` invoca al controlador, cómo este llama a la vista y dónde se cargan exactamente las plantillas (`header`, `footer`, vistas internas).
  - **Acción:** Mapear y limpiar la ruta de carga desde `index.php` hasta el renderizado final. Debes poder navegar fluidamente en el código y explicar paso a paso: `index.php` $\rightarrow$ enrutamiento/controlador $\rightarrow$ modelo $\rightarrow$ vista $\rightarrow$ inclusión de `header.php` y `footer.php`.
- [ ] **Preparación para defensa técnica historia por historia:**
  - **Requisito del tutor:** En la próxima revisión se evaluará **historia por historia directamente en el código y en ejecución**. Para cada Historia de Usuario (HU) deberás mostrar en pantalla el formulario/interfaz funcionando, realizar un cambio en vivo si se solicita y señalar exactamente su archivo en `models/`, `views/`, `controllers/` y su impacto en la base de datos.

---

## 🟠 PRIORIDAD 2: Reestructuración de la Memoria Escrita (Documento Word)

El tutor indicó que el documento actual está desorganizado en el orden lógico de ingeniería de software (se presentan arquitectura y base de datos antes de levantar requisitos).

### Capítulo 1: Contexto del Proyecto
- [ ] **Renombrar apartado 1.1:** Cambiar *"Descripción de la cooperativa"* por **1.1 Introducción** (el Capítulo 1 debe enfocarse en el contexto general del proyecto, no en el análisis profundo de la empresa).
- [ ] Mantener la secuencia: `1.1 Introducción`, `Problemática`, `Objetivos` y `Metodología de desarrollo`.

### Capítulo 2: Fundamentación Teórica
- [ ] **Sincronizar tecnologías con lo realmente usado:**
  - **CSS:** Documentar específicamente el uso de **Bootstrap** (no dejar definiciones genéricas de CSS).
  - **JavaScript:** Documentar el uso de **jQuery** y actualizar las citas/referencias bibliográficas (evitar fuentes obsoletas como JavaScript de 2019; usar referencias recientes).
  - **Enfoque práctico:** No extenderse en teoría genérica; incluir un párrafo breve de definición y enfocar el análisis en **cómo y para qué se utiliza cada tecnología dentro del proyecto**.
- [ ] **Citas y estilo de redacción (Anti-IA / Turnitin):**
  - **Referenciación obligatoria:** **Todos los párrafos** del marco teórico deben tener su respectiva cita/referencia.
  - **Eliminar guiones largos (`—`):** Reemplazar todos los guiones largos típicos de textos generados por IA por **paréntesis `()`** o redacción natural para evitar falsos positivos en detectores de IA y Turnitin.
- [ ] **Mover y ampliar el análisis de la empresa al final del Cap. 2:**
  - Crear el apartado **2.7 Sobre la empresa** (después de *Trabajos similares*).
  - Extenderlo a **mínimo 3 páginas** detallando: historia/descripción, organigrama, misión, visión, infraestructura tecnológica actual y servicios que ofrece.

### Capítulo 3: Análisis, Diseño y Planificación (Orden Lógico Corregido)
Reordenar los subcapítulos para respetar el ciclo de vida del desarrollo:

1. [ ] **3.1 Herramientas y tecnologías seleccionadas** (justificación técnica de la elección).
2. [ ] **3.2 Requisitos (Funcionales y No Funcionales):**
   - **Codificación:** Asignar código único a cada requisito (ej. `RF-01`, `RF-02`, ...).
   - **Redacción estándar:** Escribir cada requisito funcional bajo la estructura: *"El sistema debe permitir..."*.
   - **Requisitos No Funcionales (Atributos de calidad):** Clasificarlos por atributo (Usabilidad, Disponibilidad, Rendimiento, Seguridad).
   - **Métricas cuantificables (SLA):** Eliminar términos subjetivos como *"rápido"*. Definir tiempos exactos (ej. *"tiempo de respuesta del servidor inferior a $2\text{ s}$"* o en milisegundos según corresponda).
   - **Priorización MoSCoW:** Si se incluye prioridad en requisitos funcionales, utilizar el método **MoSCoW** (*Debe tener / Must have*, *Debería tener / Should have*, *Podría tener / Could have*, *No tendrá / Won't have*).
3. [ ] **3.3 Casos de Uso:**
   - **Agregar Diagrama de Casos de Uso:** Incluir obligatoriamente el diagrama gráfico (actores, sistema, relaciones `include`/`extend`) **antes** de las tablas de especificación detallada.
4. [ ] **3.4 Arquitectura y Diseño del Sistema** *(mover aquí desde su ubicación anterior)*:
   - **Corregir diagrama de arquitectura:** Eliminar diagramas genéricos de internet que muestran el *Frontend* y *Backend* separados en servidores distintos si el proyecto corre monolíticamente en un mismo servidor Apache/PHP.
   - **Ubicación de tecnologías en la arquitectura:** Reflejar correctamente que **HTML, CSS (Bootstrap), JavaScript (jQuery) y el renderizado PHP** operan en la capa de presentación/cliente, mientras que la lógica y acceso a datos se ejecutan en el servidor.
   - Incluir: **Arquitectura del sistema (propuesta real)**, **Arquitectura de despliegue**, **Modelo de datos (MER / Relacional)** y **Diagrama de componentes**.
5. [ ] **3.5 Planificación (Scrum / Product Backlog):**
   - **Interesados (Stakeholders).**
   - **Épicas:** Definir las épicas como los **módulos principales del sistema** (ej. *Épica 1: Gestión y Autenticación de Usuarios*).
   - **Historias de Usuario (HU) y Trazabilidad:**
     - Numerar todas las HU (`HU-01`, `HU-02`, ...).
     - Mantener **trazabilidad 1 a 1** con los Requisitos Funcionales (ningún RF debe quedarse sin HU; ej. incluir el *Catálogo de apoyo* que faltaba en las historias).
     - Cada HU debe incluir su **Criterio de Aceptación** específico.
   - **Sprint Backlog (Tabla independiente):**
     - Separar el *Sprint Backlog* en una tabla aparte del *Product Backlog*.
     - Consolidar las ~10 historias en un máximo de **4 a 5 Sprints** (mínimo 2 historias por sprint; no hacer 1 sprint por historia).
     - Columnas requeridas: `Sprint (1..N)`, `Fechas / Duración`, y `Historias de Usuario incluidas (HU-01, HU-05, etc.)`.
6. [ ] **3.6 Plan de Pruebas (Nuevo apartado obligatorio):**
   - Documentar la estrategia de pruebas que se ejecutará en el Capítulo 5: **Pruebas de caja negra**, **Pruebas de caja blanca** y **Pruebas de aceptación**.

### Capítulo 4: Desarrollo e Implementación
- [ ] **4.1 Construcción de la solución:**
  - Documentar el desarrollo organizado por cada **Sprint** (`Sprint 1`, `Sprint 2`, ...).
  - Por cada Historia de Usuario (`HU-XX`) dentro del sprint, incluir exactamente 3 elementos:
    1. **Descripción de la historia.**
    2. **Validación / Criterio de aceptación cumplido.**
    3. **Una (1) sola captura de pantalla** representativa de la funcionalidad construida (ej. para autenticación, mostrar el *Home/Dashboard* con el usuario ya autenticado, no dos pantallas mezcladas).
- [ ] **4.2 Despliegue:**
  - Documentar el paso a paso de la puesta en marcha/despliegue del sistema.

### Capítulo 5: Validación y Pruebas
- [ ] Ejecutar y documentar los resultados de las pruebas definidas en el apartado **3.6 Plan de Pruebas**.

---

## 🟡 PRIORIDAD 3: Presentación (PowerPoint) y Cronograma

- [ ] **Formato de diapositivas:**
  - Máximo **10 diapositivas** (diseñadas para una exposición de **15 minutos máximo**).
  - **Mínimo texto y máximo apoyo visual** (diagramas, esquemas, capturas limpias).
- [ ] **Alcance del contenido a exponer:**
  - Abordar desde el **Capítulo 1 hasta el Capítulo 3 completo**.
  - Presentar el **Sprint 1 desarrollado** del Capítulo 4, aclarando explícitamente durante la defensa que **se encuentra en fase de revisión por parte del tutor** y que los demás sprints ya cuentan con avance implementado.
- [ ] **Entregables inmediatos:**
  - Aplicar las correcciones del documento y código entre hoy y mañana.
  - Enviar el documento corregido y las diapositivas al correo del tutor a más tardar el **viernes a primera hora** para su visto bueno previo a la sustentación de la próxima semana.
```

Ya tienes la hoja de ruta completa lista para incorporar a tu proyecto. Si deseas que empecemos a trabajar sobre alguna de estas correcciones (por ejemplo, reestructurar la lógica de tus archivos PHP de Modelo/Controlador o redactar los apartados del Capítulo 3 y el Plan de Pruebas), compárteme los archivos correspondientes y avanzamos.