# Ойрхон.mn — Монголын бизнес лавлах

Хайлт-төвтэй бизнес лавлах: Laravel 13 API + Vue 3 SPA + Tailwind CSS 4,
verify.mn (MO SMS) баталгаажуулалт, byl.mn төлбөр.

## Бүтээгдэхүүний тойм

**Нийтийн хэсэг**
- 🔍 **Ухаалаг хайлт**: кирилл/латин галиг, үсгийн алдаа, ярианы нэр —
  «шүдний эмнэлэг», «shudnii emneleg», «shudni emnelg», «shudnii emneleg bayanzurh»
  бүгд ижил илэрц өгнө. Хайлтын мөрөөс ангилал, дүүргийг таньж шүүлтүүрт буулгана
- 📂 **3 түвшний ангилал** (26 үндсэн · 323 нийт): Боловсрол → Хэлний сургалт → Англи хэл.
  Нэг бизнес олон ангилалд бүртгүүлж болно (макс 5)
- 🧰 **Ангилалд тохирсон үйлчилгээ/онцлог**: зочид буудалд 24 цагийн ресепшн, CCTV,
  цахилгаан шат, сауна…; PC тоглоомын газарт VIP өрөө, буфет, тоног төхөөрөмж…
- 💳 **Зээлийн апп**: LendMN, Storepay, Pocket, Sono, Ард Апп, Toki, HiPay, MonPay, QPay, SocialPay
- 🏢 Бизнесийн дэлгэрэнгүй: салбар сонгогч, галерей, цагийн хуваарь (24/7-г хуваариас өөрөө тооцно),
  салбар тус бүрийн сэтгэгдэл, ижил төрлийн бизнес
- 📍 Газрын зургийн горим + «Миний ойролцоо» (радиусаар шүүж, зайгаар эрэмбэлнэ)
- 💬 Хэрэглэгч ↔ бизнесийн зурвас, хадгалсан жагсаалт, залруулга илгээх
- 📱 Гар утсанд доод таб цэс (Нүүр · Ангилал · Хайх · Хадгалсан · Профайл)

**Бизнес эзэн («Бизнес зөвлөл»)**
- Байгууллага → бизнес → салбар бүтэц: нэр/лого/ангилал байгууллагад, хаяг/утас/цаг/зураг салбарт
- 3 шаттай бүртгэл: мэдээлэл → салбарууд (газрын зурган дээр байршлаа тавих) → verify.mn
- Дашбоард: салбаруудын KPI, статистик (хандалт/залгалт график, «хэрхэн олсон»),
  сэтгэгдэлд хариулах, нэхэмжлэх, тохиргоо
- Салбар засах: бүрэн байдлын checklist, зураг (эрхийн хязгаартай), хаяг өөрчлөгдвөл дахин хяналт

**Эрхийн бичиг ба сурталчилгаа**
- Үнэгүй (1 бизнес · 1 зураг · салбаргүй) / Стандарт ₮120,000/жил буюу ₮15,000/сар /
  Бизнес ₮290,000/2 жил буюу ₮35,000/сар (5 бизнес, ТОП жагсаалт, ✓ тэмдэг)
- Салбарын нэмэлт: салбар бүрд +₮5,000 (эхний салбар үнэгүй)
- Ангиллын онцлох: ангилал+дүүрэг тус бүрт 3 зай (₮44k/79k/149k — 7/14/30 хоног), Бизнес эрхтэйд −10%
- Нүүрийн онцлох: хот бүрт 6 зай (₮74k/134k/249k)
- Зай дүүрсэн үед дараалалд орж, суларвал FIFO-оор автоматаар идэвхжинэ
- Промо код: эрх болон зарын аль нэгэнд, хувь/дүнгээр

**Админ**
- Модерацын дараалал (шинэ салбар батлах/татгалзах), дата чанарын үзүүлэлт
- Ангиллын 3 түвшний CRUD, эрхийн бичиг CRUD, промо код CRUD, зарын жагсаалт
- Орлогын тайлан: эрх/зарын орлого, эрхийн тархалт, онцлох зайн инвентор

## Технологи

