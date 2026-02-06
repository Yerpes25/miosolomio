# Guía de Implementación: OneSignal con External User ID

Esta guía documenta dónde está cada función relacionada con la segmentación de notificaciones usando External User ID en OneSignal.

## 🎯 Objetivo

Usar External User ID para enviar notificaciones específicas a cada trabajador, evitando que las notificaciones se envíen a todos los suscritos.

---

## 📍 PASO 1: Cliente JavaScript - Vincular External User ID

### Ubicación: `assets/js/notifications/notifications.js`

### Función: `initOneSignal()`
**Líneas:** ~56-98 (aproximadamente)

**Código clave:**
```javascript
// PASO 1: Vincular External User ID con OneSignal.login()
try {
    const externalUserId = 'trabajador_' + config.userId;
    await OneSignal.login(externalUserId);
    console.log('[CHE Notifications] External User ID vinculado:', externalUserId);
} catch (loginError) {
    console.warn('[CHE Notifications] Error al vincular External User ID:', loginError);
}
```

**También en:** `initOneSignalDirect()`
**Líneas:** ~187-210 (aproximadamente)

**¿Qué hace?**
- Vincula el dispositivo del navegador con el External User ID del trabajador
- Formato: `'trabajador_{user_id}'` (ejemplo: `'trabajador_5'`)
- Esto permite enviar notificaciones específicas desde PHP usando `include_external_user_ids`

---

## 📍 PASO 2: Servidor PHP - Enviar notificaciones usando External User ID

### Ubicación: `includes/Services/OneSignalService.php`

### Función 1: `send_notification()`
**Líneas:** ~46-132

**Cambio clave:**
```php
// ANTES (usaba filters):
'filters' => $filters,

// AHORA (usa External User IDs):
'include_external_user_ids' => $external_user_ids,
```

### Función 2: `convert_interests_to_external_user_ids()`
**Líneas:** ~135-154 (aproximadamente)

**¿Qué hace?**
- Convierte intereses (ej: `['user-5', 'admin-notifications']`) a External User IDs
- Formato: `['trabajador_5', 'trabajador_3']`
- Usa `get_users_from_interests()` para obtener los User IDs de WordPress

**Código:**
```php
private function convert_interests_to_external_user_ids($interests)
{
    $user_ids = $this->get_users_from_interests($interests);
    
    if (empty($user_ids)) {
        return [];
    }
    
    // Convertir User IDs a External User IDs (formato: 'trabajador_{user_id}')
    $external_user_ids = array_map(function($user_id) {
        return 'trabajador_' . $user_id;
    }, $user_ids);
    
    return array_unique($external_user_ids);
}
```

### Función 3: `send_to_user()`
**Líneas:** ~398-402

**¿Qué hace?**
- Envía notificación a un usuario específico
- Ejemplo: cuando el admin aprueba vacaciones de un trabajador

**Código:**
```php
public function send_to_user($user_id, $title, $body, $data = [], $deep_link = null)
{
    $interests = ['user-' . $user_id];
    return $this->send_notification($interests, $title, $body, $data, $deep_link);
}
```

### Función 4: `send_to_admins()`
**Líneas:** ~412-416

**¿Qué hace?**
- Envía notificación a todos los administradores
- Ejemplo: cuando un trabajador solicita vacaciones

**Código:**
```php
public function send_to_admins($title, $body, $data = [], $deep_link = null)
{
    $interests = ['admin-notifications'];
    return $this->send_notification($interests, $title, $body, $data, $deep_link);
}
```

### Función 5: `get_users_from_interests()`
**Líneas:** ~292-387 (aproximadamente)

**¿Qué hace?**
- Convierte intereses (ej: `'user-5'`, `'admin-notifications'`) a User IDs de WordPress
- Esta función es usada por `convert_interests_to_external_user_ids()`

**Ejemplos de intereses:**
- `'user-5'` → Devuelve: `[5]`
- `'admin-notifications'` → Devuelve: `[1, 3]` (IDs de todos los admins)
- `'worker-notifications'` → Devuelve: `[2, 4, 6]` (IDs de todos los workers)

---

## 📍 PASO 3: Ocultar Widget Nativo de OneSignal

### Ubicación 1: `admin/Init.php`
**Líneas:** ~254-290 (aproximadamente)

**Código en `OneSignal.init()`:**
```javascript
notifyButton: {
    enable: false // Deshabilitar botón de notificaciones nativo
}
```

**CSS adicional:**
```javascript
const hideOneSignalWidget = () => {
    const style = document.createElement('style');
    style.textContent = `
        #onesignal-bell-container,
        #onesignal-slidedown-container,
        .onesignal-bell-container {
            display: none !important;
            visibility: hidden !important;
        }
    `;
    document.head.appendChild(style);
};
```

