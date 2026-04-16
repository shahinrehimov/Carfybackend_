# Advertisements — App / Site API

Base URL: `{{url}}/app` (mobil) və ya `{{url}}/site` (veb)

Header: `Token: {{app_site_token}}` + `accept: application/json`

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
      "key": "yay_endirimi",
      "image_url": "https://cdn.example.com/ads/yay_endirimi.gif",
      "link": "https://api.example.com/ads/yay_endirimi/click"
    }
  ]
}
```

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
| `key` | Reklam key-i (məs. `yay_endirimi`) |

**Response:**
```json
{
  "data": {
    "title": "Yay Endirimi",
    "key": "yay_endirimi",
    "image_url": "https://cdn.example.com/ads/yay_endirimi.gif",
    "link": "https://api.example.com/ads/yay_endirimi/click"
  }
}
```

**Xəta (reklam tapılmadıqda, deaktiv və ya başqa platform üçün olduqda):**
```json
{ "message": "No query results for model [Advertisement]." }
```
HTTP 404

---

## İstifadə nümunəsi

```html
<!-- link-i birbaşa href-ə ver, o qədər -->
<a href="{{ data.link }}">
  <img src="{{ data.image_url }}" />
</a>
```

`link` field-i backend redirect URL-idir — frontend başqa heç nə etmir, click statistikası avtomatik qeydlənir.

---

## Qeydlər

- `link` field-i **həmişə** backend redirect URL-idir (`/ads/{key}/click`). Frontend orijinal linki bilmir — bütün click-lər backendən keçir.
- Reklam deaktiv olduqda, tarixi keçdikdə və ya başqa platform üçün olduqda `by-key` endpoint-i 404 qaytarır.
- `active` endpoint-i boş massiv qaytara bilər (`"data": []`) — aktiv reklam yoxdur deməkdir.
