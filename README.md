# Хаана.mn — Монголын бизнес лавлах

Claude Design дээр гарсан «Mongolia Business Directory» дизайны бүрэн хэрэгжүүлэлт.
Laravel + Vue 3 + Tailwind CSS + MySQL.

## Бүтээгдэхүүний тойм

**Нийтийн хэсэг**
- 🔍 Хайлт-төвтэй нүүр хуудас: 12 ангилал, нүүрийн онцлох (6 зай)
- 📂 Ангиллын хуудас: дэд ангилал, дүүрэг/үнэ/үнэлгээ/онцлог шүүлтүүр, онцлох бизнесүүд дээрээ
- 🏢 Бизнесийн дэлгэрэнгүй: салбар сонгогч, галерей, цагийн хуваарь, салбар тус бүрийн сэтгэгдэл
- 📍 «Миний ойролцоо»: байршил/дүүргээр, радиус шүүлтүүр, зураглал + зайгаар эрэмбэлсэн жагсаалт
- 💬 Хэрэглэгч ↔ бизнесийн зурвас, хадгалсан жагсаалт, тоймчийн зэрэглэл (Lv)

**Бизнес эзэн («Бизнес зөвлөл»)**
- Байгууллага → бизнес → салбар бүтэц: нэр/лого/ангилал байгууллагад, хаяг/утас/цаг/зураг/сэтгэгдэл салбарт
- 3 шаттай бүртгэл: мэдээлэл → салбарууд (цагийн хуваарийн editor) → verify.mn баталгаажуулалт
- Дашбоард: салбаруудын KPI, статистик (хандалт/залгалт график, «хэрхэн олсон»), зурвасын inbox, сэтгэгдэлд хариулах, нэхэмжлэх, тохиргоо
- Салбар засах editor: бүрэн байдлын checklist, зураг (эрхийн хязгаартай), хаяг өөрчлөлт → редакцын хяналт

**Эрхийн бичиг ба сурталчилгаа**
- Эрх: Үнэгүй (1 бизнес · 1 зураг · салбаргүй) / Стандарт ₮120,000/жил / Бизнес ₮290,000/2 жил (5 бизнес, ТОП, ✓ тэмдэг)
- Салбарын нэмэлт: салбар бүрд +₮5,000
- Ангиллын онцлох: ангилал+дүүрэг тус бүрт 3 зай (₮44k/79k/149k — 7/14/30 хоног), Бизнес эрхтэйд −10%
- Нүүрийн онцлох: хот бүрт 6 зай · Хайлтын үгийн онцлох: үг тутамд 3 зай
- Зай дүүрсэн үед дараалалд орж, зай суларвал FIFO-оор автоматаар идэвхжинэ

**Админ**
- Модерацын дараалал (шинэ салбар батлах/татгалзах), дата чанарын үзүүлэлт
- Орлогын тайлан: эрх/сурталчилгааны орлого, эрхийн тархалт, онцлох зайн инвентор

## Технологи

