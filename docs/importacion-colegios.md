# Actualizaci?n oficial de colegios: gesti?n 2025

Abre **Administraci?n ? Actualizar desde Ministerio** (`/admin/ministry-school-import`). Carga el Excel y pulsa **Cargar Excel e iniciar actualizaci?n**.

El Excel aporta solo el RUE en A y el departamento en C para clasificar los JSON. Se ignoran las dem?s columnas. Se espera un encabezado **C?digo RUE**, que puede estar despu?s de filas informativas. Se leen todas las hojas, se deduplican RUE y se rechazan departamentos contradictorios para un mismo RUE.

El sistema procesa un departamento a la vez. Por cada RUE consulta `https://seie.minedu.gob.bo/reportes/mapas_unidades_educativas/ficha/ver/{RUE}`. Valida el RUE de la respuesta y la gesti?n 2025. Extrae datos administrativos, ubicaci?n oficial, servicios, ambientes, bachillerato y estad?sticas de matr?cula, promovidos, reprobados y abandono por a?o y sexo hasta 2025 inclusive. Registra fecha de consulta y a?os disponibles. No atribuye el a?o 2025 a infraestructura si la ficha no publica esa fecha.

Al terminar un departamento, genera su JSON y lo importa autom?ticamente en una transacci?n. Actualiza colegios por RUE, ubicaci?n/servicios/ambientes por colegio y estad?sticas por colegio, categor?a y a?o. Conserva datos existentes cuando la ficha no publica un valor (vac?o o `--`), distingue cero de ausencia y no elimina colegios ausentes del Excel. Un error de importaci?n revierte los cambios del departamento. La columna C clasifica los archivos; el departamento guardado como ubicaci?n proviene de la ficha.

Las fichas tienen hasta tres intentos, l?mite de conexi?n y tiempo de respuesta. Los errores aparecen por RUE y el sistema sigue con otras fichas. **Reintentar fichas fallidas** consulta las fichas pendientes de error y vuelve a importar el departamento. Los procesos incompletos muestran **con_errores**. Los JSON parciales contienen solo fichas v?lidas y el reporte identifica las pendientes. Al terminar puedes descargar los nueve JSON y el reporte en ZIP.

## Reutilizar la descarga local en el servidor (flujo anual recomendado)

1. En local, carga el Excel en `/admin/ministry-school-import` y espera a que termine la consulta al Ministerio. Resuelve las fichas fallidas y revisa el reporte antes de llevarlo al servidor.
2. Pulsa **Descargar 9 JSON y reporte**. Conserva ese ZIP como respaldo de la descarga anual.
3. En la misma sección del servidor, usa **Importar JSON ya descargados**. Sube el ZIP directamente o selecciona los archivos `colegios_departamento.json`, sin renombrarlos. Puedes actualizar uno, varios o los nueve departamentos.
4. Pulsa **Importar JSON a la base de datos** y sigue el progreso por departamento. Esta opción no realiza consultas HTTP al Ministerio ni necesita el Excel. El trabajo aparece en **Importaciones guardadas** con origen **JSON**.

Los archivos se validan por completo antes de registrar el trabajo: estructura, RUE de ocho dígitos, RUE repetidos, departamento del archivo y gestión 2025. Los datos de ubicación oficial se conservan aunque difieran de la clasificación del Excel. No se admiten los antiguos JSON que contienen solamente RUE y departamento. Los JSON vacíos de un departamento son válidos dentro de una carga que contenga colegios.

La importación avanza en lotes de 50 colegios y guarda su posición. Cada lote usa una transacción; si falla, se revierte ese lote y puede reintentarse sin repetir los lotes terminados. Los departamentos no cargados quedan como **No cargado**. Los nuevos se crean y los existentes se actualizan por RUE; se preservan los campos ausentes y no se eliminan colegios. Subir de nuevo el mismo archivo no duplica colegios ni estadísticas. Una descarga parcial solo actualiza los colegios presentes: importar el ZIP no completa las fichas ausentes.

Mantén el panel abierto para que avance, o usa el ejecutor descrito abajo para continuar con el navegador cerrado. La descarga lenta de fichas se realiza una sola vez en local; en el servidor solo se valida e importa. Para el próximo ciclo anual deberás obtener una nueva descarga local cuando el Ministerio publique los datos. El extractor y el validador actuales están limitados a datos hasta 2025 y deben adaptarse a la nueva gestión antes de importar años posteriores.

El panel admite hasta 50 MB por archivo y 100 MB de contenido JSON en total (también al descomprimir el ZIP). Livewire tiene configurado el límite de 50 MB. PHP y el servidor web deben permitir la carga: por ejemplo, `upload_max_filesize = 50M` y `post_max_size = 64M` para un ZIP; para varios JSON grandes, sube los departamentos por separado o aumenta el límite total del servidor. Reinicia PHP/Apache después de cambiar su configuración. Los archivos se guardan en almacenamiento privado.

## Ejecutor del servidor

La secci?n avanza autom?ticamente mientras est? abierta. El progreso queda guardado y puedes retomarlo desde **Importaciones guardadas**. Para que contin?e con el navegador cerrado, mantener activo:

```powershell
php artisan schools:process-ministry --watch
```

En Windows/XAMPP se puede iniciar oculto:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-ministry-sync.ps1
```

El ejecutor espera cargas del panel; no inicia una extracci?n por s? mismo. Hay bloqueo para impedir ejecutores duplicados y otro por importaci?n para evitar consultas simult?neas. Se limita el ritmo de consultas. MySQL y acceso HTTPS al Ministerio deben estar disponibles; el certificado TLS se verifica. Despu?s de reiniciar Windows vuelve a iniciar el ejecutor o configura este script en el Programador de tareas al iniciar sesi?n. En Linux puede mantenerse con systemd/Supervisor.

Un programador de tareas existente tambi?n puede ejecutar peri?dicamente `php artisan schools:process-ministry --steps=10`. Para registrar un Excel por consola usa `php artisan schools:import-excel "ruta/colegios.xlsx"`; registra el trabajo y no importa otros datos del Excel.

Los archivos privados por importaci?n quedan en `storage/app/importaciones/ministerio/{id}/`: Excel original, estado, fichas por RUE y nueve JSON en `json/`. Para volver a importar los JSON oficiales usa `php artisan schools:import-json "directorio/json"`. Este comando valida su estructura y gesti?n 2025 registrada. El antiguo `import:schools` conserva su funcionamiento previo y no es parte de este flujo.

La importaci?n anterior hecha solo desde el Excel no consult? fichas ni actualiz? estad?sticas 2025. Sus JSON en `storage/app/importaciones/colegios` corresponden al flujo anterior. Usa la nueva secci?n para actualizar desde el Ministerio.
