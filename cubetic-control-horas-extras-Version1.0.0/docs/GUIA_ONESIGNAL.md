# Guía de Implementación de OneSignal para Notificaciones Push

Esta guía explica paso a paso cómo configurar e implementar las notificaciones push con OneSignal en el plugin Control Horas Extras.

---

## 📋 Tabla de Contenidos

1. [Requisitos Previos](#requisitos-previos)
2. [Configuración en OneSignal Dashboard](#configuración-en-onesignal-dashboard)
3. [Configuración en el Plugin](#configuración-en-el-plugin)
4. [Verificación de la Instalación](#verificación-de-la-instalación)
5. [Flujo de Notificaciones](#flujo-de-notificaciones)
6. [Troubleshooting](#troubleshooting)
7. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## 1. Requisitos Previos

Antes de comenzar, asegúrate de tener:

- ✅ Cuenta en OneSignal (gratis en https://onesignal.com)
- ✅ Plugin Control Horas Extras instalado y activo
- ✅ Permisos de escritura en la raíz de WordPress (para crear `OneSignalSDKWorker.js`)
- ✅ Certificado SSL activo (HTTPS) para producción (requerido para notificaciones push)

---

## 2. Configuración en OneSignal Dashboard

### Paso 1: Crear una Nueva App en OneSignal

1. Inicia sesión en tu cuenta de OneSignal: https://onesignal.com
2. Haz clic en **"New App/Website"**
3. Completa el formulario:
   - **App Name**: `Control Horas Extras` (o el nombre que prefieras)
   - **Platform**: Selecciona **"Web Push"**
   - Haz clic en **"Create"**

### Paso 2: Configurar Web Push Platform

1. En el dashboard de tu nueva App, ve a **Settings > Platforms**
2. Haz clic en **"Web Push"** o **"Chrome & Firefox"**
3. Configura los siguientes campos:
   - **Site URL**: `https://tu-dominio.com` (tu URL de producción)
   - **Default Notification Icon URL**: URL de un icono (192x192px recomendado)
   - **Safari Web ID**: (Se genera automáticamente, anótalo si aparece)
4. Haz clic en **"Save"**

### Paso 3: Obtener las Credenciales

1. Ve a **Settings > Keys & IDs**
2. Anota los siguientes valores:
   - **OneSignal App ID**: Ejemplo: `1903f3a5-c349-48e0-90d1-c8513b7571cc`
   - **REST API Key**: Ejemplo: `os_v2_app_deb7hjodjfeobegrzbitw5lrzs4ji7ndf2xel2uf2twr6q2dzabzcxirk7yiab3pg22u6yzybxohpc4qpphi3ty4jcfhwkiz7bkz4fy`

⚠️ **IMPORTANTE**: Mantén estas credenciales seguras y no las compartas públicamente.

---

## 3. Configuración en el Plugin

### Paso 1: Configurar el Archivo de Configuración

1. Navega a la carpeta del plugin:
   ```
   wp-content/plugins/cubetic-control-horas-extras/config/
   ```

2. Abre el archivo `onesignal-config.php`

3. Completa los valores con las credenciales de OneSignal:
   ```php
   <?php
   return [
       // App ID de OneSignal (Settings > Keys & IDs)
       'app_id' => 'TU-APP-ID-AQUI',
       
       // REST API Key de OneSignal (Settings > Keys & IDs)
       'rest_api_key' => 'TU-REST-API-KEY-AQUI',
   ];
   ```

4. Guarda el archivo

### Paso 2: Verificar que el Plugin Está Activo

1. Ve a **Plugins** en el panel de administración de WordPress
2. Verifica que **"Control Horas Extras"** esté activo
3. Si no está activo, haz clic en **"Activar"**

### Paso 3: Verificar Archivo OneSignalSDKWorker.js

El plugin crea automáticamente el archivo `OneSignalSDKWorker.js` en la raíz de WordPress al activarse.

Para verificar:

1. Navega a la raíz de tu instalación de WordPress (donde está `wp-config.php`)
2. Busca el archivo `OneSignalSDKWorker.js`
3. Si no existe, el plugin intentará crearlo automáticamente en la próxima carga

**Contenido esperado del archivo:**
```javascript
importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");
```

---

## 4. Verificación de la Instalación

### Verificación en el Navegador

1. Abre tu sitio web en el navegador (Chrome, Firefox o Edge)
2. Abre la consola de desarrollador (F12)
3. Busca estos mensajes en la consola:
   - `[CHE OneSignal] SDK cargado e inicializado con App ID: ...`
   - `[CHE Notifications] OneSignal inicializado, permiso: ...`
   - `[CHE Notifications] Tags configurados: ...`

### Verificar Permisos de Notificaciones

1. El navegador debería mostrar una solicitud de permiso para notificaciones
2. Haz clic en **"Permitir"** para activar las notificaciones push
3. Si ya denegaste antes, puedes habilitarlas manualmente:
   - **Chrome**: Configuración > Privacidad y seguridad > Notificaciones > Tu sitio
   - **Firefox**: Configuración > Privacidad y seguridad > Permisos > Notificaciones

### Verificar Tags en OneSignal Dashboard

1. Ve a **Audience > All Users** en el dashboard de OneSignal
2. Busca tu usuario (debería aparecer después de que visites el sitio)
3. Haz clic en el usuario para ver sus tags:
   - `user_id`: ID del usuario en WordPress
   - `role_0`, `role_1`, etc.: Roles del usuario
   - `is_admin`: "true" si es admin
   - `is_worker`: "true" si es trabajador
   - `is_super_admin`: "true" si es super admin

---

## 5. Flujo de Notificaciones

### Cómo Funciona el Sistema

1. **Cuando ocurre un evento** (validar parte, solicitar vacaciones, etc.):
   - El plugin guarda la notificación en la base de datos
   - El plugin envía la notificación push a OneSignal
   - OneSignal envía la notificación a los usuarios correspondientes

2. **Cuando un usuario recibe la notificación**:
   - Si la página está abierta: Se muestra un toast/modal en pantalla
   - Si la página está cerrada: Aparece una notificación push del navegador
   - El badge de notificaciones se actualiza automáticamente

### Tipos de Notificaciones Implementadas

#### Para Administradores:
- 📅 **Nueva solicitud de vacaciones**: Cuando un trabajador solicita vacaciones
- 📝 **Nuevo parte de trabajo**: Cuando un trabajador envía un parte
- 👑 **Nuevo super administrador**: Cuando se crea un nuevo super admin

#### Para Trabajadores:
- ✅ **Vacaciones aprobadas**: Cuando se aprueba su solicitud de vacaciones
- ❌ **Vacaciones rechazadas**: Cuando se rechaza su solicitud
- ✔️ **Parte validado**: Cuando un admin valida su parte
- ✖️ **Parte rechazado**: Cuando un admin rechaza su parte

### Segmentación de Usuarios

Las notificaciones se envían usando **tags** de OneSignal basados en:

- **Usuario específico**: `user_id` = ID del usuario en WordPress
- **Rol del usuario**: `is_admin`, `is_worker`, `is_super_admin`
- **Roles adicionales**: `role_0`, `role_1`, etc.

Esto permite enviar notificaciones a:
- Usuarios específicos (notificaciones personales)
- Todos los administradores
- Todos los trabajadores
- Todos los super administradores

---

## 6. Troubleshooting

### Problema: Las notificaciones no llegan

**Solución 1: Verificar credenciales**
- Revisa que `app_id` y `rest_api_key` estén correctos en `onesignal-config.php`
- Verifica que las credenciales coincidan con las del dashboard de OneSignal

**Solución 2: Verificar permisos del navegador**
- Asegúrate de que el navegador tenga permisos para mostrar notificaciones
- Verifica en Configuración > Privacidad > Notificaciones

**Solución 3: Verificar consola del navegador**
- Abre F12 y busca errores en la consola
- Verifica que OneSignal se inicialice correctamente
- Busca mensajes que comiencen con `[CHE OneSignal]` o `[CHE Notifications]`

**Solución 4: Verificar HTTPS**
- OneSignal requiere HTTPS en producción
- Verifica que tu sitio tenga un certificado SSL válido

### Problema: OneSignalSDKWorker.js no existe

**Solución 1: Verificar permisos**
- Asegúrate de que WordPress tenga permisos de escritura en `ABSPATH` (raíz de WordPress)
- El archivo se crea automáticamente, pero necesita permisos

**Solución 2: Crear manualmente**
Si el plugin no puede crear el archivo automáticamente, créalo manualmente:

1. Navega a la raíz de WordPress (donde está `wp-config.php`)
2. Crea un archivo llamado `OneSignalSDKWorker.js`
3. Añade este contenido:
   ```javascript
   importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");
   ```
4. Guarda el archivo

### Problema: Tags no se configuran

**Solución 1: Verificar inicialización**
- Revisa la consola del navegador para ver si OneSignal se inicializa correctamente
- Verifica que `config.appId` esté definido en JavaScript

**Solución 2: Verificar usuario logueado**
- Los tags solo se configuran cuando el usuario está logueado
- Asegúrate de que el usuario tenga una sesión activa

**Solución 3: Verificar en OneSignal Dashboard**
- Ve a **Audience > All Users**
- Verifica que el usuario aparezca en la lista
- Revisa los tags del usuario

### Problema: Notificaciones duplicadas

**Solución 1: Verificar polling**
- El sistema tiene un fallback de polling (cada 2 minutos)
- Si recibes duplicados, puede ser que polling detecte la misma notificación
- Esto es normal y se resuelve automáticamente con el tracking de IDs

**Solución 2: Limpiar caché**
- Limpia el caché del navegador
- Recarga la página con Ctrl+Shift+R (Windows/Linux) o Cmd+Shift+R (Mac)

### Problema: Error "OneSignal no está configurado"

**Solución:**
- Verifica que `onesignal-config.php` exista en `config/`
- Verifica que `app_id` y `rest_api_key` no estén vacíos
- Verifica que el archivo retorne un array válido

---

## 7. Preguntas Frecuentes

### ¿Puedo usar OneSignal en localhost/desarrollo?

Sí, el plugin está configurado para funcionar en localhost. El código incluye `allowLocalhostAsSecureOrigin: true` para permitir desarrollo local.

### ¿Las notificaciones funcionan en todos los navegadores?

OneSignal soporta:
- ✅ Chrome (Android y Desktop)
- ✅ Firefox (Android y Desktop)
- ✅ Edge (Desktop)
- ✅ Safari (macOS e iOS) - requiere configuración adicional
- ⚠️ Safari iOS requiere configuración de Web Push en el dashboard

### ¿Qué pasa si OneSignal falla?

El sistema tiene un **fallback automático**:
- Si OneSignal no está disponible, usa **polling** (verificación cada 30 segundos)
- Las notificaciones se guardan en la base de datos siempre
- El badge y dropdown de notificaciones funcionan incluso sin OneSignal

### ¿Puedo personalizar las notificaciones?

Sí, puedes personalizar:
- **Títulos y mensajes**: Edita los métodos en `OneSignalService.php`
- **Iconos**: Configura un icono en el dashboard de OneSignal
- **Deep links**: Se configuran automáticamente según el tipo de notificación

### ¿Cómo pruebo las notificaciones?

1. **Como Admin**: Solicita vacaciones como trabajador → Deberías recibir notificación como admin
2. **Como Trabajador**: Valida un parte como admin → Deberías recibir notificación como trabajador
3. **Consola del navegador**: Revisa los logs que comienzan con `[CHE Notifications]`

### ¿Cuánto cuesta OneSignal?

OneSignal tiene un plan **gratuito** que incluye:
- Hasta 10,000 suscriptores
- Notificaciones ilimitadas
- Soporte para web, iOS y Android

Para más información: https://onesignal.com/pricing

---

## 📝 Archivos del Sistema

### Archivos Principales

- **`includes/Services/OneSignalService.php`**: Servicio principal para enviar notificaciones
- **`admin/Init.php`**: Carga SDK de OneSignal y configura inicialización
- **`assets/js/notifications/notifications.js`**: Lógica del cliente (tags, listeners, UI)
- **`config/onesignal-config.php`**: Configuración con credenciales (⚠️ NO subir a repositorios públicos)
- **`includes/activation/activation.php`**: Crea `OneSignalSDKWorker.js` automáticamente

### Archivos Generados Automáticamente

- **`OneSignalSDKWorker.js`** (en raíz de WordPress): Service Worker de OneSignal
- Se crea automáticamente al activar el plugin
- Se verifica en cada carga del plugin

---

## 🔒 Seguridad

### Protección de Credenciales

- ⚠️ **NUNCA** subas `onesignal-config.php` a repositorios públicos (GitHub, GitLab, etc.)
- Añade `config/onesignal-config.php` a tu `.gitignore`
- Usa variables de entorno o servicios de secretos para producción

### Recomendaciones

1. **Permisos de archivo**: `onesignal-config.php` debería tener permisos `600` (solo lectura para el propietario)
2. **HTTPS**: Usa siempre HTTPS en producción (requerido por OneSignal)
3. **REST API Key**: Mantén la REST API Key segura y no la compartas

---

## 📚 Recursos Adicionales

- [Documentación oficial de OneSignal](https://documentation.onesignal.com/)
- [OneSignal Dashboard](https://app.onesignal.com/)
- [Guía de Web Push de OneSignal](https://documentation.onesignal.com/docs/web-push-quickstart)

---

## ✅ Lista de Verificación Final

Antes de considerar la implementación completa, verifica:

- [ ] OneSignal App creada en el dashboard
- [ ] App ID y REST API Key configurados en `onesignal-config.php`
- [ ] Plugin activo en WordPress
- [ ] `OneSignalSDKWorker.js` existe en la raíz de WordPress
- [ ] Permisos de notificaciones habilitados en el navegador
- [ ] Tags configurados correctamente (verificar en OneSignal Dashboard)
- [ ] Notificaciones de prueba funcionando
- [ ] HTTPS activo (para producción)
- [ ] Credenciales NO están en repositorios públicos

---

## 🎉 ¡Listo!

Si has completado todos los pasos y verificaciones, tu sistema de notificaciones push con OneSignal está funcionando correctamente.

Para soporte adicional o problemas, revisa los logs de WordPress (`error_log`) y la consola del navegador (F12).

---

**Última actualización**: Enero 2025  
**Versión del plugin**: 2.0  
**Versión de OneSignal SDK**: v16