| Давхарга | Хэрэгсэл |
|---|---|
| Backend | Laravel 13, PHP 8.4, Sanctum (API token) |
| Frontend | Vue 3 SPA, Vue Router, Pinia, Tailwind CSS 4, Vite |
| Өгөгдлийн сан | MySQL (production) · SQLite (dev/test) |
| SMS баталгаажуулалт | [verify.mn](https://verify.mn) — MO SMS |
| Төлбөр | [byl.mn](https://byl.mn) — Checkout API (төлсний дараа сайт руу буцаана) + HMAC webhook |

## Суулгах

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate

# .env: MySQL холболтоо тохируулна (DB_DATABASE=directory ...)

php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Демо хэрэглэгчид (local seed): эзэмшигч `99000000` / админ `99000001`, нууц үг `password123`.

Админ хэрэглэгч нэмэх (production дээр ч ажиллана):

```bash
php artisan admin:create 99112233                          # шинэ админ, нууц үг автоматаар үүсч 1 удаа харагдана
php artisan admin:create 99112233 --name="Бат" --password=secret123
php artisan admin:create 99112233 --demote                 # админ эрх буцаах
```

### Гуравдагч үйлчилгээ (.env)

```env
# verify.mn — Developer Console-оос API key
VERIFY_MN_ENABLED=true      # false бол dev горимд SMS-гүйгээр авто баталгаажна
VERIFY_MN_API_KEY=vrf_xxxx

# byl.mn — Dashboard-оос (хоосон бол dev горимд төлбөр шууд батлагдана)
BYL_API_TOKEN=
BYL_PROJECT_ID=
BYL_WEBHOOK_SECRET=
```

byl.mn dashboard дээр webhook URL: `https://тань-домэйн/webhooks/byl`

## Гол урсгалууд

**verify.mn (MO SMS)** — бид SMS илгээдэггүй, хэрэглэгч илгээдэг:
1. Backend `POST api.verify.mn/sessions` → `sessionId`, `smsUri`, `displayInstruction`
2. Хэрэглэгч кодоо verify.mn-ийн богино дугаарт өөрөө илгээнэ (нэг товчтой `sms:` линк)
3. verify.mn callback (`GET /webhooks/verify-mn/{uuid}?token=…`) → албан ёсны төлвийг API-аас давхар шалгана
4. Клиент 3 секунд тутам poll (`GET /api/v1/auth/verifications/{uuid}`)
5. Хэрэглэгддэг газрууд: бүртгэл (заавал), мессежээр нэвтрэх, нууц үг сэргээх

**byl.mn төлбөр** — эрх + салбарын нэмэлт + онцлох нэг захиалгаар:
1. `POST /api/v1/checkout` → order (KH-YYYY-MM-XXXX) + byl checkout → төлбөрийн хуудас руу шууд үсэрнэ
2. Төлсний дараа byl.mn `success_url` (`/orders/{id}/pay?return=success`) руу буцаана; болиход `cancel_url`
3. `POST /webhooks/byl` — `checkout.completed`, `Byl-Signature` (HMAC-SHA256, raw body, constant-time) шалгаад идэвхжүүлнэ
4. Идэвхжүүлэлт idempotent: эрх сунгах, ✓ тэмдэг, онцлох зай эзлэх/дараалалд оруулах
5. Fallback: `GET /api/v1/orders/{id}` төлөв poll (буцаж ирэх мөчид webhook хоцорсон ч барина)

## API v1 (мобайл апп-д бэлэн)

Base: `/api/v1` · Auth: `Authorization: Bearer <token>` (Sanctum)

| Бүлэг | Endpoints |
|---|---|
| Auth | `POST auth/register`, `auth/login`, `auth/login-sms`, `auth/reset`, `auth/reset/confirm`, `GET auth/verifications/{uuid}`, `POST auth/verify/start`, `auth/logout`, `GET/PUT me`, `PUT me/password` |
| Лавлах | `GET home`, `search` (q, category, district, price, rating, open_now, verified, amenity, lat/lng/radius, sort), `categories`, `categories/{slug}`, `businesses/{slug}`, `pricing`, `POST branches/{id}/event` |
| Хэрэглэгч | `GET favorites`, `POST businesses/{id}/favorite`, `GET my/reviews`, `POST/DELETE branches/{id}/reviews`, `GET my/messages`, `GET/POST businesses/{id}/messages` |
| Бизнес зөвлөл | `GET/POST console/organizations`, `PUT console/organizations/{id}`, `POST console/businesses/{id}` (multipart), салбарын CRUD + зураг, `GET …/stats`, `…/reviews` + `reply`, зурвасын inbox |
| Төлбөр | `POST checkout`, `GET orders`, `orders/{id}`, `GET slots`, `GET console/organizations/{id}/campaigns` |
| Админ | `GET admin/moderation`, `POST admin/branches/{id}/approve|reject`, `GET admin/revenue`, `admin/businesses`, `admin/reviews` |

## Тест

```bash
php artisan test   # 37 тест: verify.mn урсгал (mock), byl webhook + идэвхжүүлэлт,
                   # зайн дараалал/promote, хайлт + онцлох эрэмбэ, эрхийн хязгаар, модерац
```

## Тэмдэглэл

- Дизайн дээрх verify.mn-ийн «1414» дугаар нь mockup — бодит integration нь verify.mn API-аас ирсэн жинхэнэ shortcode/кодыг харуулна.
- Хайлтын үгийн онцлох нь дизайнд «аукцион» гэж байгаа ч энэ хувилбарт тогтмол үнэтэй (үг тутамд 3 зай, дараалалтай) хэрэгжсэн.
- Зураглалууд нь дизайны placeholder хэв маягаар (бодит газрын зургийн санг дараа нь холбож болно), «Зам заах» нь Google Maps руу гардаг.
