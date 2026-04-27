# App — Notifications Module

Bu sənəd **App / Site** (istifadəçi tərəfi) üçün bildiriş və device token endpointlərini izah edir.

**Base URL:** `{{url}}/:provider/`  
**Auth:** Bearer token → `{{login_token_site}}`  
**Header:** `Token: {{app_site_token}}`  
**`:provider`** = `app` (mobil) və ya `site` (veb)

---

## Ümumi anlayışlar

### İstifadəçiyə nə göndərilir?

İstifadəçi iki mənbədən bildiriş alır:

| Mənbə | Nə vaxt |
|-------|---------|
| **Avtomatik (system)** | Sifariş verildi, status dəyişdi, ləğv edildi, çatdırıldı, məhsul ləğv edildi |
| **Manual (admin / satıcı)** | Admin və ya satıcının yaratdığı kampaniya bildirişləri |

### Bildiriş kanalları

| Kanal | İzah |
|-------|------|
| `in_app` | App daxilindəki bildiriş mərkəzinə düşür — bu endpointlər vasitəsilə oxunur |
| `push` | FCM push notification — app bağlı olsa da gəlir |
| `sms` | SMS |
| `email` | E-poçt |

> Bu sənəddəki bütün endpointlər yalnız `in_app` kanalına aiddir. Push/SMS/Email kanal kimi seçilir, ancaq backend göndərir — frontend tərəfindən ayrıca çağırış tələb olunmur.

### Avtomatik bildiriş hadisələri

| Hadisə | Nə vaxt göndərilir |
|--------|-------------------|
| `order_placed` | İstifadəçi sifariş verəndə |
| `order_status_changed` | Sifarişin ümumi statusu dəyişəndə |
| `order_cancelled` | Sifariş tam ləğv ediləndə |
| `order_delivered` | Sifariş çatdırılanda |
| `order_item_cancelled` | Sifarişdəki bir məhsul ləğv edilib geri ödəmə başlananda |

> Bu hadisələrin aktiv/deaktiv vəziyyəti admin tərəfindən idarə olunur. Deaktiv edilibsə bildiriş göndərilmir.

### Action types — bildirişə tap deyin

Bildirişdə `action_type` və `action_id` sahələri var. İstifadəçi bildirişə toxunanda bu dəyərlərə görə yönləndirilməlidir:

| `action_type` | `action_id` | Nə etməli |
|---------------|-------------|-----------|
| `none` | — | Heç bir yönləndirmə |
| `order` | Order UUID | Sifariş detay ekranını aç |
| `product` | Product variant slug | Məhsul ekranını aç |
| `category` | Category slug | Kateqoriya ekranını aç |
| `url` | URL string | Brauzer/webview ilə aç |

---

## Bildiriş endpointləri

### 1. Bildirişlər siyahısı

```
GET /:provider/notifications/load
```

İstifadəçinin `in_app` kanalı ilə aldığı, hələ silinməmiş bildirişlər.

**Response:**

```json
{
  "data": [
    {
      "id": 5,
      "uuid": "550e8400-...",
      "title": "Sifarişiniz verildi",
      "body": "Sifarişiniz #CF-1042 uğurla qəbul edildi. Təsdiqlənəcəyi zaman sizə bildiriş göndərəcəyik.",
      "image": null,
      "action_type": "order",
      "action_id": "order-uuid-here",
      "is_read": false,
      "date": "27.04.2026 14:30",
      "date_raw": "2026-04-27T14:30:00.000000Z"
    },
    {
      "id": 3,
      "uuid": "661f9511-...",
      "title": "Endirim başladı!",
      "body": "Bu həftə seçilmiş məhsullarda 20% endirim.",
      "image": "https://cdn.example.com/sale.jpg",
      "action_type": "category",
      "action_id": "elektronika",
      "is_read": true,
      "date": "26.04.2026 09:15",
      "date_raw": "2026-04-26T09:15:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 30,
    "total": 72
  }
}
```

**Sahələr:**

| Sahə | Tip | İzah |
|------|-----|------|
| `uuid` | string | Bildirişin unikal ID-si (əməliyyatlarda istifadə et) |
| `title` | string | Bildiriş başlığı (istifadəçi dilinə uyğun) |
| `body` | string | Bildiriş mətni |
| `image` | string\|null | Bildiriş şəkli URL-i (varsa göstər) |
| `action_type` | string | Tap davranışı (`none`, `order`, `product`, `category`, `url`) |
| `action_id` | string\|null | Yönləndirmə üçün ID/slug/URL |
| `is_read` | bool | Oxunub-oxunmadığı |
| `date` | string | Göstərmək üçün format (`27.04.2026 14:30`) |
| `date_raw` | string | Sıralama/hesablama üçün ISO format |

> Siyahı `created_at DESC` sıralanır — ən yeni üstdə.  
> Silinmiş bildirişlər (`in_app_deleted_at` dolu) gəlmir.  
> Bildiriş məzmunu avtomatik istifadəçi dilinə uyğunlaşdırılır.

---

### 2. Oxunmamış say (badge)

```
GET /:provider/notifications/unread-count
```

App ikonundakı badge rəqəmi üçün. Sessiyanın açılışında və bildiriş mərkəzi bağlananda çağır.

**Response:**

```json
{
  "count": 5
}
```

> `count: 0` — badge göstərmə.

---

### 3. Bildirişi oxunmuş işarələ

```
POST /:provider/notifications/:notification/read
```

`:notification` — bildirişin UUID-si.

