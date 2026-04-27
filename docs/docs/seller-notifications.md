# Seller Panel — Notifications Module

Bu sənəd **Seller panel** (satıcı) üçün bildiriş endpointlərini və tam iş axınını izah edir.

**Base URL:** `{{url}}/sellers/`  
**Auth:** Bearer token → `{{login_token_seller}}`  
**Header:** `Token: {{app_seller_token}}`

---

## Ümumi anlayışlar

### Satıcı bildirişi necə işləyir?

Satıcı öz müştərilərinə bildiriş göndərmək istəyəndə:

1. Bildiriş yaradır → status avtomatik `pending_approval` olur
2. Admin panel-də `pending` siyahısına düşür
3. Admin təsdiqləyir → bildiriş göndərilir (`sent`)
4. Admin rədd edirsə → satıcı rədd səbəbini görür, düzəldə bilər

```
Satıcı yaradır         Admin idarə edir        Alıcı alır
─────────────          ────────────────         ──────────
POST /add         →    pending_approval    →    approve → SENT
                       pending_approval    →    reject  → REJECTED
                                                          ↓
Satıcı düzəldir:                          update + resend → pending_approval
```

### Hədəflər (targeting)

Satıcı yalnız **öz müştərilərini** hədəfləyə bilər:

| target_type | Nə baş verir |
|-------------|-------------|
| `bulk` | Satıcının **bütün** müştəriləri hədəflənir — `target_filters` server tərəfindən avtomatik məhdudlaşdırılır |
| `individual` | `target_filters.user_ids` ilə göstərilən xüsusi istifadəçilər |

> **Qeyd:** Satıcı `bulk` seçəndə `target_filters`-i özü göndərmir — server həmin satıcının `company_id`-si ilə avtomatik filter qoyur. Yəni satıcı başqa satıcının müştərilərini hədəfləyə bilməz.

### Statuslar

| Status | Mənası |
|--------|--------|
| `pending_approval` | Admin təsdiqi gözlənilir |
| `approved` | Admin təsdiqlədi (göndərilmə başlayır) |
| `rejected` | Admin rədd etdi — `rejection_reason` dolu olur |
| `sending` | Göndərilmə prosesindədir |
| `sent` | Göndərildi |
| `cancelled` | Göndərilmə zamanı xəta |

### Redaktə qaydası

| Status | Redaktə | Silmə | Yenidən göndər |
|--------|---------|-------|----------------|
| `pending_approval` | ✅ | ✅ | ❌ |
| `rejected` | ✅ | ✅ | ❌ |
| `sent` | ❌ | ❌ | ✅ |
| `cancelled` | ❌ | ❌ | ✅ |
| `approved` / `sending` | ❌ | ❌ | ❌ |

### Kanallar

| Kanal | Dəyər |
|-------|-------|
| In-app | `in_app` |
| Push | `push` |
| SMS | `sms` |
| Email | `email` |

### Action types

Bildirişə toxunulduqda app hansı ekranı açacaq:

| Dəyər | İzah |
|-------|------|
| `none` | Heç bir yönləndirmə |
| `order` | Sifariş ekranı — `action_id` = order UUID |
| `product` | Məhsul ekranı — `action_id` = product variant slug |
| `category` | Kateqoriya ekranı — `action_id` = category slug |
| `url` | URL aç — `action_id` = link |

---

## Endpointlər

### 1. Bildirişlər siyahısı

```
GET /sellers/notifications/load
```

Yalnız bu satıcının yaratdığı bildirişlər gəlir.

**Response:**

```json
{
  "data": [
    {
      "id": 12,
      "uuid": "550e8400-...",
      "title": "Yeni kampaniya",
      "source": "seller",
      "status": "pending_approval",
      "target_type": "bulk",
      "channels": ["in_app", "push"],
      "total_recipients": 0,
      "scheduled_at": null,
      "sent_at": null,
      "created_at": "27.04.2026 10:00",
      "sender": {
        "type": "App\\Models\\CompanyAdmin",
        "name": "Əli Həsənov"
      }
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "total": 30 }
}
```

---

### 2. Bildiriş detayı

```
GET /sellers/notifications/show/:notification
```

`:notification` — bildirişin UUID-si. Başqa satıcının bildirişinə girmə cəhdi `403` verir.

**Response:**

