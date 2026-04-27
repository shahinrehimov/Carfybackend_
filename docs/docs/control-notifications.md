# Control Panel — Notifications Module

Bu sənəd **Control panel** (Carfy admin) üçün bildiriş endpointlərini və tam iş axınını izah edir.

**Base URL:** `{{url}}/control/`  
**Auth:** Bearer token → `{{token}}`  
**Header:** `Token: {{app_token}}`

---

## Ümumi anlayışlar

### Bildiriş növləri (source)

| Dəyər | Mənası |
|-------|--------|
| `system` | Avtomatik sistem bildirişi (sifariş verildi, çatdırıldı və s.) |
| `admin` | Admin tərəfindən əl ilə yaradıldı |
| `seller` | Satıcı tərəfindən yaradıldı, admin təsdiqi gözləyir |

### Statuslar

| Status | Mənası |
|--------|--------|
| `draft` | Qaralama — hələ göndərilməyib |
| `pending_approval` | Satıcı yaratdı, admin təsdiqi gözləyir |
| `approved` | Admin təsdiq etdi, göndərilməyə hazırdır |
| `rejected` | Admin rədd etdi |
| `sending` | Göndərilmə prosesindədir |
| `sent` | Göndərildi |
| `cancelled` | Göndərilmə zamanı xəta baş verdi |

### Status keçid sxemi

```
Admin yaradır:
  DRAFT → (dərhal send çağırılır) → SENDING → SENT

Admin yaradır (scheduled):
  DRAFT → APPROVED → (vaxt gəlincə) → SENDING → SENT

Satıcı yaradır:
  PENDING_APPROVAL → (admin approve) → SENDING → SENT
  PENDING_APPROVAL → (admin reject) → REJECTED

Yenidən göndərmə (resend):
  SENT/CANCELLED → yeni bildiriş yaranır → SENDING → SENT
```

### Kanallar

| Kanal | Dəyər | İzah |
|-------|-------|------|
| In-app | `in_app` | App daxilindəki bildiriş mərkəzi |
| Push | `push` | FCM push notification |
| SMS | `sms` | SMS göndərmə |
| Email | `email` | E-poçt |

### Hədəf növləri (target_type)

| Dəyər | İzah |
|-------|------|
| `bulk` | Toplu — `target_filters`-ə uyğun istifadəçilər |
| `individual` | Fərdi — `target_filters.user_ids` ilə seçilmiş istifadəçilər |

### Bulk hədəf filterləri (target_filters)

`target_type: bulk` olduqda `target_filters` içindəki açar hansı targetinq strategiyasının işlənəcəyini müəyyən edir:

| target_filters açarı | Strategiya | Nümunə |
|----------------------|------------|--------|
| _(heç biri yoxdur)_ | Bütün istifadəçilər | `{}` və ya `null` |
| `company_id` | Həmin satıcının alıcıları | `{"company_id": 5}` |
| `recent_buyers_days` | Son N gündə sifariş verənlər | `{"recent_buyers_days": 30}` |
| `cart_abandoners_days` | Səbəti tərk edənlər (N gün) | `{"cart_abandoners_days": 7}` |
| `inactive_days` | Son N gündə aktiv olmayanlar | `{"inactive_days": 90}` |

`target_type: individual` olduqda:

```json
{
  "target_filters": {
    "user_ids": [1, 2, 3]
  }
}
```

### Action types (bildirişə tap deyin)

| Dəyər | İzah |
|-------|------|
| `none` | Heç bir yönləndirmə yoxdur |
| `order` | Sifarişi aç — `action_id` = order UUID |
| `product` | Məhsulu aç — `action_id` = product variant slug |
| `category` | Kateqoriyanı aç — `action_id` = category slug |
| `url` | Xarici/daxili URL — `action_id` = URL |

---

## Endpointlər

### 1. Bildirişlər siyahısı

```
GET /control/notifications/load
```

**Query parametrləri (hamısı optional):**

