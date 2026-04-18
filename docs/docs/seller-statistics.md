# Seller — Statistics & Analytics

Base URL: `/api/sellers`  
Auth: `Bearer {{login_token_seller}}` + `Token: {{app_seller_token}}`

---

## Endpoints

| Method | URL | Açıqlama |
|--------|-----|----------|
| GET | `/sellers/statistics/orders` | Sifariş statistikası (günlük / aylıq) |
| GET | `/sellers/statistics/top-selling` | Ən çox satılan məhsullar |
| GET | `/sellers/statistics/top-viewed` | Ən çox baxılan məhsullar |
| GET | `/sellers/statistics/customer-behavior` | Müştəri davranış analitikası |

> **View Tracking (App):** `POST /api/app/products/{cpv_uuid}/track-view`  
> Müştəri məhsul səhifəsini açanda bu endpoint çağırılmalıdır. Seller statistikasına daxil olur.

---

## GET `/sellers/statistics/orders`

Sellerin sifarişlərini günlük və ya aylıq qruplaşdırılmış formada qaytarır.

### Query parametrləri

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `period` | `daily` \| `monthly` | `daily` | Qruplaşdırma dövr |
| `date_from` | date | — | Başlanğıc tarix (`YYYY-MM-DD`) |
| `date_to` | date | — | Son tarix (`YYYY-MM-DD`), `date_from`-dan böyük olmalıdır |
| `status` | string | — | Sifariş statusuna görə filter (`pending`, `confirmed`, `shipped`, `delivered`, `canceled` və s.) |

### Response

```json
{
  "data": {
    "summary": {
      "total_orders": 47,
      "total_revenue": 2350.00,
      "total_quantity": 63
    },
    "chart": [
      {
        "date": "2026-04-01",
        "order_count": 5,
        "revenue": 250.00,
        "quantity": 7
      },
      {
        "date": "2026-04-02",
        "order_count": 3,
        "revenue": 150.00,
        "quantity": 4
      }
    ]
  }
}
```

**Qeydlər:**
- `period=monthly` olduqda `date` sahəsi `YYYY-MM` formatında qaytarılır (məs. `"2026-04"`).
- `period=daily` olduqda `date` sahəsi `YYYY-MM-DD` formatında qaytarılır.
- `total_revenue` və `revenue` — manatla (qəpik deyil). Göstərilərkən `₼` simvolu əlavə et.
- `summary` həmişə mövcuddur, `chart` boş array ola bilər (həmin dövrdə sifariş yoxdursa).
- `status` parametri göndərilmədikdə bütün statuslar daxil edilir.

---

## GET `/sellers/statistics/top-selling`

Sellerin ən çox satılan məhsullarını miqdar üzrə sıralanmış qaytarır.

### Query parametrləri

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `date_from` | date | — | Başlanğıc tarix (`YYYY-MM-DD`) |
| `date_to` | date | — | Son tarix (`YYYY-MM-DD`) |
| `limit` | integer | `10` | Nəticə sayı (5–50) |

### Response

```json
{
  "data": {
    "data": [
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
    ]
  }
}
```

**Qeydlər:**
- Sıralama `quantity_sold` üzrədir (azalan).
- `main_image` — null ola bilər (məhsulun şəkli yoxdursa).
- `revenue` — manatla.
- `cpv.uuid` — məhsul səhifəsinə link üçün istifadə et.

---

## GET `/sellers/statistics/top-viewed`

Sellerin ən çox baxılan məhsullarını sıralanmış qaytarır.

> **Vacib:** Bu statistika yalnız frontend `POST /products/{uuid}/track-view` çağırdıqdan sonra doldurulmağa başlayır. Əvvəlki baxışlar qeydə alınmır.

### Query parametrləri

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `date_from` | date | — | Başlanğıc tarix (`YYYY-MM-DD`) |
| `date_to` | date | — | Son tarix (`YYYY-MM-DD`) |
| `limit` | integer | `10` | Nəticə sayı (5–50) |

### Response

