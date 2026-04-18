# Control Panel — Statistics & Analytics

Base URL: `/api/control`  
Auth: `Bearer {{token}}` + `Token: {{app_token}}`

---

## Endpoints

| Method | URL | Açıqlama |
|--------|-----|----------|
| GET | `/control/statistics/orders` | Sifariş statistikası + chart |
| GET | `/control/statistics/top-stores` | Ən çox satış edən mağazalar |
| GET | `/control/statistics/top-products` | Ən çox satılan və baxılan məhsullar |
| GET | `/control/statistics/users` | İstifadəçi statistikası |
| GET | `/control/statistics/cancellations` | Ləğvetmə statistikası |
| GET | `/control/statistics/commissions` | Komissiyon gəliri *(TODO)* |

---

## Ümumi filtrlər

Aşağıdakı parametrlər bütün endpointlərdə mövcuddur (hamısı opsional):

| Param | Tip | Açıqlama |
|-------|-----|----------|
| `date_from` | `YYYY-MM-DD` | Başlanğıc tarix |
| `date_to` | `YYYY-MM-DD` | Son tarix (`date_from`-dan böyük olmalıdır) |
| `company_id` | integer | Konkret mağaza üzrə filter (göndərilmədikdə bütün mağazalar) |

> `date_to` həmişə o günün sonuna (23:59:59) qədər əhatə edir.  
> Pul məbləğləri manatla (AZN) qaytarılır, qəpiklə deyil.

---

## GET `/control/statistics/orders`

Platformanın sifariş statistikasını qaytarır: xülasə + dövri chart.

### Əlavə parametrlər

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `period` | `daily` \| `weekly` \| `monthly` | `daily` | Chart qruplaşdırma dövrü |
| `status` | string | — | Sifariş statusuna görə filter |

Mövcud status dəyərləri: `pending`, `payment_pending`, `failed`, `processing`, `confirmed`, `shipped`, `delivered`, `canceled`, `returned`

### Response

```json
{
  "data": {
    "summary": {
      "total_orders": 214,
      "total_revenue": 18750.00,
      "total_quantity": 389
    },
    "chart": [
      {
        "date": "2026-04-01",
        "order_count": 12,
        "revenue": 950.00,
        "quantity": 20
      },
      {
        "date": "2026-04-02",
        "order_count": 8,
        "revenue": 640.00,
        "quantity": 14
      }
    ]
  }
}
```

**Qeydlər:**
- `period=monthly` olduqda `date` sahəsi `YYYY-MM` formatında qaytarılır (məs. `"2026-04"`).
- `summary` həmişə mövcuddur; `chart` boş array ola bilər.
- `company_id` göndərildikdə yalnız həmin mağazanın order item-ləri hesablanır.

---

## GET `/control/statistics/top-stores`

Ən çox gəlir əldə edən mağazaları sıralanmış qaytarır.

### Əlavə parametrlər

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `limit` | integer | `10` | Nəticə sayı (1–50) |

### Response

```json
{
  "data": {
    "data": [
      {
        "company": {
          "id": 3,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "name": "AutoPart Baku"
        },
        "quantity_sold": 128,
        "revenue": 6400.00,
        "order_count": 94
      }
    ]
  }
}
```

**Qeydlər:**
- Sıralama `revenue` üzrədir (azalan).

---

## GET `/control/statistics/top-products`

Platformanın ən çox satılan və ən çox baxılan CPV-lərini qaytarır.

### Əlavə parametrlər

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `limit` | integer | `10` | Hər siyahıdakı nəticə sayı (1–50) |

### Response

```json
{
  "data": {
    "top_selling": [
      {
        "cpv": {
          "id": 12,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "title": "Brake Pad Set — Honda Civic 2018 (Front)",
          "main_image": "https://cdn.example.com/image.jpg"
        },
        "quantity_sold": 42,
        "revenue": 1260.00,
        "order_count": 35
      }
    ],
    "top_viewed": [
      {
        "cpv": {
          "id": 7,
          "uuid": "661e9500-f30c-52e5-b827-557766551111",
          "title": "Engine Oil Filter — Toyota Camry 2020",
          "main_image": "https://cdn.example.com/image2.jpg"
        },
        "view_count": 580
      }
    ]
  }
}
```

