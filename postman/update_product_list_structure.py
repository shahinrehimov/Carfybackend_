#!/usr/bin/env python3
"""
Documents the canonical product list structure in the Postman collection.

Every app/site list endpoint (products load, groups, related, order items,
basket, wishlist) now renders through CompanyProductVariantListResource, so the
item shape is identical everywhere. This script writes that contract into the
folder/request descriptions of the affected endpoints.

Usage: python3 postman/update_product_list_structure.py
"""

import json
from pathlib import Path

COLLECTION = Path(__file__).resolve().parent / "Carfy.postman_collection.json"

CANONICAL = """CANONICAL PRODUCT LIST ITEM
---------------------------
Every product list in the app/site API returns this exact object. No key is
ever conditionally omitted — a missing value is null (or 0 / [] where noted).

{
  "uuid": "51ff7a5f-...",            // CPV uuid — add to basket/wishlist, products/show/{uuid}
  "variant_slug": "2",               // detail URL ?variant=
  "title": "Test salam az 2",
  "oem_code": null,
  "hover_image": "https://.../x.png",// seller image -> variant image -> product image
  "is_set": false,
  "stock": 99,
  "price": 199.99,                   // AZN
  "price_final": 199.99,
  "has_discount": false,             // use THIS, not `discount` — discount stays
                                     // filled after the discount window expires
  "discount_type": "by_percentage",  // by_percentage | by_amount | null
  "discount_type_title": "Faizle",
  "discount": 10,
  "in_wishlist": false,
  "in_basket": true,
  "basket_quantity": 1,              // 0 when not in basket
  "price_in_basket": 199.99,         // price * basket_quantity
  "price_final_in_basket": 199.99,   // price_final * basket_quantity
  "ratings": { "average": 0, "count": 0 },
  "seller": {
    "uuid": "51ff7a5f-...",          // same as top-level uuid
    "store_code": "RDBTZ",           // detail URL ?seller=  (REQUIRED on slug route)
    "label": "Magaza RDBTZ",
    "city": { "id": 1, "title": "Baki" },
    "images": [ { "url": "https://..." } ]
  },
  "product": {
    "id": 2,
    "uuid": "8db4748b-...",
    "slug": "test-salam-az",
    "category_slugs": {
      "parent_category_slug": "test",// may be null
      "sub_category_slug": "test-1"
    },
    "title": "Test salam az",
    "description_short": "...",
    "hover_image": "https://.../x.png",
    "is_set": false,
    "brand": { ... }
  }
}

basket/load and wishlist/load add one extra key: `sets` — the sub-items of a
set product, each in this same structure (with seller: null).

NAVIGATING TO THE DETAIL PAGE
  by uuid:  GET /products/show/{uuid}
  by slug:  GET /products/show/{parent_category_slug}/{sub_category_slug}/{product.slug}
                ?variant={variant_slug}&seller={seller.store_code}
            (no parent slug: GET /products/show/{sub_category_slug}/{product.slug}?...)
  The slug route REQUIRES `seller` and it is seller.store_code, not a uuid."""

BREAKING = """BREAKING (2026-07-27) — this endpoint moved to the canonical list structure:
  - `id` removed          -> use `uuid` (CPV) or `product.id`
  - `slug`                -> renamed to `variant_slug`
  - `seller.seller_label` -> renamed to `seller.label`
  - added: `seller.store_code`, `variant_slug`, `ratings`
  - `basket_quantity` is now always present (0 when not in basket)
  - `product.category_slugs` now always has both parent + sub keys
  Unchanged: hover_image, seller.images, price_in_basket,
  price_final_in_basket, sets, and the basket_data block."""

ADDED = """UPDATED (2026-07-27) — nothing was removed or renamed here. Added to each item:
  `basket_quantity`, `price_in_basket`, `price_final_in_basket`, `seller.images`.
  `ratings`, `seller.city` and `product` are no longer dropped when empty — they
  are always present (null / zeroed). `product.category_slugs` always carries
  both `parent_category_slug` and `sub_category_slug`."""

SHOW_NOTE = """UPDATED (2026-07-27) — the `seller` block gained `store_code` and `label`.
  `seller_label` is kept for backwards compatibility but is deprecated; use
  `label`. The same keys were added to `sets[].seller`.
  `related_products[]` follows the canonical product list structure (see the
  Products / Load description)."""

# path -> (mode, note). mode: "replace" appends the note + canonical block,
# "note" appends the note only.
UPDATES = {
    ("App", "Products", "Load"): ("full", ADDED),
    ("App", "Basket", "Load"): ("full", BREAKING),
    ("App", "Wishlist", "Load"): ("full", BREAKING),
    ("App", "Groups", "Single with products"): ("note", ADDED),
    ("App", "Groups", "Bulk with products"): ("note", ADDED),
    ("App", "Products", "Show by uuid"): ("note", SHOW_NOTE),
    ("App", "Products", "Show by slug"): ("note", SHOW_NOTE),
    ("App", "Products", "Show by slug (only sub category)"): ("note", SHOW_NOTE),
    ("App", "Orders", "Checkout Preview"): (
        "note",
        "UPDATED (2026-07-27) — `sellers[].items[].product` follows the canonical\n"
        "  product list structure (see the Products / Load description).",
    ),
    ("App", "Orders", "Details"): (
        "note",
        "UPDATED (2026-07-27) — `items[].product` follows the canonical product list\n"
        "  structure (see the Products / Load description).",
    ),
    ("App", "Orders", "Show"): (
        "note",
        "UPDATED (2026-07-27) — `items[].product` follows the canonical product list\n"
        "  structure (see the Products / Load description).",
    ),
}

MARKER = "CANONICAL PRODUCT LIST ITEM"
NOTE_MARKER = "(2026-07-27)"


def walk(items, path=()):
    for item in items:
        current = path + (item["name"],)
        if "item" in item:
            yield from walk(item["item"], current)
        else:
            yield current, item


def main():
    collection = json.loads(COLLECTION.read_text())
    touched = []

    for path, item in walk(collection["item"]):
        if path not in UPDATES:
            continue

        mode, note = UPDATES[path]
        request = item["request"]
        description = (request.get("description") or "").rstrip()

        # Idempotent: strip anything appended by a previous run.
        for marker in (NOTE_MARKER, MARKER):
            index = description.find(marker)
            if index != -1:
                description = description[:index].rstrip()

        block = note if mode == "note" else note + "\n\n" + CANONICAL
        request["description"] = (description + "\n\n" + block).strip() + "\n"
        touched.append(" / ".join(path))

    missing = set(UPDATES) - {tuple(p.split(" / ")) for p in touched}
    if missing:
        raise SystemExit(f"Endpoints not found in collection: {sorted(missing)}")

    # Match the collection's existing formatting so the diff stays minimal.
    COLLECTION.write_text(json.dumps(collection, indent=2, ensure_ascii=False) + "\n")

    print(f"Updated {len(touched)} endpoints:")
    for name in touched:
        print("  " + name)


if __name__ == "__main__":
    main()