| Parametr | Tip | Nümunə |
|----------|-----|--------|
| `source` | string | `manual`, `system`, `seller` |
| `status` | string | `draft`, `sent`, `pending_approval` |
| `date_from` | date | `2026-01-01` |
| `date_to` | date | `2026-04-30` |

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Sifarişiniz verildi",
      "source": "system",
      "status": "sent",
      "target_type": "individual",
      "channels": ["in_app", "push"],
      "total_recipients": 1,
      "scheduled_at": null,
      "sent_at": "27.04.2026 14:30",
      "created_at": "27.04.2026 14:29",
      "sender": null
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 98 }
}
```

> `sender` — admin bildirişlərində `null`, satıcı bildirişlərində `{type, name}` gəlir.

---

### 2. Təsdiq gözləyən bildirişlər

```
GET /control/notifications/pending
```

Yalnız `status: pending_approval` olan bildirişlər. Cavab formatı `load` ilə eynidir.

---

### 3. Bildiriş detayı

```
GET /control/notifications/show/:notification
```

`:notification` — bildirişin UUID-si.

**Response:**

```json
{
  "data": {
    "id": 1,
    "uuid": "550e8400-...",
    "source": "seller",
    "event_type": null,
    "status": "pending_approval",
    "target_type": "bulk",
    "target_filters": { "company_id": 3 },
    "channels": ["in_app", "push"],
    "action_type": "order",
    "action_id": "order-uuid-here",
    "image": "https://cdn.example.com/img.jpg",
    "total_recipients": 0,
    "rejection_reason": null,
    "approved_at": null,
    "scheduled_at": null,
    "sent_at": null,
    "created_at": "27.04.2026 12:00",
    "translations": [
      {
        "language_id": 1,
        "title": "Yeni kampaniya",
        "body": "Endirimlərdən yararlanın!"
      }
    ],
    "sender": {
      "type": "App\\Models\\CompanyAdmin",
      "name": "Əli Həsənov"
    },
    "approved_by": null
  }
}
```

---

### 4. Bildiriş yarat

```
POST /control/notifications/add
```

Admin yaratdıqda bildiriş dərhal `approved` olur və (əgər `scheduled_at` yoxdursa) dərhal göndərilir.

**Request body:**

```json
{
  "target_type": "bulk",
  "target_filters": {
    "recent_buyers_days": 30
  },
  "channels": ["in_app", "push"],
  "action_type": "none",
  "action_id": null,
  "image": null,
  "scheduled_at": null,
  "translations": [
    {
      "language_id": 1,
      "title": "Başlıq AZ",
      "body": "Mətn AZ"
    },
    {
      "language_id": 2,
      "title": "Title EN",
      "body": "Body EN"
    },
    {
      "language_id": 3,
      "title": "Заголовок RU",
      "body": "Текст RU"
    }
  ]
}
```

**Qeydlər:**
- `translations` — bütün dillər üçün göndərilməlidir (`language_id`: 1=AZ, 2=EN, 3=RU)
- `scheduled_at` göndərilsə — bildiriş `approved` statusunda saxlanır, həmin vaxt göndərilir
- `scheduled_at` boş olarsa — yarandıqdan sonra dərhal göndərilir

**Response:**

```json
{
  "message": "Bildiriş yaradıldı.",
  "data": {
    "uuid": "550e8400-..."
  }
}
```

---

### 5. Bildiriş yenilə

```
POST /control/notifications/update/:notification
```

Yalnız `draft`, `pending_approval`, `rejected` statuslu bildirişlər dəyişdirilə bilər. Göndərilmiş bildiriş dəyişdirilə bilməz.

**Request body (hamısı optional, yalnız göndərilən sahələr dəyişdirilir):**

```json
{
  "channels": ["in_app"],
  "target_type": "individual",
  "target_filters": {
    "user_ids": [1, 5, 12]
  },
  "action_type": "product",
  "action_id": "product-slug",
  "image": "https://cdn.example.com/new.jpg",
  "scheduled_at": "2026-05-01 10:00:00",
  "translations": [
    {
      "language_id": 1,
      "title": "Yenilənmiş başlıq",
      "body": "Yenilənmiş mətn"
    }
  ]
}
```

**Response:**

```json
{
  "message": "Bildiriş yeniləndi."
}
```

---

### 6. Bildiriş sil

```
DELETE /control/notifications/delete/:notification
```

Yalnız `draft` və `pending_approval` statuslu bildirişlər silinə bilər.

**Response:**

```json
{
  "message": "Bildiriş silindi."
}
```

---

### 7. Bildiriş göndər

```
POST /control/notifications/send/:notification
```

`approved` və ya `draft` statuslu bildirişi dərhal göndərir. `scheduled_at` olsa belə, bu endpoint çağırılanda dərhal göndərilir.

**Response:**

```json
{
  "message": "Bildiriş göndərilir."
}
```

---

### 8. Bildiriş planlaşdır

```
POST /control/notifications/schedule/:notification
```

`approved` və ya `draft` statuslu bildirişi gələcəkdə göndərilmək üçün planlaşdırır.

**Request body:**

```json
{
  "scheduled_at": "2026-05-01 10:00:00"
}
```

**Response:**

```json
{
  "message": "Bildiriş planlaşdırıldı."
}
```

---

### 9. Yenidən göndər (resend)

```
POST /control/notifications/resend/:notification
```

Yalnız `sent` və ya `cancelled` statuslu bildirişlər yenidən göndərilə bilər. Mövcud bildiriş dəyişdirilmir — yeni bir klon yaranır və dərhal göndərilir.

**Response:**

```json
{
  "message": "Bildiriş yenidən göndərilir.",
  "data": {
    "uuid": "yeni-klon-uuid"
  }
}
```

---

### 10. Satıcı bildirişini təsdiqlə

```
POST /control/notifications/approve/:notification
```

Yalnız `pending_approval` statuslu bildirişlər təsdiqlənə bilər. Təsdiqləndikdən sonra (əgər `scheduled_at` yoxdursa) dərhal göndərilir.

**Response:**

```json
{
  "message": "Bildiriş təsdiqləndi və göndərilir."
}
```

---

### 11. Satıcı bildirişini rədd et

```
POST /control/notifications/reject/:notification
```

Yalnız `pending_approval` statuslu bildirişlər rədd edilə bilər. Satıcı rədd səbəbini görə bilir.

**Request body:**

```json
{
  "rejection_reason": "Kampaniya şərtlərini pozur."
}
```

**Response:**

```json
{
  "message": "Bildiriş rədd edildi."
}
```

---

### 12. Hədəf sayı önizləmə

```
POST /control/notifications/targeting-preview
```

Müəyyən targetinq parametrləri üçün neçə istifadəçiyə çatacağını göstərir. Bildiriş yaratmadan istifadə olunur.

**Request body:**

```json
{
  "target_type": "bulk",
  "target_filters": {
    "recent_buyers_days": 7
  }
}
```

**Response:**

```json
{
  "count": 243
}
```

---

## Bildiriş ayarları

### 13. Ayarları siyahıla

```
GET /control/notification-settings/load
```

Sistem tərəfindən avtomatik göndərilən bildiriş növlərini (event type) və onların kanallarını göstərir.

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "event_type": "order_placed",
      "is_active": true,
      "channels": ["in_app", "push"]
    },
    {
      "id": 2,
      "event_type": "order_status_changed",
      "is_active": true,
      "channels": ["in_app", "push"]
    },
    {
      "id": 3,
      "event_type": "order_cancelled",
      "is_active": true,
      "channels": ["in_app", "push"]
    },
    {
      "id": 4,
      "event_type": "order_delivered",
      "is_active": true,
      "channels": ["in_app", "push"]
    },
    {
      "id": 5,
      "event_type": "order_item_cancelled",
      "is_active": true,
      "channels": ["in_app", "push"]
    }
  ]
}
```

