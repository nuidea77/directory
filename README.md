# Лавлах.мн — Монголын бизнес лавлах

Байгууллага, үйлчилгээний нэгдсэн лавлах сайт. Laravel + Vue 3 + Tailwind CSS + MySQL.

## Онцлогууд

- 🔍 **Лавлах**: ангилал, дүүрэг, түлхүүр үгээр хайх; үнэлгээ, үзэлтээр эрэмбэлэх
- 📱 **Утасны баталгаажуулалт**: [verify.mn](https://verify.mn) — хэрэглэгч 144773 дугаарт SMS илгээж дугаараа баталгаажуулна (MO SMS)
- 💳 **Төлбөр**: [byl.mn](https://byl.mn) — QPay, SocialPay, Pocket, Golomt-оор «Онцлох байршуулалт» худалдан авах
- ⭐ **Үнэлгээ, сэтгэгдэл**, ❤️ **Хадгалах** (favorites)
- 🏪 **Бизнес удирдлага**: лого, ковер, галерей зураг, цагийн хуваарь, сошиал хаяг
- 🔌 **REST API** (`/api/v1`, Sanctum Bearer token) — мобайл апп-д шууд ашиглахад бэлэн

## Технологи

| Давхарга | Хэрэгсэл |
|---|---|
| Backend | Laravel 13, PHP 8.4, Sanctum |
| Frontend | Vue 3 (SPA), Vue Router, Pinia, Tailwind CSS 4, Vite |
| Өгөгдлийн сан | MySQL (production), SQLite (dev/test) |
| SMS баталгаажуулалт | verify.mn |
| Төлбөр | byl.mn |

## Суулгах

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# .env дотор MySQL холболтоо тохируулна:
#   DB_DATABASE=directory, DB_USERNAME=..., DB_PASSWORD=...

php artisan migrate --seed
php artisan storage:link

npm run build        # эсвэл хөгжүүлэлтэд: composer run dev
php artisan serve
```

### Гуравдагч үйлчилгээний тохиргоо (.env)

```env
# verify.mn — Developer Console-оос API key авна
VERIFY_MN_ENABLED=true          # false бол dev горимд SMS-гүйгээр авто баталгаажна
VERIFY_MN_API_KEY=vrf_xxxx

# byl.mn — Dashboard-оос token, project id, webhook secret авна
BYL_API_TOKEN=...
BYL_PROJECT_ID=...
BYL_WEBHOOK_SECRET=...
```

byl.mn dashboard дээр webhook URL-ийг `https://тань-домэйн/webhooks/byl` гэж бүртгүүлнэ.

## Архитектур

### Утас баталгаажуулах урсгал (verify.mn — MO SMS)

1. `POST /api/v1/auth/register` — нэр, утас, нууц үг. Сервер verify.mn дээр session үүсгээд `sms_uri`, `display_instruction`, `expires_at` буцаана. **Хэрэглэгч энэ үед үүсэхгүй.**
2. Хэрэглэгч 144773 дугаарт заасан кодоо SMS-ээр илгээнэ (UI дээр нэг товчтой `sms:` линк).
3. verify.mn манай callback (`GET /webhooks/verify-mn/{uuid}?token=...`) руу мэдэгдэнэ; сервер албан ёсны төлвийг `GET /sessions/:id`-ээр давхар шалгана (callback-д итгэхгүй).
4. Клиент `GET /api/v1/auth/verifications/{uuid}`-ийг 3 секунд тутам poll хийнэ. `verified` болмогц хэрэглэгч үүсч, Sanctum token олгогдоно (зөвхөн нэг удаа).
5. Нууц үг сэргээх ижил урсгалаар: `POST /auth/reset` → баталгаажуулалт → `POST /auth/reset/confirm`.

### Төлбөрийн урсгал (byl.mn)

1. `POST /api/v1/my/listings/{id}/feature` `{plan: featured_7|featured_30|featured_90}` — byl.mn invoice үүсгээд `invoice_url` буцаана.
2. Хэрэглэгч byl.mn hosted хуудсаар төлнө.
3. `POST /webhooks/byl` — `Byl-Signature` (HMAC-SHA256, raw body, constant-time харьцуулалт) шалгаад `invoice.paid` дээр төлбөрийг баталгаажуулж, `featured_until`-ийг сунгана. Давхардсан webhook-д idempotent.
4. Клиент `GET /api/v1/payments/{id}`-ээр төлөв шалгах боломжтой (fallback polling).

## API (мобайл апп-д)

Base URL: `/api/v1`. Auth: `Authorization: Bearer <token>`.

### Нээлттэй

| Method | Path | Тайлбар |
|---|---|---|
| POST | `/auth/register` | Бүртгэл эхлүүлэх (баталгаажуулалт үүсгэнэ) |
| GET | `/auth/verifications/{uuid}` | Баталгаажуулалтын төлөв poll хийх (≥3s) |
| POST | `/auth/login` | `{phone, password, device_name?}` → token |
| POST | `/auth/reset` / `/auth/reset/confirm` | Нууц үг сэргээх |
| GET | `/categories`, `/categories/{slug}` | Ангилалууд |
| GET | `/listings` | Хайлт: `q, category, district, featured, sort, page, per_page` |
| GET | `/listings/featured` | Онцлох байгууллагууд |
| GET | `/listings/{slug}` | Дэлгэрэнгүй (үзэлт +1) |
| GET | `/plans` | Онцлох байршуулалтын тарифууд |

### Нэвтэрсэн хэрэглэгч

| Method | Path | Тайлбар |
|---|---|---|
| GET | `/me` · PUT `/me` · PUT `/me/password` · POST `/auth/logout` | Профайл |
| GET/POST | `/my/listings` | Өөрийн бизнесүүд / шинээр нэмэх (multipart: `logo`, `cover`) |
| GET/POST/DELETE | `/my/listings/{id}` | Харах / засах (POST multipart) / устгах |
| POST | `/my/listings/{id}/images` | Галерей зураг нэмэх (`images[]`, макс 10) |
| DELETE | `/my/listings/{id}/images/{imageId}` | Зураг устгах |
| POST | `/listings/{id}/reviews` | Үнэлгээ өгөх/шинэчлэх `{rating: 1-5, comment?}` |
| DELETE | `/listings/{id}/reviews` | Өөрийн үнэлгээг устгах |
| POST | `/listings/{id}/favorite` | Хадгалах toggle |
| GET | `/favorites` | Хадгалсан жагсаалт |
| POST | `/my/listings/{id}/feature` | Онцлох болгох нэхэмжлэх үүсгэх `{plan}` |
| GET | `/payments` · `/payments/{id}` | Төлбөрийн түүх / төлөв |

## Тест

```bash
php artisan test    # 23 тест: auth урсгал (verify.mn mock), byl webhook (гарын үсэг, idempotency), listing CRUD, эрхийн шалгалт
```

## Демо өгөгдөл

`php artisan db:seed` нь 12 ангилал, (local орчинд) 10 жишээ байгууллага, үнэлгээнүүд үүсгэнэ.
Демо эзэмшигч: утас `99000000`, нууц үг `password123`.
