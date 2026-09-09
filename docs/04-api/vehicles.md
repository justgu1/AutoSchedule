# API — Veículos

```text
GET    /api/vehicles                    -- catálogo público, ou ?scope=mine (admin/seller)
GET    /api/vehicles/filters             -- facetas pra popular filtro
GET    /api/vehicles/amenities-catalog   -- catálogo global de itens (público, só leitura)
GET    /api/vehicles/{id}                -- perfil completo (dono/admin) ou público (resto)
POST   /api/vehicles                     -- cria (admin/seller)
PATCH  /api/vehicles/{id}                -- edita, ou move de concessionária (admin/seller)
DELETE /api/vehicles/{id}                -- lixeira (admin/seller)
POST   /api/vehicles/{id}/restore
POST   /api/vehicles/{id}/purge
POST   /api/vehicles/{id}/photos         -- multipart images[], até 10 arquivos de 20MB
PATCH  /api/vehicles/{id}/photos         -- { order: [id, id, ...] } -- lista completa
DELETE /api/vehicles/{id}/photos/{image_id}
```

Regras: [`01-domain/business-rules.md`](../01-domain/business-rules.md) — VH-\* (veículo), AM-\*
(specs/itens), GL-\* (galeria), SR-\* (busca).

## `GET /vehicles` — filtros (query, todos combináveis)

```text
q, sort, brand, model, year_min, year_max, price_min, price_max,
dealership_id, transmission, body_type, fuel_type, mileage_km_max,
page, per_page
```

`sort`: `price_asc`, `price_desc`, `year_desc`, `created_asc`, `created_desc` — ausente, ordena por
relevância (SR-005). `q` com `per_page` pequeno também serve como autocomplete — não há endpoint
de sugestão separado.

## Campos aceitos em `POST`/`PATCH /vehicles`

```text
dealership_id, brand, model, version, manufacture_year, model_year, price, description,
mileage_km, transmission, body_type, fuel_type, color, plate_end_digit,
accepts_trade, ipva_paid, licensed, amenity_ids
```

`amenity_ids` substitui o conjunto inteiro quando enviado (AM-003) — não soma ao que já existia.
`transmission`/`body_type`/`fuel_type` são um dos valores do enum correspondente (ver
[`05-data/model.md`](../05-data/model.md)) ou omitido.
