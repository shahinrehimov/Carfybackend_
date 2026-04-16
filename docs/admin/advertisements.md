# Advertisements — Admin Panel API

Base URL: `{{url}}/control`

Auth: Bearer `{{token}}` + Header `Token: {{app_token}}`

---

## Endpointlər

### Load (paginated)
```
GET /control/advertisements/load
```

Bütün reklamları filtrlənmiş şəkildə qaytarır. `is_currently_active` filteri həm `is_active = true` həm də bu günün `start_date..end_date` aralığına düşdüyünü yoxlayır.

**Query params:**

| Param | Tip | Açıqlama |
|---|---|---|
| `search` | string | `title` və `key` üzrə axtarış |
| `limit` | integer | Səhifə ölçüsü (5–100, default 10) |
| `page` | integer | Səhifə nömrəsi |
| `platform` | string | `site` / `app` / `both` |
| `is_active` | boolean | `is_active` statusuna görə filter |
| `is_currently_active` | boolean | Həm `is_active` həm tarix aralığına görə filter |
| `date_from` | date | `start_date >= date_from` |
| `date_to` | date | `end_date <= date_to` |

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Yay Endirimi",
      "key": "yay_endirimi",
      "link": "https://example.com/sale",
      "image_url": "https://cdn.example.com/ads/yay_endirimi.gif",
      "start_date": "2026-06-01",
      "end_date": "2026-08-31",
      "is_active": true,
      "is_currently_active": true,
      "platform": "both",
      "click_count": 142,
      "created_user": "admin",
      "created_date": "2026-04-16 10:00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}
```

---

### Show
```
GET /control/advertisements/show/{id}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "Yay Endirimi",
    "key": "yay_endirimi",
    "description": "Admin üçün daxili qeyd",
    "link": "https://example.com/sale",
    "image_url": "https://cdn.example.com/ads/yay_endirimi.gif",
    "start_date": "2026-06-01",
    "end_date": "2026-08-31",
    "is_active": true,
    "is_currently_active": true,
    "platform": "both",
    "click_count": 142,
    "created_user": "admin",
    "created_date": "2026-04-16 10:00:00",
    "updated_date": "2026-04-16 12:00:00"
  }
}
```

---

### Add
```
POST /control/advertisements/add
Content-Type: multipart/form-data
```

**Body (form-data):**

| Field | Tələb | Açıqlama |
|---|---|---|
| `title` | required | Reklam başlığı (max 255) |
| `description` | nullable | Admin üçün daxili qeyd |
| `link` | required | Yönləndiriləcək URL (tam format: https://...) |
| `start_date` | required | Başlama tarixi `Y-m-d` |
| `end_date` | required | Bitmə tarixi `Y-m-d`, `start_date`-dən böyük olmalıdır |
| `is_active` | nullable | `0` / `1`, default `0` |
| `platform` | required | `site` / `app` / `both` |
| `image` | nullable | jpg, jpeg, png, webp, gif — max 5MB |

> `key` avtomatik yaradılır: `title` → snake_case. Əgər eyni key mövcuddursa `_2`, `_3` şəkilçisi əlavə olunur.

**Response:**
```json
{
  "data": {
    "message": "Advertisement saved successfully!",
    "data": { /* AdvertisementResource */ }
  }
}
```

---

### Update
```
POST /control/advertisements/update/{id}
Content-Type: multipart/form-data
```

`Add` ilə eyni field-lər, hamısı optional-dır. Yalnız göndərilən field-lər yenilənir.

> `title` dəyişdirildikdə `key` də avtomatik yenilənir.

---

### Delete
```
DELETE /control/advertisements/delete/{id}
```

Soft delete — `is_deleted = 'deleted_{id}'` olur.

**Response:**
```json
{
  "data": { "message": "Advertisement deleted successfully!" }
}
```

---

### Change Active Status
```
POST /control/advertisements/change-active-status/{id}
```

`is_active` statusunu toggle edir (`true` → `false`, `false` → `true`).

> Diqqət: Bu yalnız `is_active` field-ini dəyişir. Reklam `is_active = true` olsa belə, tarix aralığına düşmürsə `is_currently_active = false` qalır.

**Response:**
```json
{
  "data": { "message": "Status changed successfully!" }
}
```

---

### Clicks (paginated)
```
GET /control/advertisements/clicks/{id}
```

Bir reklama aid bütün click qeydlərini qaytarır. `click_count` sürətli göstərmək üçün `advertisements` cədvəlindədir; bu endpoint isə ətraflı analitika üçündür.

**Query params:**

| Param | Tip | Açıqlama |
|---|---|---|
| `limit` | integer | Səhifə ölçüsü (5–100, default 20) |
| `page` | integer | Səhifə nömrəsi |
| `date_from` | date | `Y-m-d` — bu tarixdən başlayan clicklər |
| `date_to` | date | `Y-m-d` — bu tarixə qədər olan clicklər |
| `user_type` | string | `authenticated` / `guest` / `anonymous` |

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_type": "authenticated",
      "user_id": 42,
      "guest_identifier": null,
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0 ...",
      "clicked_at": "2026-04-16 14:30:00"
    },
    {
      "id": 2,
      "user_type": "guest",
      "user_id": null,
      "guest_identifier": "550e8400-e29b-...",
      "ip_address": "10.0.0.5",
      "user_agent": "Mozilla/5.0 ...",
      "clicked_at": "2026-04-16 14:31:00"
    },
    {
      "id": 3,
      "user_type": "anonymous",
      "user_id": null,
      "guest_identifier": null,
      "ip_address": "172.16.0.1",
      "user_agent": "curl/7.68.0",
      "clicked_at": "2026-04-16 14:32:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 98 }
}
```

**`user_type` məntiqi:**
- `authenticated` — `user_id` dolu, login etmiş istifadəçi
- `guest` — `user_id` boş, amma `guest_identifier` var (app-də qeydiyyatsız istifadəçi)
- `anonymous` — heç bir token yoxdur, yalnız IP qeydlənib

---

## Qeydlər

- **`is_currently_active`** computed field-dir, saxlanılmır. Hər sorğuda hesablanır: `is_active = true` VƏ `start_date <= today <= end_date`.
- **`click_count`** real vaxtda artırılır — ayrıca sorğuya ehtiyac yoxdur.
- **Platform `both`** — həm `site` həm `app` üçün göstərilir.
- GIF dəstəklənir (image yükləmədə `gif` tipi qəbul edilir).