```json
{
  "data": {
    "id": 12,
    "uuid": "550e8400-...",
    "source": "seller",
    "event_type": null,
    "status": "rejected",
    "target_type": "bulk",
    "target_filters": { "company_id": 3 },
    "channels": ["in_app", "push"],
    "action_type": "none",
    "action_id": null,
    "image": null,
    "total_recipients": 0,
    "rejection_reason": "Kampaniya şərtlərini pozur.",
    "approved_at": null,
    "scheduled_at": null,
    "sent_at": null,
    "created_at": "27.04.2026 10:00",
    "translations": [
      {
        "language_id": 1,
        "title": "Endirim başladı",
        "body": "Bu həftə bütün məhsullarda 20% endirim!"
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

### 3. Bildiriş yarat

```
POST /sellers/notifications/add
```

**Request body:**

```json
{
  "target_type": "bulk",
  "channels": ["in_app", "push"],
  "action_type": "none",
  "action_id": null,
  "image": null,
  "translations": [
    {
      "language_id": 1,
      "title": "Yeni kampaniya AZ",
      "body": "Bu həftə bütün məhsullarda endirim!"
    },
    {
      "language_id": 2,
      "title": "New Campaign EN",
      "body": "Discounts on all products this week!"
    },
    {
      "language_id": 3,
      "title": "Новая акция RU",
      "body": "Скидки на все товары на этой неделе!"
    }
  ]
}
```

**`target_type: individual` nümunəsi:**

```json
{
  "target_type": "individual",
  "target_filters": {
    "user_ids": [101, 205, 318]
  },
  "channels": ["in_app"],
  "translations": [ ... ]
}
```

**Qeydlər:**
- `bulk` seçildikdə `target_filters` göndərməyə ehtiyac yoxdur — server avtomatik tətbiq edir
- `individual` seçildikdə `target_filters.user_ids` mütləqdir
- `translations` — bütün dillər üçün göndərin (1=AZ, 2=EN, 3=RU)

**Response:**

```json
{
  "message": "Bildiriş təsdiq üçün göndərildi.",
  "data": {
    "uuid": "550e8400-..."
  }
}
```

---

### 4. Bildiriş yenilə

```
POST /sellers/notifications/update/:notification
```

Yalnız `pending_approval` və `rejected` statuslu bildirişlər yenilənə bilər.  
Yalnız göndərilən sahələr dəyişdirilir — göndərilməyən sahələr olduğu kimi qalır.

**Request body (hamısı optional):**

```json
{
  "channels": ["in_app", "push", "email"],
  "action_type": "product",
  "action_id": "mahsul-slug",
  "image": "https://cdn.example.com/banner.jpg",
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

### 5. Bildiriş sil

```
DELETE /sellers/notifications/delete/:notification
```

Yalnız `pending_approval` və `rejected` statuslu bildirişlər silinə bilər.

**Response:**

```json
{
  "message": "Bildiriş silindi."
}
```

---

### 6. Yenidən göndər

```
POST /sellers/notifications/resend/:notification
```

Yalnız `sent` və ya `cancelled` statuslu bildirişlər yenidən göndərilə bilər.  
Orijinal bildiriş dəyişdirilmir — **yeni klon** yaranır, yenidən `pending_approval` statusuna düşür.

**Response:**

```json
{
  "message": "Bildiriş təsdiq üçün göndərildi.",
  "data": {
    "uuid": "yeni-klon-uuid"
  }
}
```

---

### 7. Hədəf sayı önizləmə

```
GET /sellers/notifications/targeting-preview
```

Bu satıcının neçə müştərisinin bildiriş alacağını göstərir. `bulk` üçündür — `individual` üçün `user_ids.length` göstərmək kifayət edir.

**Response:**

```json
{
  "count": 127
}
```

---

## Tam iş axını — UI nöqteyi-nəzərindən

### Yeni bildiriş yaratma ekranı

```
1. Əvvəlcə neçə nəfərə gedəcəyini göstər:
   GET /notifications/targeting-preview → count göstər

2. Formu doldur + göndər:
   POST /notifications/add → uuid al

3. Siyahıya qayıt:
   GET /notifications/load → yeni bildiriş "pending_approval" statusunda görünür
```

### Rədd edilmiş bildirişi düzəltmə

```
1. Siyahıdan "rejected" bildirişi seç
   GET /notifications/show/:uuid → rejection_reason oxu

2. Düzəlt:
   POST /notifications/update/:uuid → dəyişiklikləri yadda saxla

3. Yenidən göndər:
   POST /notifications/resend/:uuid → yeni klon "pending_approval"-a düşür
```

### Göndərilmiş bildirişi yenidən göndər

```
1. "sent" statuslu bildirişi seç
   POST /notifications/resend/:uuid → yeni klon yaranır, admin təsdiqi gözlənilir
```

---

## Xəta cavabları

| HTTP kod | Hal |
|----------|-----|
| `403` | Başqa satıcının bildirişinə giriş cəhdi |
| `422` | Redaktə edilə bilməz (status uyğun deyil) |
| `422` | Silinə bilməz (status uyğun deyil) |
| `422` | Yalnız `sent`/`cancelled` yenidən göndərilə bilər |
