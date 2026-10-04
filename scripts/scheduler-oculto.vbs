' Ejecuta "php artisan schedule:run" sin abrir ventana. Lo invoca cada minuto la tarea
' programada de Windows "ERP Academias - scheduler" (correos en cola, recordatorios,
' mora de alumnos y facturación a academias).
Set fso = CreateObject("Scripting.FileSystemObject")
backend = fso.GetParentFolderName(fso.GetParentFolderName(WScript.ScriptFullName))
php = "C:\wamp64\bin\php\php8.2.18\php.exe"
CreateObject("WScript.Shell").Run """" & php & """ """ & backend & "\artisan"" schedule:run", 0, False