**Mövcud event növləri:**

| event_type | Nə vaxt göndərilir |
|------------|-------------------|
| `order_placed` | İstifadəçi sifariş verəndə |
| `order_status_changed` | Sifarişin statusu dəyişəndə |
| `order_cancelled` | Sifariş ləğv ediləndə |
| `order_delivered` | Sifariş çatdırılanda |
| `order_item_cancelled` | Sifarişin içindəki bir məhsul ləğv ediləndə |

---

### 14. Ayarları yenilə

```
POST /control/notification-settings/update
```

Bir və ya bir neçə event növünü eyni anda yeniləyir.

**Request body:**

```json
{
  "settings": [
    {
      "id": 1,
      "is_active": true,
      "channels": ["in_app", "push"]
    },
    {
      "id": 2,
      "is_active": false,
      "channels": ["in_app"]
    }
  ]
}
```

**Response:**

```json
{
  "message": "Bildiriş ayarları yeniləndi."
}
```

---

### 15. Event növlərini sinxronlaşdır

```
POST /control/notification-settings/sync
```

Kodda yeni event növü əlavə edildikdə bunu verilənlər bazasına əlavə edir. Artıq mövcud olanları dəyişdirmir.

**Response:**

```json
{
  "message": "2 yeni event növü sinxronlaşdırıldı.",
  "added": 2
}
```

---

## Tam iş axını — Admin nöqteyi-nəzərindən

### Dərhal göndərmə

```
1. POST /notifications/add  (scheduled_at: null)
   → Bildiriş yaranır, dərhal göndərilir
   → status: sent

2. (İstəsə) POST /notifications/resend/:uuid
   → Yeni klon yaranır, yenidən göndərilir
```

### Planlaşdırılmış göndərmə

```
1. POST /notifications/add  (scheduled_at: "2026-05-01 10:00:00")
   → Bildiriş yaranır, status: approved
   → Sistem həmin vaxt avtomatik göndərir

2. (İstəsə dərhal göndərmək üçün) POST /notifications/send/:uuid
```

### Satıcı bildirişini idarə etmə

```
1. GET /notifications/pending
   → Yeni satıcı bildirişlərini gör

2. GET /notifications/show/:uuid
   → Məzmunu oxu, kimdən olduğunu gör

3a. POST /notifications/approve/:uuid
    → Bildiriş dərhal göndərilir

3b. POST /notifications/reject/:uuid  {rejection_reason: "..."}
    → Satıcı rədd səbəbini görür, düzəldib yenidən göndərə bilər
```

---

## Xəta cavabları

| HTTP kod | Hal |
|----------|-----|
| `422` | Redaktə edilə bilməz (status `sent`, `sending` və s.) |
| `422` | Göndərilə bilməz (status uyğun deyil) |
| `422` | Yalnız `pending_approval` olanlar təsdiqlənə/rədd edilə bilər |
| `422` | Yalnız `sent`/`cancelled` olanlar yenidən göndərilə bilər |
| `422` | Yalnız `draft`/`pending_approval` olanlar silinə bilər |
