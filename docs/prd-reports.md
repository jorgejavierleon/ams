# PRD: Reportes Avanzados y Exportación para Integración con Nómina/Payroll
 
**Producto:** Kolvi — Sistema de Control de Asistencia
**Autor:** Jorge (PM/Lead Dev) + Claude (asistencia PM)
**Estado:** Draft para revisión
**Fecha:** 2026-08-03
**Épica relacionada:** Reportería y Payroll Integration (ver GitHub epics / MVP Gap Analysis #9, #11)
 
---
 
## 1. Resumen Ejecutivo
 
Kolvi tiene implementados los 4 reportes obligatorios DT (Portal Fiscalizador, ~95%), pero **carece completamente de una capa de reportería operativa/exportable orientada a nómina**, identificada como brecha crítica tanto en el MVP Gap Analysis interno como en el Kolvi vs GeoVictoria Gap Analysis. Los tres competidores directos (Buk, Talana, GeoVictoria) resuelven esto de forma nativa o vía integraciones, y lo usan activamente como argumento de venta ("traspaso a remuneraciones", "+50 reportes", "integraciones nativas con ERP/nómina").
 
Este PRD define el feature de **Reportes de Asistencia para Payroll** — un motor de reportería configurable + exportación multi-formato + traspaso/integración con sistemas de remuneraciones — que debe alcanzar paridad funcional con la competencia y, donde sea posible, diferenciarse por simplicidad de configuración y trazabilidad de compliance.
 
---
 
## 2. Contexto Competitivo (research de mercado)
 
### 2.1 Talana — "Traspaso a Remuneraciones"
- Módulo de Asistencia y Turnos tiene una función explícita de **traspaso a remuneraciones**, solo disponible si el cliente tiene ambos módulos (asistencia + remuneraciones) del mismo proveedor.
- Antes del traspaso exige un **checklist de validación**: corrección de marcas inválidas/con error, configuración previa de qué conceptos reciben qué cálculos (horas extra, descuento por atraso), y revisión vía reportes de apoyo.
- Reportes de soporte al traspaso: **Reporte Semanal Persona** y **Reporte Semanal por RUT** — reportes "avanzados" donde el usuario elige qué campos ver (Real vs. Teórico: entrada, salida, entrada/salida colación).
- Reporte adicional "**Movimientos del Mes**" (altas/bajas, ausentismos, vacaciones, variaciones de sueldo base, variaciones de tipo de contrato) — exportable a Excel con una hoja por tipo de movimiento.
- Reporte "**Maestro de Empleados**" — descarga masiva de fichas de colaboradores con su último contrato vigente.
- Un dato clave: si un trabajador tiene una marca inválida, **el sistema bloquea el traspaso de ese trabajador** hasta corregirla. Esto es un patrón de integridad de datos que vale la pena replicar.
### 2.2 Buk — Integración nativa con motor de nómina propio
- Diferenciador central: Buk no "exporta a payroll", **es** payroll y asistencia en la misma plataforma, por lo que horas extra, atrasos e inasistencias <cite index="9-1">se reflejan automáticamente en el cálculo del pago y en el timbrado de recibos en tiempo real, eliminando los errores manuales</cite> (contexto México/CFDI, pero el patrón de automatización aplica igual en Chile).
- Reportes: <cite index="11-1">reportería en tiempo real con acceso inmediato a reportes de rotación, remuneraciones y contratos</cite> (módulo "Lounge").
- Para clientes que NO usan el ecosistema Buk completo, exponen **API RESTful para exportar datos a sistemas externos**, más integraciones ya construidas (SSO SAML, licencias médicas vía IMED/Medipass, registro de contratos vía MiDT).
- Reportes exportables en Excel/Word/PDF desde el portal del trabajador (ej. reporte de asistencia con filtro por rango de fechas, reporte de excesos de jornada semanal).
- Terceros (ej. Workera) han construido integraciones nativas leyendo la API de Buk con **API Key de 24 caracteres** por empresa — patrón de integración simple que conviene imitar para que otros puedan integrarse con Kolvi también.
### 2.3 GeoVictoria — Integraciones como ecosistema, no como feature aislada
- Mensaje de marketing: <cite index="2-1">+50 Reportes. Integraciones disponibles. El sistema de asistencia de GeoVictoria opera con los softwares de Recursos Humanos y ERP más importantes del mercado</cite>.
- Integraciones confirmadas: **Nubox**, **Rankmi** (integración nativa bidireccional: asistencia → cálculo de remuneraciones, y marcaje desde la app de Rankmi), **Laudus** (Chile, contable/ERP), y en otros mercados SAP, Oracle, ADP, Softland.
- Con Laudus específicamente: <cite index="1-1">la integración garantiza que la información llegue limpia, validada y lista para su uso en la generación de liquidaciones o reportes contables</cite>.
- Exponen una **API que entrega datos de asistencia, turnos, permisos, horas extra y atrasos** para que terceros (BPO de remuneraciones, ERPs) consuman directamente — <cite index="8-1">GeoVictoria expone datos de asistencia, turnos, permisos, horas extra y atrasos a través de su API</cite>.
- Patrón de negocio relevante: la integración es también **canal de distribución** (partnerships con ERPs contables masivos usados por PYMEs chilenas) — Nubox en particular es altamente relevante para el segmento objetivo de Kolvi.
### 2.3.1 ⚠️ Hallazgo crítico: GeoVictoria ya tiene integración nativa con Nubox
 
Esto **no estaba confirmado** en la sesión anterior y cambia la prioridad de este feature: GeoVictoria y Nubox anunciaron una **alianza con integración nativa** (no solo exportación manual) donde <cite index="47-1">los registros de asistencia y cálculos laborales se sincronizan automáticamente, evitando reprocesos, errores y diferencias en las liquidaciones</cite>. Específicamente:
 
- Nubox <cite index="50-1">y GeoVictoria se han aliado para ofrecer una solución integrada que simplifica el registro de asistencia y garantiza el cumplimiento normativo de manera eficiente y automatizada</cite>, apuntando explícitamente a la Ley de 40 Horas.
- El mecanismo es vía **credenciales API**: el centro de ayuda de Nubox tiene artículos dedicados a "¿Cómo puedo obtener las credenciales API de GeoVictoria?", "¿Cómo puedo agregar las credenciales API de GeoVictoria a Nubox Remuneraciones?" y "¿Cómo sincronizar los datos de asistencia de GeoVictoria en el sistema de remuneraciones de Nubox?" — es decir, el usuario final conecta ambas plataformas desde la configuración de Nubox, sin exportar/importar archivos manualmente.
- Nubox marca esto como diferenciador de venta propio: <cite index="47-1">Nuestro Control de Asistencia con GeoVictoria es el primero en Chile en contar con la Resolución Exenta N°38</cite> — es decir, están usando el mismo ángulo de compliance DT que Kolvi quiere usar como diferenciador.
**Implicancia para Kolvi:** la brecha frente a GeoVictoria en este feature es más profunda de lo estimado — no es solo "exportar un archivo a Nubox", es **igualar una integración nativa bidireccional vía API** que ya está en el mercado y siendo promocionada activamente a las +18.000 empresas que usan Nubox. Esto sube la prioridad de RF-5 (API) y de una integración nativa real con Nubox, y reduce el valor relativo de quedarse solo en "exportación de archivo".
 
### 2.3.2 Formato de importación de Nubox (confirmado)
 
Nubox tiene una función de **Carga Masiva de Haberes y Descuentos Variables** (Utilitarios → Importar → Tipo de información: "Carga Masiva de Haberes y Descuentos Variables"), que es la vía estándar por la que cualquier sistema externo (incluyendo Kolvi, si no se construye una integración nativa vía API) puede inyectar datos de asistencia a la liquidación de sueldo. La estructura del archivo es: <cite index="39-1">PERIODO: Mes y año en el que aplicará el concepto. FUNCIONARIO: Indicar al funcionario que se aplicara el Haber/Descuento, pudiendo identificarlo por código o por Nombre. CODIGO DE HABER DESCUENTO: Indicar el concepto, identificándolo por código. MONTO/DÍAS/HORAS: Indicar la cantidad de días, horas o monto con el que debe realizarse el cálculo para la liquidación de sueldo</cite>.
 
Esto confirma y simplifica bastante el RF-4: **la plantilla Nubox del MVP es una tabla plana de 4 columnas** (Período, RUT/Código Funcionario, Código de Haber/Descuento, Monto-Días-Horas), una fila por concepto por trabajador por período. Cada "haber/descuento" (horas extra, atraso, ausentismo) se mapea a un código que el cliente configura en su propio Nubox — Kolvi solo necesita exponer, por trabajador y período, los conceptos ya calculados (horas extra por tipo, días de atraso, días de ausentismo) en filas separadas con esta estructura.
 
### 2.3.3 Modelo de referencia para la API de Kolvi (RF-5): estructura real de la API de GeoVictoria
 
La documentación pública de la API de GeoVictoria (API-GV3, wiki.geovictoria.com) da un modelo concreto y ya validado en el mercado de qué debe exponer una API de asistencia orientada a remuneraciones. Los endpoints más relevantes para Kolvi:
 
- **Login** (`POST /api/v1/Login`): autenticación con `Clave API` + `Secreto` → devuelve un token (expira a las 5 horas). Patrón simple de aplicar en Kolvi con Sanctum.
- **AttendanceBook** (`POST /api/v1/AttendanceBook`): dado un rango de fechas y una lista de IDs de colaboradores, devuelve por día: marcas (entrada/salida/colación) con origen (API/Web/reloj), turno asignado, atrasos, salidas anticipadas, horas trabajadas/no trabajadas, horas extra (antes/después de turno, aprobadas vs. asignadas, desglosadas por porcentaje), permisos vigentes con su tipo.
- **Consolidated** (`POST /api/v1/Consolidated`): resumen por trabajador para un período — horas trabajadas, horas no trabajadas, horas extra autorizadas totales, cantidad de inasistencias, desglose de horas extra por porcentaje.
- **Consolidated/Extended**: el mismo resumen pero con el detalle que realmente importa para nómina: domingos y feriados trabajados (con horas), y un desglose de **días pagados vs. no pagados** (`PaidDays.WorkedDays`, `PaidDays.Vacations`, `PaidDays.PaidTimeOffDays`, `NonPaidDays.UnjustifiedAbsenseDays`, `NonPaidDays.Leaves`, `NonPaidDays.NonPaidTimeOffDays`) — esta es prácticamente la estructura ideal para el "Resumen de Remuneraciones por Período" del RF-1.
**Recomendación concreta:** diseñar el endpoint `GET /api/v1/attendance/summary` de Kolvi calcando la forma de `Consolidated/Extended` de GeoVictoria (con nombres en español para diferenciarse: `horas_trabajadas`, `horas_no_trabajadas`, `horas_extra_autorizadas`, `dias_pagados`, `dias_no_pagados`, desglose por tipo de ausentismo). Replicar un modelo ya probado en producción reduce el riesgo de diseño y facilita que integradores que ya conocen GeoVictoria entiendan Kolvi rápido.
 
### 2.4 Patrón común entre los tres
 
| Elemento | Talana | Buk | GeoVictoria |
|---|---|---|---|
| Traspaso/integración nativa a payroll propio | Sí (mismo proveedor) | Sí (mismo proveedor) | No tiene payroll propio |
| Integración con ERPs/payroll externos | Parcial | API REST | Nubox, Rankmi, Laudus, SAP, Oracle, ADP |
| Reportes pre-traspaso de validación | Sí (semanal persona/RUT) | Sí (portal trabajador) | Sí (+50 reportes) |
| Exportación multi-formato | Excel | Excel/Word/PDF | Excel/CSV/API |
| Bloqueo de traspaso por datos inválidos | Sí, explícito | No confirmado | No confirmado |
| API pública documentada para terceros | No confirmado | Sí | Sí |
 
**Conclusión estratégica:** Kolvi no tiene (ni debería en el corto plazo) un módulo de remuneraciones propio, por lo que el modelo correcto es el de **GeoVictoria**: reportería robusta + exportación multi-formato + API/integraciones con los sistemas contables/nómina que ya usan las PYMEs chilenas (Nubox es el más relevante por volumen de mercado PYME, luego Buk/Talana como destino si el cliente usa esos ecosistemas solo para remuneraciones).
 
---
 
## 3. Problema y Oportunidad
 
**Problema actual:** Kolvi no tiene forma de sacar la información de asistencia (horas trabajadas, horas extra, atrasos, ausentismos, licencias, vacaciones) en un formato que un contador/analista de remuneraciones pueda usar para calcular sueldos, ni de integrarse con los sistemas de nómina que ya usan las PYMEs chilenas. Esto:
- Bloquea la venta a cualquier PYME que ya tenga un proceso de remuneraciones externo (la inmensa mayoría del segmento objetivo).
- Fuerza a los prospectos actuales a evaluar Buk/Talana/GeoVictoria solo por este feature, aunque Kolvi sea superior en compliance DT.
- Es una brecha marcada como "MISSING" y de alta prioridad en el MVP Gap Analysis (#9 Payroll Integration Preparation) y como "Alta" en el Gap Analysis vs GeoVictoria.
**Oportunidad:** Convertir esta brecha en un diferenciador de "confiabilidad de datos para nómina" — aprovechando que Kolvi ya tiene el motor de cálculo de horas/atrasos/HHEE construido para los reportes DT, el trabajo incremental es de **capa de presentación, validación y exportación**, no de recalcular nada desde cero.
 
---
 
## 4. Objetivos y Métricas de Éxito
 
| Objetivo | Métrica |
|---|---|
| Cerrar la brecha de paridad con competidores | Reporte de asistencia/remuneraciones exportable disponible en Excel, CSV y PDF |
| Habilitar integración con el ecosistema contable PYME chileno | Al menos 1 integración nativa (Nubox) + formato de exportación genérico configurable para el resto |
| Reducir fricción de venta | Ningún prospecto descarta Kolvi por "no exporta a mi sistema de remuneraciones" |
| Garantizar integridad de datos hacia nómina | 0% de traspasos con marcas no corregidas/pendientes sin advertencia explícita al usuario |
| Adopción | % de tenants activos que generan al menos 1 reporte de remuneraciones por período de pago |
 
---
 
## 5. Alcance
 
### 5.1 Dentro de alcance (MVP de este feature)
1. **Motor de reportes configurables** (builder simple, no drag-and-drop complejo) sobre datos ya calculados: horas trabajadas, horas extra, atrasos, ausentismos, licencias, vacaciones, permisos.
2. **Reporte "Resumen para Remuneraciones"** por período (quincena/mes), por trabajador y consolidado por empresa, con columnas mapeables a conceptos de nómina (horas normales, HHEE 50%, HHEE 100% según corresponda, descuento por atraso, días de ausentismo justificado/injustificado).
3. **Validación pre-exportación**: bloqueo/advertencia si existen marcas con errores, anomalías sin resolver o modificaciones pendientes de aprobación (patrón Talana).
4. **Exportación multi-formato**: Excel (.xlsx), CSV, PDF.
5. **Plantillas de exportación por destino**: al menos una plantilla genérica configurable (mapeo de columnas por el usuario) + una plantilla específica para **Nubox** (por volumen de mercado PYME en Chile).
6. **API REST de solo lectura** para exponer los mismos datos (asistencia, horas extra, atrasos, permisos) que expone GeoVictoria, para que integradores/terceros construyan sobre Kolvi sin depender de exportación manual.
7. **Historial y auditoría de exportaciones**: quién exportó, cuándo, qué período, qué formato — requisito de trazabilidad tanto para el cliente como potencialmente para DT.
8. **Filtros**: por sucursal, centro de costo, cargo, fecha, trabajador individual o masivo.
### 5.2 Fuera de alcance (fases futuras / explícitamente no incluido)
- Cálculo de liquidaciones de sueldo o motor de nómina propio (Kolvi no compite en ese espacio).
- Integraciones nativas con Rankmi, Buk, Talana, SAP, Oracle, ADP, Softland (se evalúan en fase 2 según demanda real de clientes).
- Timbrado electrónico o cumplimiento tributario (no aplica en Chile de la misma forma que México/CFDI).
- Dashboards ejecutivos/BI avanzado (cubierto en otro gap — "Advanced Reporting & Analytics", #11 del MVP Gap Analysis, es un epic separado).
---
 
## 6. Requisitos Funcionales
 
### RF-1: Catálogo de Reportes
El sistema debe ofrecer, como mínimo, estos reportes predefinidos (nombres sujetos a UX):
 
| Reporte | Nivel | Campos clave | Formato de salida |
|---|---|---|---|
| Resumen de Remuneraciones por Período | Empresa / consolidado | Horas normales, HHEE, atrasos, ausentismos por trabajador | Excel, CSV, PDF |
| Detalle Semanal por Trabajador | Individual | Entrada/salida real vs. teórica, colación, anomalías (equivalente a "Reporte Semanal Persona" de Talana) | Excel, PDF |
| Movimientos del Período | Empresa | Altas/bajas, inicio/fin de licencias, vacaciones aprobadas, cambios de turno | Excel (multi-hoja por tipo) |
| Maestro de Trabajadores | Empresa | Ficha completa + último contrato vigente, sucursal, centro de costo | Excel, CSV |
| Excesos de Jornada / HHEE | Individual o consolidado | Horas extra por semana con detalle de justificación (pactada/no pactada) | Excel, PDF |
 
### RF-2: Validación de Integridad Pre-Exportación
- Antes de generar cualquier exportación con fines de nómina, el sistema debe verificar:
  - Marcas con estado "error" o "anomalía" sin resolver dentro del período.
  - Modificaciones de marca (`MarkModification`) pendientes de aprobación.
- Si existen, el sistema debe **mostrar una advertencia bloqueante** (no impedir la exportación pero requerir confirmación explícita) listando los trabajadores/días afectados, replicando el patrón de Talana de excluir/corregir antes de traspasar.
### RF-3: Constructor de Plantillas de Exportación
- El usuario (admin/RRHH) puede definir un mapeo de columnas: qué campo interno de Kolvi corresponde a qué columna/nombre en el archivo exportado, y en qué orden.
- Debe soportar guardar plantillas reutilizables (ej. "Plantilla Nubox", "Plantilla Contador Externo").
- Plantilla predefinida y no editable: "Nubox" (mapeo específico, ver RF-4).
### RF-4: Integración Nativa — Nubox (prioridad 1)
- Justificación: Nubox es el software contable/tributario más usado por PYMEs en Chile, y **GeoVictoria ya tiene una integración nativa vía API con Nubox** (alianza anunciada, activamente promocionada — ver sección 2.3.1). Kolvi necesita al menos paridad de exportación, e idealmente moverse hacia integración nativa para no quedar en desventaja competitiva directa.
- **Formato confirmado (MVP, vía exportación de archivo):** plantilla "Carga Masiva de Haberes y Descuentos Variables" de Nubox — 4 columnas planas:
  | Columna | Descripción |
  |---|---|
  | PERIODO | Mes y año (ej. 08/2026) |
  | FUNCIONARIO | RUT o código del trabajador (debe coincidir con el maestro de funcionarios ya cargado en Nubox por el cliente) |
  | CODIGO DE HABER DESCUENTO | Código del concepto ya configurado por el cliente en su Nubox (ej. horas extra 50%, atraso, día sin licencia) |
  | MONTO/DÍAS/HORAS | Cantidad de días, horas o monto según corresponda al concepto |
  Cada fila = un concepto por trabajador por período. Kolvi debe generar una fila por cada tipo de haber/descuento calculado (ej. si un trabajador tuvo horas extra 50% y un atraso en el período, son 2 filas). El mapeo de "qué código de Nubox corresponde a horas extra 50% vs. atraso" **lo define el cliente** al configurar su plantilla (ver RF-3) — Kolvi no debe asumir códigos fijos, porque cada empresa configura sus propios haberes/descuentos en Nubox.
- **Fase 2 (post-MVP):** evaluar integración nativa vía API (misma lógica que la alianza GeoVictoria-Nubox), lo que requeriría que Nubox habilite a Kolvi como partner de integración — validar factibilidad comercial/técnica con Nubox antes de comprometer desarrollo.
### RF-5: API REST de Datos de Asistencia (solo lectura)
- Endpoints mínimos:
  - `GET /api/v1/attendance/summary?period=&employee_id=`
  - `GET /api/v1/attendance/overtime?period=`
  - `GET /api/v1/attendance/absences?period=`
  - `GET /api/v1/employees` (maestro)
- Autenticación vía API Key por tenant (patrón Buk: key alfanumérica generada por empresa, regenerable desde el panel de admin).
- Rate limiting por tenant (ya identificado como gap técnico general en el MVP Gap Analysis, #10).
- Documentación pública tipo OpenAPI/Swagger — condición para que terceros (BPOs de remuneraciones, integradores) construyan sobre Kolvi, como hace GeoVictoria con Rex+/BPOs.
### RF-6: Auditoría de Exportaciones
- Registrar: usuario, timestamp, tipo de reporte, período consultado, formato, filtros aplicados.
- Visible en un log accesible por el admin del tenant (no solo superadmin).
### RF-7: Filtros y Segmentación
- Por sucursal, centro de costo, cargo, tipo de contrato, trabajador individual, rango de fechas.
- Selección masiva con exclusión (patrón "Excluir" de Talana: seleccionar todo menos X).
---
 
## 7. Requisitos No Funcionales
 
- **Performance**: exportaciones de hasta 500 trabajadores por período deben completarse en <30s; sobre ese umbral, debe procesarse como **job asíncrono en cola** (Laravel Queues) con notificación al usuario cuando el archivo esté listo (evitar timeout de request síncrono — patrón ya usado por Talana, que envía el reporte por correo cuando termina de generarse).
- **Multi-tenancy**: todos los reportes y la API deben respetar aislamiento de datos por tenant sin excepción.
- **Seguridad**: datos de remuneraciones (horas, sueldo base si se llega a incluir) son datos sensibles — exportaciones deben ir cifradas en tránsito (HTTPS) y los archivos generados no deben quedar accesibles públicamente sin autenticación (URLs firmadas con expiración).
- **Auditabilidad**: todo acceso a datos vía API debe quedar logueado (quién, cuándo, qué endpoint).
- **Compatibilidad de formato**: el CSV exportado debe usar codificación UTF-8 y separador configurable (coma vs. punto y coma), dado que Excel en configuración regional chilena a menudo requiere punto y coma.
---
 
## 8. Consideraciones Técnicas (Laravel Filament)
 
- **Generación de reportes**: usar jobs en cola (`ShouldQueue`) para cualquier exportación que involucre más de N trabajadores o rangos de fecha amplios. Considerar `maatwebsite/excel` (Laravel Excel) para Excel/CSV, y la librería PDF que ya se use en los 4 reportes DT para mantener consistencia visual.
- **Plantillas de mapeo**: modelar como tabla `export_templates` (tenant_id, name, field_mappings JSON, is_system_template bool) para diferenciar la plantilla Nubox (sistema, no editable) de las plantillas custom del usuario.
- **API**: usar Laravel Sanctum con tokens por tenant en vez de OAuth completo para el MVP — más simple de implementar y suficiente para el caso de uso B2B server-to-server.
- **Auditoría**: aprovechar/extender el mismo mecanismo de audit log que ya deba existir para `MarkModification` (Art. 44 Res. 38) en vez de construir uno paralelo.
- **Validación pre-exportación**: reutilizar el motor de detección de anomalías que ya alimenta el Reporte de Jornada Diaria DT — es el mismo dato, solo se re-consulta antes del traspaso.
---
 
## 9. Historias de Usuario (para desglose en Jira)
 
1. Como **encargado de RRHH**, quiero generar un resumen de horas trabajadas y horas extra por período para pasárselo a mi contador externo, sin tener que armarlo manualmente en Excel.
2. Como **encargado de RRHH**, quiero que el sistema me avise si hay marcas sin corregir antes de exportar, para no enviar información errónea a remuneraciones.
3. Como **contador/administrador de nómina externo**, quiero recibir el archivo en un formato que Nubox pueda importar directamente.
4. Como **founder/admin de Kolvi**, quiero exponer una API para que integradores o BPOs de remuneraciones puedan consumir los datos de asistencia sin pedirme un export manual cada vez.
5. Como **admin de tenant**, quiero ver un historial de qué reportes se generaron y quién los generó, para efectos de auditoría interna.
---
 
## 10. Riesgos y Preguntas Abiertas
 
- ~~Formato exacto de importación de Nubox~~ — **RESUELTO**: confirmado en sección 2.3.2 (plantilla de 4 columnas: Período, Funcionario, Código Haber/Descuento, Monto/Días/Horas).
- **GeoVictoria ya tiene integración nativa con Nubox** (sección 2.3.1) — esto sube la urgencia real de este feature más allá de lo estimado originalmente; evaluar si vale la pena acercarse a Nubox como partner de integración en paralelo al desarrollo del MVP de exportación.
- **Demanda real de integraciones con Buk/Talana/Rankmi como destino**: antes de invertir 4-6 semanas por integración (estimado ya en el Gap Analysis vs GeoVictoria), validar con clientes/prospectos reales cuál sistema de nómina usan hoy.
- **Alcance de la API pública**: exponer una API implica compromiso de mantenimiento y documentación — pero dado que GeoVictoria compite justamente con una API robusta (AttendanceBook, Consolidated, Extended — ver 2.3.3), no tener API propia deja a Kolvi en desventaja para el segmento que ya evalúa integraciones. Evaluar si el MVP puede lanzar con una versión mínima de esta API en vez de posponerla completamente a Fase 3.
- **Riesgo de alcance**: este feature podría "inflarse" hacia un mini-motor de nómina si no se mantiene la frontera clara del punto 5.2 (fuera de alcance).
---
 
## 11. Fasificación Propuesta
 
| Fase | Contenido | Estimado |
|---|---|---|
| Fase 1 (MVP) | RF-1, RF-2, RF-6, RF-7 + exportación Excel/CSV/PDF genérica | 2-3 semanas |
| Fase 2 | RF-3 (constructor de plantillas) + RF-4 (Nubox) | 2-3 semanas adicionales, sujeto a confirmar formato Nubox |
| Fase 3 | RF-5 (API REST pública) | 1-2 semanas, evaluar demanda antes de priorizar |
| Fase 4 (no comprometida) | Integraciones nativas Buk/Talana/Rankmi como destino | 4-6 semanas por integración, solo bajo demanda validada |
 
---
 
## 12. Fuentes
 
- MVP Gap Analysis interno (Chilean_AMS_SaaS - MVP Gap Analysis.md) — gap #9 Payroll Integration, #11 Advanced Reporting.
- Kolvi vs GeoVictoria Gap Analysis (docx interno).
- Talana_documentation.pdf (cargado en proyecto): Traspaso a Remuneraciones, Reporte Semanal Persona, Movimientos del Mes, Maestro de Empleados, Certificación de Asistencia y Turnos.
- Resolución 38 EXENTA (09/05/2024) — estructura de reportes DT (referencia, no exportación a nómina).
- Buk: buk.cl/precios, buk.cl/productos/administracion/control-de-asistencia, comparasoftware.cl/buk, help.workera.com (integración BUK vía API).
- GeoVictoria: geovictoria.com (CL/CO/MX), lp.geovictoria.com/siigo/mexico, laudus.cl (alianza GeoVictoria-Laudus), rankmi.com (integración GeoVictoria-Rankmi), bst.cl (BPO remuneraciones, mención API GeoVictoria).
- **Nubox** (Centro de Ayuda, help.nubox.com): colección "Remuneraciones", artículo "Carga Masiva de Haberes y Descuentos Variables" (formato de importación confirmado), colección "Integración con GeoVictoria" (credenciales API, sincronización).
- **Nubox** (sitio/blog, nubox.com y blog.nubox.com): "Software Control de Asistencia | Cumple con la Ley de 42 Horas" (alianza GeoVictoria), "Cumple la Ley de 40 Horas con la Alianza de Nubox y GeoVictoria", "Portal de Colaboradores de Nubox".
- **GeoVictoria API** — documentación técnica pública "API GV3" (wiki.geovictoria.com/wp-content/uploads/2021/07/API-GV3.pdf): especificación completa de endpoints AttendanceBook, Consolidated, Consolidated/Extended, TimeOff, Shift, User, Punch.