```json
{
  "data": {
    "data": [
      {
        "cpv": {
          "id": 12,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "title": "Brake Pad Set — Honda Civic 2018 (Front)",
          "main_image": "https://cdn.example.com/image.jpg"
        },
        "view_count": 310
      }
    ]
  }
}
```

---

## GET `/sellers/statistics/customer-behavior`

Müştəri davranış analitikasını qaytarır: baxışdan səbətə, səbətdən sifarişə konversiya nisbətləri və ən çox tərk edilən məhsullar.

### Query parametrləri

| Param | Tip | Default | Açıqlama |
|-------|-----|---------|----------|
| `date_from` | date | — | Başlanğıc tarix (`YYYY-MM-DD`) |
| `date_to` | date | — | Son tarix (`YYYY-MM-DD`) |
| `abandoned_limit` | integer | `10` | Tərk edilmiş məhsullar siyahısının uzunluğu (5–50) |

### Response

```json
{
  "data": {
    "overview": {
      "total_views": 1200,
      "total_basket_adds": 240,
      "converted_baskets": 96,
      "view_to_basket_rate": 20.0,
      "basket_to_order_rate": 40.0,
      "view_to_order_rate": 8.0
    },
    "abandoned_products": [
      {
        "cpv": {
          "id": 12,
          "uuid": "550e8400-e29b-41d4-a716-446655440000",
          "title": "Brake Pad Set — Honda Civic 2018 (Front)",
          "main_image": "https://cdn.example.com/image.jpg"
        },
        "basket_count": 50,
        "order_count": 10,
        "abandoned_count": 40,
        "abandonment_rate": 80.0
      }
    ]
  }
}
```

**Sahələrin izahı:**

| Sahə | Açıqlama |
|------|----------|
| `total_views` | Məhsul səhifəsinə ümumi baxış sayı |
| `total_basket_adds` | Səbətə əlavə edilmə sayı |
| `converted_baskets` | Sifarişə çevrilmiş səbət əlavə sayı |
| `view_to_basket_rate` | Baxış → Səbət nisbəti (%) |
| `basket_to_order_rate` | Səbət → Sifariş nisbəti (%) |
| `view_to_order_rate` | Baxış → Sifariş nisbəti (%) |
| `abandoned_products` | Ən çox tərk edilən məhsullar (`abandoned_limit` qədər) |
| `abandonment_rate` | Həmin məhsul üçün tərk etmə faizi (%) |

**Qeydlər:**
- `view_to_basket_rate = total_basket_adds / total_views * 100`
- `basket_to_order_rate = converted_baskets / total_basket_adds * 100`
- Hər hansı bir məxrəc `0` olduqda rate `0.0` qaytarılır (sıfıra bölmə olmur).
- `abandoned_products` — `basket_count > order_count` olan CPV-lər, azalan `abandoned_count` üzrə sıralanır.

---

## POST `/api/app/products/{cpv_uuid}/track-view`

Müştəri bir seller məhsulunun detallarını açanda bu endpoint-i çağır.

**Auth:** Opsional (həm autentifikasiya olunmuş, həm də qonaq istifadəçilər üçün çalışır)  
**Throttle:** 30 request/dəqiqə (hər IP üçün)

### URL parametri

| Param | Açıqlama |
|-------|----------|
| `cpv_uuid` | `CompanyProductVariant`-in UUID-si |

### Headers (qonaq üçün)

```
X-Guest-Identifier: {{guest_identifier}}
```

### Response

```json
{
  "data": []
}
```

**Qeyd:** Bu endpoint-i məhsul detalları yükləndikdən sonra fire-and-forget olaraq çağır. Cavabı gözləmək məcburi deyil.

---

## Texniki qeydlər

- Bütün tarix filterləri `YYYY-MM-DD` formatındadır.
- `date_from` / `date_to` opsionaldır; göndərilmədikdə bütün tarixlər üzrə hesablanır.
- `date_to` həmişə o günün sonuna (23:59:59) qədər əhatə edir.
- Bütün pul məbləğləri manatla (AZN) qaytarılır, qəpiklə deyil.