**Qeydlər:**
- `top_selling` — `quantity_sold` üzrə azalan sıralama.
- `top_viewed` — `view_count` üzrə azalan sıralama.
- `company_id` filtri hər iki siyahıya tətbiq olunur.
- `main_image` — null ola bilər.

---

## GET `/control/statistics/users`

İstifadəçi aktivliyini üç metrikada qaytarır.

### Əlavə parametrlər

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `period` | `daily` \| `weekly` \| `monthly` | `daily` | Qeydiyyat chart-ı üçün qruplaşdırma |

### Response

```json
{
  "data": {
    "summary": {
      "registered": 312,
      "active_via_login": 187,
      "active_via_order": 94
    },
    "registration_chart": [
      {
        "date": "2026-04-01",
        "count": 14
      },
      {
        "date": "2026-04-02",
        "count": 9
      }
    ]
  }
}
```

**Sahələrin izahı:**

| Sahə | Açıqlama |
|------|----------|
| `registered` | Seçilmiş dövrdə qeydiyyatdan keçən istifadəçi sayı |
| `active_via_login` | Seçilmiş dövrdə son Sanctum tokeni yaradılan (login olmuş) istifadəçi sayı |
| `active_via_order` | Seçilmiş dövrdə ən az 1 sifariş vermiş unikal istifadəçi sayı |
| `registration_chart` | Qeydiyyatın dövri paylanması |

**Qeydlər:**
- `company_id` bu endpoint-ə tətbiq edilmir — istifadəçi statistikası platformanın ümumi göstəricisidir.
- `active_via_login` → `personal_access_tokens.created_at` (MAX per user) bazasında hesablanır.

---

## GET `/control/statistics/cancellations`

Ləğvedilmiş sifarişlərin statistikasını, aktyor bölgüsünü və ən çox rast gəlinən səbəbləri qaytarır.

### Əlavə parametrlər

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `limit` | integer | `10` | Top səbəblər siyahısının uzunluğu (1–50) |

### Response

```json
{
  "data": {
    "total_cancelled": 28,
    "by_actor": [
      { "actor": "Admin", "count": 18 },
      { "actor": "CompanyAdmin", "count": 10 }
    ],
    "top_reasons": [
      { "reason": "Stokda məhsul yoxdur", "count": 9 },
      { "reason": "Müştəri tələbi ilə", "count": 7 }
    ]
  }
}
```

**Sahələrin izahı:**

| Sahə | Açıqlama |
|------|----------|
| `total_cancelled` | Seçilmiş dövrdə ləğv edilmiş sifariş sayı |
| `by_actor` | Kim tərəfindən ləğv edildiyinin bölgüsü (`Admin` / `CompanyAdmin`) |
| `top_reasons` | Ən çox istifadə edilən ləğvetmə səbəbləri |

**Qeydlər:**
- `by_actor` yalnız `cancelled_by` dolu olan sifarişləri əhatə edir.
- `top_reasons` yalnız `cancel_reason` dolu olan sifarişləri əhatə edir.
- `company_id` filtri tətbiq olunduqda həmin mağazanın sifarişlərindəki item-lərə görə filter edilir.

---

## GET `/control/statistics/commissions`

> **TODO:** Komissiyon məntiqi hələ müəyyənləşdirilməyib. Hazırda sıfır dəyərlər qaytarır.

### Response

```json
{
  "data": {
    "total_commission": 0,
    "chart": []
  }
}
```

---

## Order Cancel — Yeni sahələr

`POST /control/orders/change-status/{order}` endpoint-inə `canceled` statusu göndərildikdə `cancel_reason` məcburidir:

### Request body

```json
{
  "status": "canceled",
  "cancel_reason": "Stokda məhsul yoxdur"
}
```

| Param | Tip | Tələb | Açıqlama |
|-------|-----|-------|----------|
| `status` | string | məcburi | Yeni sifariş statusu |
| `cancel_reason` | string | `canceled` statusunda məcburi | Ləğvetmə səbəbi (max 1000 simvol) |

**Qeyd:** `cancelled_by_type` / `cancelled_by_id` — avtomatik doldurulur (login olmuş admin/company admin məlumatından). Frontend-dən göndərmə tələb olunmur.