**¿Qué hace?**
- Oculta completamente el widget nativo de OneSignal (la campanita pequeña)
- Se ejecuta en `wp_head` durante la inicialización de OneSignal

---

## 📍 PASO 4: Funciones de Notificación Específicas

### Ubicación: `includes/Services/OneSignalService.php`

### Función: `notify_vacation_request()`
**Líneas:** ~444-469

**¿Qué hace?**
- Envía notificación cuando un trabajador solicita vacaciones
- **Destinatarios:** Solo admins (excluye al trabajador que solicita)

**Usa:**
```php
$this->send_notification(['vacaciones-solicitudes'], $title, $body, $data, $deep_link);
```

### Función: `notify_vacation_approved()`
**Líneas:** ~479-508

**¿Qué hace?**
- Envía notificación cuando el admin aprueba vacaciones
- **Destinatario:** Solo el trabajador específico

**Usa:**
```php
return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
```

### Función: `notify_parte_validated()`
**Líneas:** ~593-613

**¿Qué hace?**
- Envía notificación cuando el admin valida un parte
- **Destinatario:** Solo el trabajador específico

**Usa:**
```php
return $this->send_to_user($worker_id, $title, $body, $data, $deep_link);
```

---

## 📍 Llamadas desde Controladores

### Ubicación: `includes/Api/VacacionesController.php`

### Función: `solicitar_vacaciones()`
**Líneas:** ~142-144

**Código:**
```php
$onesignal = OneSignalService::get_instance();
$onesignal->notify_vacation_request($post_id, $user_id, $fecha_inicio, $fecha_fin);
```

### Función: `aprobar_vacacion()`
**Líneas:** ~286-287

**Código:**
```php
$onesignal = OneSignalService::get_instance();
$onesignal->notify_vacation_approved($id, $worker_id, $fecha_inicio, $fecha_fin);
```

### Ubicación: `includes/Api/TimesheetController.php`

### Función: `validar_parte()`
**Líneas:** ~452

**Código:**
```php
OneSignalService::get_instance()->notify_parte_validated($parte_id, $trabajador_id, $fecha);
```

---

## 🔄 Flujo Completo de una Notificación

### Ejemplo: Trabajador solicita vacaciones → Admin recibe notificación

1. **Cliente (JS):** `notifications.js` → `OneSignal.login('trabajador_5')`
   - Vincula dispositivo con External User ID

2. **API:** `VacacionesController.php` → `solicitar_vacaciones()`
   - Llama a `OneSignalService::notify_vacation_request()`

3. **Servicio:** `OneSignalService.php` → `notify_vacation_request()`
   - Crea título y mensaje
   - Llama a `send_notification(['vacaciones-solicitudes'], ...)`

4. **Servicio:** `OneSignalService.php` → `send_notification()`
   - Llama a `convert_interests_to_external_user_ids()`
   - Obtiene: `['trabajador_1', 'trabajador_3']` (IDs de admins)
   - Envía a OneSignal API con `include_external_user_ids`

5. **OneSignal:** Envía notificación push solo a los dispositivos vinculados con esos External User IDs

---

## ✅ Ventajas de usar External User ID

1. **Segmentación precisa:** Solo reciben notificaciones los destinatarios correctos
2. **Sin duplicados:** No se envían a todos los suscritos
3. **Escalable:** Funciona con miles de usuarios
4. **Fácil debug:** El External User ID es visible y fácil de rastrear

---

## 🐛 Debugging

### Verificar External User ID vinculado (Consola del navegador):
```javascript
OneSignal.User.externalId
// Debe mostrar: "trabajador_5" (ejemplo)
```

### Ver logs en PHP (error_log):
```
[CHE OneSignal] Enviando notificación a External User IDs: trabajador_1, trabajador_3
```

### Verificar notificaciones en OneSignal Dashboard:
- Ir a: Audience → All Users
- Buscar por External User ID: `trabajador_5`
- Verificar que el usuario esté vinculado

---

## 📝 Notas Importantes

1. **Formato del External User ID:** Siempre `'trabajador_{user_id}'`
2. **El External User ID debe ser único:** OneSignal lo usa como identificador
3. **Se vincula automáticamente:** Al iniciar sesión, se ejecuta `OneSignal.login()`
4. **Exclusión de usuario:** Usar `exclude_user_id` en `$data` para excluir al originador

---

## 📂 Archivos Modificados

1. `assets/js/notifications/notifications.js` - Paso 1 (Cliente)
2. `includes/Services/OneSignalService.php` - Paso 2 (Servidor)
3. `admin/Init.php` - Paso 3 (Ocultar widget)
4. `includes/Api/VacacionesController.php` - Llamadas a notificaciones
5. `includes/Api/TimesheetController.php` - Llamadas a notificaciones
