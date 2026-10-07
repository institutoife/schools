# Actualizaci?n oficial de colegios: gesti?n 2025

Abre **Administraci?n ? Actualizar desde Ministerio** (`/admin/ministry-school-import`). Carga el Excel y pulsa **Cargar Excel e iniciar actualizaci?n**.

El Excel aporta solo el RUE en A y el departamento en C para clasificar los JSON. Se ignoran las dem?s columnas. Se espera un encabezado **C?digo RUE**, que puede estar despu?s de filas informativas. Se leen todas las hojas, se deduplican RUE y se rechazan departamentos contradictorios para un mismo RUE.

El sistema procesa un departamento a la vez. Por cada RUE consulta `https://seie.minedu.gob.bo/reportes/mapas_unidades_educativas/ficha/ver/{RUE}`. Valida el RUE de la respuesta y la gesti?n 2025. Extrae datos administrativos, ubicaci?n oficial, servicios, ambientes, bachillerato y estad?sticas de matr?cula, promovidos, reprobados y abandono por a?o y sexo hasta 2025 inclusive. Registra fecha de consulta y a?os disponibles. No atribuye el a?o 2025 a infraestructura si la ficha no publica esa fecha.

Al terminar un departamento, genera su JSON y lo importa autom?ticamente en una transacci?n. Actualiza colegios por RUE, ubicaci?n/servicios/ambientes por colegio y estad?sticas por colegio, categor?a y a?o. Conserva datos existentes cuando la ficha no publica un valor (vac?o o `--`), distingue cero de ausencia y no elimina colegios ausentes del Excel. Un error de importaci?n revierte los cambios del departamento. La columna C clasifica los archivos; el departamento guardado como ubicaci?n proviene de la ficha.

Las fichas tienen hasta tres intentos, l?mite de conexi?n y tiempo de respuesta. Los errores aparecen por RUE y el sistema sigue con otras fichas. **Reintentar fichas fallidas** consulta las fichas pendientes de error y vuelve a importar el departamento. Los procesos incompletos muestran **con_errores**. Los JSON parciales contienen solo fichas v?lidas y el reporte identifica las pendientes. Al terminar puedes descargar los nueve JSON y el reporte en ZIP.

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