| Давхарга | Хэрэгсэл |
|---|---|
| Backend | Laravel 13, PHP 8.4, Sanctum (API token) |
| Frontend | Vue 3 SPA, Vue Router, Pinia, Tailwind CSS 4, Vite, lucide icons |
| Өгөгдлийн сан | MySQL (production) · SQLite (dev/test) |
| Хайлт | Өөрийн индекс (`branches.search_text`) + галиг/fuzzy/синоним (`search_aliases`) |
| SMS баталгаажуулалт | [verify.mn](https://verify.mn) — MO SMS |
| Төлбөр | [byl.mn](https://byl.mn) — Checkout API + HMAC webhook |

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

Хайлтын индексийг дахин үүсгэх (ангилал/синоним өөрчлөгдсөний дараа):

```bash
php artisan search:reindex
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

**Хайлт v1 (галиг + fuzzy + синоним)**
1. `App\Support\SearchText::fold()` — кирилл/латиныг нэг канон түлхүүрт буулгана
   (`kh→h`, `ts→c`, `y→i`, давхар үсэг хураана): «Шүдний эмнэлэг» ба «shudnii emneleg» → `shudni emneleg`
2. `SearchIndexer` — салбарын нэр, хаяг, дүүрэг, ангилал (эцгүүд нь хамт), синоним, зээлийн аппыг
   `branches.search_text`-д бэлдэнэ; салбар/бизнес хадгалах бүрд өөрөө шинэчлэгдэнэ
3. `SearchQuery::parse()` — 3→2→1 үгийн цонхоор байршил, ангилал, үлдсэн үгийг ялгаж,
   Levenshtein-ээр үсгийн алдааг залруулна
4. `search_aliases` — ангилал бүрийн ярианы нэр («тог» → Цахилгаанчин, «шил хийх» → Цонх, хаалга)
5. Илэрцийн дээр «Ойлгосон нь: [Шүдний эмнэлэг] [Баянзүрх]» гэж хэрхэн ойлгосноо харуулна

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
| Лавлах | `GET home`, `search` (q, category, district, price, rating, open_now, open_24_7, verified, amenity, payment, lat/lng/radius, sort), `categories`, `categories/{slug}`, `businesses/{slug}`, `locations`, `amenities?category=slug`, `pricing`, `POST branches/{id}/event` |
| Хэрэглэгч | `GET favorites`, `POST businesses/{id}/favorite`, `GET my/reviews`, `POST/DELETE branches/{id}/reviews`, `POST …/reviews/{id}/report`, `POST reviews/{id}/helpful`, `POST branches/{id}/corrections` |
| Бизнес зөвлөл | `GET/POST console/organizations`, `PUT console/organizations/{id}`, `POST console/businesses/{id}` (multipart), салбарын CRUD + зураг, `GET …/stats`, `…/reviews` + `reply` |
| Төлбөр | `POST checkout`, `GET orders`, `orders/{id}`, `GET slots`, `GET console/organizations/{id}/campaigns` |
| Админ | `GET admin/moderation`, `POST admin/branches/{id}/approve\|reject`, `GET admin/revenue`, `admin/businesses`, `admin/categories` CRUD, `admin/plans` CRUD, `admin/promo-codes` CRUD, `admin/reviews` + `moderate`, `admin/corrections` + `moderate` |

## Тест

```bash
php artisan test   # 114 тест: хайлт (галиг/fuzzy/синоним), ангилалын amenity, зээлийн апп,
                   # verify.mn урсгал (mock, expired/401), byl checkout + webhook, brute-force түгжээ,
                   # салбарын нэмэлт, зайн дараалал/promote, ангиллын мод, промо код, scheduler
```

## Production deploy

```bash
composer deploy          # install --no-dev, migrate, storage:link, optimize, queue:restart
npm ci && npm run build
php artisan search:reindex
```

Заавал тохируулах зүйлс:

1. **Cron** — scheduler-гүйгээр зарын дуусгалт/сунгалт, захиалгын цэвэрлэгээ ажиллахгүй:
   ```
   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
   ```
2. **Queue worker** — мэдэгдлүүд (и-мэйл) queue-гээр илгээгддэг:
   ```
   php artisan queue:work --tries=3   # supervisor/systemd-ээр байнга ажиллуулна
   ```
3. **byl.mn dashboard** — webhook URL: `https://тань-домэйн/webhooks/byl`, event: `checkout.completed`
4. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Ulaanbaatar`, `APP_LOCALE=mn`,
   `VERIFY_MN_API_KEY`, `BYL_API_TOKEN/PROJECT_ID/WEBHOOK_SECRET`, `CORS_ALLOWED_ORIGINS`
   — verify.mn/byl түлхүүр дутуу бол production дээр бүртгэл/төлбөр АЖИЛЛАХГҮЙ (fail-closed)
5. И-мэйл мэдэгдэлд `MAIL_*` тохиргоо (одоо log driver)

## Тохируулах жагсаалтууд (config)

| Файл | Агуулга |
|---|---|
| `config/locations.php` | Нийслэл + 21 аймаг, дүүрэг/сумдтайгаа |
| `config/amenities.php` | Үйлчилгээ/онцлог — `common` + ангиллын slug тус бүрийн багц (нэр → lucide icon) |
| `config/payments.php` | Зээл, хэсэгчилсэн төлбөрийн аппууд |
| `config/billing.php` | Эрхийн бичиг, салбарын нэмэлт, зарын үнэ/зай |
| `database/seeders/SearchAliasSeeder.php` | Ангиллын ярианы нэр (синоним) |

Ангиллын amenity нэрийг өөрчилвөл хуучин салбаруудын хадгалсан утга таарахаа болино —
шинэ нэр нэмэх нь аюулгүй, байгаа нэрийг засах бол өгөгдлийг хамт шилжүүлнэ.
Шинэ icon нэмбэл `resources/js/data/amenityIcons.js`-д бүртгэнэ.

## Тэмдэглэл

- Дизайн дээрх verify.mn-ийн «1414» дугаар нь mockup — бодит integration нь verify.mn API-аас
  ирсэн жинхэнэ shortcode/кодыг харуулна.
- Зээлийн аппуудын лого нь тухайн компанийн өмч тул зурган лого ашиглаагүй —
  брэндийн өнгө + товчлол харуулна.
- Зураглалууд OpenStreetMap (Leaflet), «Зам заах» нь Google Maps руу гардаг.
