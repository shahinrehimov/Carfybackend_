# Advertisements — App / Site API

Base URL: `{{url}}/app` (mobil) və ya `{{url}}/site` (veb)

Header: `Token: {{app_site_token}}` + `accept: application/json`

> Bu endpointlər auth tələb etmir. Bununla belə, əgər `Authorization: Bearer` token göndərilirsə istifadəçi məlumatı click statistikasında qeydlənir.

---

## Endpointlər

### Active — Aktiv Reklamlar
```
GET /{provider}/advertisements/active
```

Hal-hazırda aktiv olan reklamları qaytarır. Aşağıdakı şərtlərin hamısı ödənilməlidir:
- `is_active = true`
- `start_date <= bugün <= end_date`
- `platform = {provider}` VƏ ya `platform = both`

**Response:**
```json
{
  "data": [
    {
      "title": "Yay Endirimi",
      "key": "yay-endirimi",
      "image_url": "https://cdn.example.com/ads/yay-endirimi.gif",
      "link": "https://api.example.com/app/advertisements/yay-endirimi/click"
    }
  ]
}
```

> `link` field-i birbaşa hədəf URL deyil — click statistikasını qeydləmək üçün backend redirect endpoint-idir. Frontend bu linki olduğu kimi açmalıdır.

---

### By Key — Key ilə Reklam
```
GET /{provider}/advertisements/by-key/{key}
```

Müəyyən `key` ilə tək reklam qaytarır. Eyni aktiv yoxlama tətbiq olunur.

**Path params:**

| Param | Açıqlama |
|---|---|
| `provider` | `site` və ya `app` |
| `key` | Reklam key-i (məs. `yay-endirimi`) |

**Response:**
```json
{
  "data": {
    "title": "Yay Endirimi",
    "key": "yay-endirimi",
    "image_url": "https://cdn.example.com/ads/yay-endirimi.gif",
    "link": "https://api.example.com/app/advertisements/yay-endirimi/click"
  }
}
```

**Xəta (reklam tapılmadıqda, deaktiv və ya başqa platform üçün olduqda):**
```json
{ "message": "No query results for model [Advertisement]." }
```
HTTP 404

---

### Click — Redirect & Statistika
```
GET /{provider}/advertisements/{key}/click
```

Click-i qeydləyib orijinal `link`-ə `302 redirect` edir. Frontend bu URL-i birbaşa açmalıdır (`fetch` / `axios` ilə deyil).

**Throttle:** dəqiqədə 30 sorğu per-IP (bot müdafiəsi).

**Path params:**

| Param | Açıqlama |
|---|---|
| `provider` | `site` və ya `app` |
| `key` | Reklam key-i |

**Auth konteksti (prioritet sırası):**

| Durum | Qeydlənən məlumat |
|---|---|
| `Authorization: Bearer {token}` göndərilibsə | `user_id` qeydlənir |
| Yalnız `identifier` header göndərilibsə | `guest_identifier` qeydlənir |
| Heç biri yoxdursa | Yalnız IP + User-Agent |

**Response:** `302 Location: {orijinal link}` (redirect)

**Xəta (reklam tapılmadıqda və ya deaktivdirsə):** HTTP 404

---

## İstifadə nümunəsi

```js
// 1. Aktiv reklamları yüklə
const { data } = await axios.get('/site/advertisements/active');

// 2. Şəkli göstər
// data[0].image_url — birbaşa istifadə et

// 3. Kliklənəndə link-i aç (redirect link-dir, birbaşa aç)
window.open(data[0].link, '_blank');
// və ya
window.location.href = data[0].link;
```

---

## Qeydlər

- `link` field-i **həmişə** backend redirect endpoint-ini göstərir (`/advertisements/{key}/click`). Frontend orijinal linki bilmir və bilməməlidir — bütün click-lər backendən keçir.
- Reklam deaktiv olduqda, tarixi keçdikdə və ya başqa platform üçün olduqda `by-key` və `click` endpointləri 404 qaytarır.
- `active` endpoint-i boş massiv qaytara bilər (`"data": []`) — bu normaldır, aktiv reklam yoxdur deməkdir.