İstifadəçi bildirişi açanda çağır. `is_read: true` olur, badge sayı azalır.

**Response:**

```json
{
  "message": "Oxunmuş kimi işarələndi."
}
```

**Xəta:** Başqa istifadəçinin bildirişi üçün `403`.

---

### 4. Hamısını oxunmuş işarələ

```
POST /:provider/notifications/read-all
```

"Hamısını oxu" düyməsi üçün. Bütün oxunmamış bildirişlər bir anda oxunmuş olur.

**Response:**

```json
{
  "message": "Bütün bildirişlər oxunmuş kimi işarələndi."
}
```

---

### 5. Bildirişi sil

```
DELETE /:provider/notifications/:notification/delete
```

`:notification` — bildirişin UUID-si.

İstifadəçinin görüş siyahısından silir (soft delete). Bildirişin özü silinmir.

**Response:**

```json
{
  "message": "Bildiriş silindi."
}
```

**Xəta:** Başqa istifadəçinin bildirişi üçün `403`.

---

### 6. Hamısını sil

```
DELETE /:provider/notifications/delete-all
```

İstifadəçinin bütün in-app bildirişlərini siyahıdan silir.

**Response:**

```json
{
  "message": "Bütün bildirişlər silindi."
}
```

---

## Device Token endpointləri

Push notification almaq üçün FCM (Firebase Cloud Messaging) token-i backend-ə qeyd etmək lazımdır.

### 7. Token qeydiyyatı

```
POST /:provider/device-token/register
```

**Nə vaxt çağır:**
- İstifadəçi giriş edəndə
- FCM token yeniləndikdə (Firebase `onTokenRefresh` callback-i)
- App yenidən işə düşəndə (token hələ mövcuddursa `updateOrCreate` yazar, ziyanı yoxdur)

**Request body:**

```json
{
  "token": "fcm-device-token-buraya",
  "device_type": "android"
}
```

| Sahə | Dəyərlər | Qeyd |
|------|---------|------|
| `token` | string | FCM/APNs token |
| `device_type` | `ios`, `android`, `web` | Platforma |

**Response:**

```json
{
  "message": "Cihaz qeydiyyatdan keçdi."
}
```

---

### 8. Token ləğvi

```
DELETE /:provider/device-token/unregister
```

**Nə vaxt çağır:**
- İstifadəçi çıxış edəndə (logout)
- İstifadəçi push bildirişlərini söndürəndə

**Request body:**

```json
{
  "token": "fcm-device-token-buraya"
}
```

**Response:**

```json
{
  "message": "Cihaz qeydiyyatdan silindi."
}
```

---

## Frontend tərəfindən tam iş axını

### App açılışı

```
1. İstifadəçi giriş etdi:
   POST /:provider/device-token/register  ← FCM token qeyd et

2. Bildiriş mərkəzini yüklə:
   GET /:provider/notifications/unread-count  ← badge

3. İstifadəçi bildiriş mərkəzini açdı:
   GET /:provider/notifications/load  ← siyahı göstər
   POST /:provider/notifications/read-all  (istəyə bağlı, "hamısını oxu" düyməsi)
```

### Bildiriş siyahısında UX qaydaları

```
- is_read: false → fərqli rəng / bold / göy nöqtə ilə göstər
- image varsa → kiçik thumbnail göstər
- date → nisbi vaxt ("2 saat əvvəl") və ya tam format
- action_type != "none" → tap-a bağlı yönləndirmə əlavə et
```

### İstifadəçi bildirişə toxundu

```
1. POST /:provider/notifications/:uuid/read  ← oxunmuş işarələ
2. action_type-a görə yönləndir:
   - order    → Sifarişlər ekranı → Sifariş detayı (action_id = order UUID)
   - product  → Məhsul ekranı (action_id = slug)
   - category → Kateqoriya ekranı (action_id = slug)
   - url      → Webview / Brauzer
   - none     → Heç bir yönləndirmə
```

### Push notification (FCM) gəldi — app bağlı ikən

```
Sistem FCM vasitəsilə cihaza push göndərir.
Frontend FCM payload-ı alır.

Tövsiyə olunan payload strukturu:
{
  "notification": {
    "title": "Sifarişiniz verildi",
    "body": "Sifarişiniz #CF-1042 qəbul edildi."
  },
  "data": {
    "uuid": "bildiriş-uuid",
    "action_type": "order",
    "action_id": "order-uuid"
  }
}

Tap olunanda:
→ POST /:provider/notifications/:uuid/read
→ action_type-a görə yönləndir
```

### İstifadəçi çıxış etdi

```
1. DELETE /:provider/device-token/unregister  ← push dayandır
2. Logout endpoint-i çağır
```

### Badge sayını yeniləmə strategiyası

```
- App açılışında: GET /unread-count
- Bildiriş mərkəzi bağlananda: GET /unread-count
- Hər oxuma (read / read-all) əməliyyatından sonra: GET /unread-count
- WebSocket / real-time varsa: server event-ə subscribe ol
```

---

## Qeydlər

- Siyahı `per_page: 30` ilə paginasiya olunur — sonsuz scroll üçün `?page=2` əlavə et
- Bildirişlər istifadəçinin dil seçiminə uyğun gəlir — dil başlığını request-ə əlavə et (`Accept-Language` və ya mövcud dil mexanizmi)
- `in_app_deleted_at` dolu olan bildirişlər siyahıya daxil edilmir — silmə soft-delete-dir
- Badge sayı `in_app` kanalı olan `sent` statuslu bildirişlərə əsaslanır
