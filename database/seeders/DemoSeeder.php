<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStat;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Демо өгөгдөл — дизайн дээрх жишээ бизнесүүд.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['phone' => '99000000'],
            ['name' => 'Б.Батжаргал', 'password' => 'password123', 'phone_verified_at' => now()],
        );

        User::updateOrCreate(
            ['phone' => '99000001'],
            ['name' => 'Админ', 'password' => 'password123', 'phone_verified_at' => now(), 'is_admin' => true],
        );

        $reviewers = collect([
            ['name' => 'Энхжин Д.', 'phone' => '88000001'],
            ['name' => 'Тэмүүлэн Б.', 'phone' => '88000002'],
            ['name' => 'Болормаа Ц.', 'phone' => '88000003'],
            ['name' => 'Ганбат Н.', 'phone' => '88000004'],
            ['name' => 'Оюунаа Б.', 'phone' => '88000005'],
            ['name' => 'Батсайхан Б.', 'phone' => '88000006'],
        ])->map(fn ($r) => User::updateOrCreate(
            ['phone' => $r['phone']],
            ['name' => $r['name'], 'password' => 'password123', 'phone_verified_at' => now()],
        ));

        // --- Хангай Авто ХХК: Бизнес эрхтэй, 3 салбартай ------------------------
        $hangaiOrg = Organization::updateOrCreate(
            ['name' => 'Хангай Авто ХХК'],
            [
                'owner_id' => $owner->id,
                'plan' => 'business',
                'plan_term_years' => 2,
                'plan_started_at' => now()->subDays(148),
                'plan_expires_at' => now()->subDays(148)->addYears(2),
            ],
        );

        $auto = Category::where('slug', 'auto')->first();

        $hangai = $hangaiOrg->businesses()->updateOrCreate(
            ['slug' => 'hangai-auto'],
            [
                'category_id' => $auto->id,
                'subcategory' => 'Хөдөлгүүрийн засвар',
                'name' => 'Хангай Авто',
                'description' => 'Германы техник хангамжтай, 12 жилийн туршлагатай авто засварын төв. Хөдөлгүүр, явах эд анги, тохируулгын мэргэшсэн баг.',
                'website' => 'hangaiauto.mn',
                'facebook' => 'facebook.com/hangaiauto',
                'price_level' => '₮₮',
                'is_verified' => true,
            ],
        );

        $hours = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [
            $d => match ($d) {
                'sat' => ['from' => '10:00', 'to' => '16:00'],
                'sun' => ['closed' => true],
                default => ['from' => '09:00', 'to' => '19:00'],
            },
        ])->all();

        // 24 цагийн салбаруудын хуваарь — is_24_7-г Branch модель өөрөө үүнээс тооцно
        $hours247 = collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [$d => ['from' => '00:00', 'to' => '00:00']])->all();

        $branchData = [
            ['name' => 'Сүхбаатар салбар', 'district' => 'Сүхбаатар', 'khoroo' => '1-р хороо', 'address' => 'Их тойруу 42', 'lat' => 47.9184, 'lng' => 106.9177, 'phone' => '70112233', 'is_main' => true],
            ['name' => 'Баянзүрх салбар', 'district' => 'Баянзүрх', 'khoroo' => '13-р хороо', 'address' => 'Энхтайваны өргөн чөлөө 68, “Гранд” төвийн 1-р давхар', 'lat' => 47.9102, 'lng' => 106.9541, 'phone' => '95001122', 'is_main' => false],
            ['name' => 'Хан-Уул салбар', 'district' => 'Хан-Уул', 'khoroo' => '15-р хороо', 'address' => 'Чингисийн өргөн чөлөө 14', 'lat' => 47.8935, 'lng' => 106.9002, 'phone' => '75009090', 'is_main' => false],
        ];

        foreach ($branchData as $data) {
            $branch = $hangai->branches()->updateOrCreate(
                ['name' => $data['name']],
                [
                    ...$data,
                    'slug' => Str::slug('hangai-auto-'.$data['district']) ?: Str::lower(Str::random(8)),
                    'city' => 'Улаанбаатар',
                    'hours' => $hours,
                    'amenities' => ['Зогсоол', 'Картаар', 'Баталгаат засвар', 'Гэрээт даатгал'],
                    'payments' => ['LendMN', 'Storepay', 'Pocket'],
                    'status' => 'active',
                ],
            );

            $this->seedReviews($branch, $reviewers, random_int(3, 6));
            $this->seedStats($branch);
        }

        // --- Бусад демо бизнесүүд -------------------------------------------------
        $samples = [
            ['org' => 'Модерн Номин ХХК', 'name' => 'Модерн Номин', 'slug' => 'modern-nomin', 'category' => 'restaurants', 'sub' => 'Монгол хоол', 'district' => 'Сүхбаатар', 'address' => 'Их тойруу 42, 2-р давхар', 'phone' => '70112233', 'price' => '₮₮', 'verified' => true,
                'desc' => 'Монгол мяханд шинэ хандлага. 2019 онд Сүхбаатар дүүрэгт нээгдсэн, дотоодын ферм, малчидтай шууд хамтран ажилладаг. Оройн хоолны цагт урьдчилан захиалга авахыг зөвлөж байна.'],
            ['org' => 'Оюу Дент ХХК', 'name' => 'Оюу Дент', 'slug' => 'oyu-dent', 'category' => 'health', 'sub' => 'Шүдний эмнэлэг', 'district' => 'Хан-Уул', 'address' => 'Чингисийн өргөн чөлөө 20', 'phone' => '77004455', 'price' => '₮₮', 'verified' => true,
                'amenities' => ['Урьдчилсан цаг', 'Хүлээх танхим', 'Даатгалаар үйлчилнэ', 'Хүүхдийн эмчтэй', 'Картаар'],
                'desc' => 'Олон улсын стандартын шүдний эмнэлэг. Имплант, цайруулалт, хүүхдийн эмчилгээ.'],
            ['org' => 'Тэрэлж Ложи ХХК', 'name' => 'Тэрэлж Ложи', 'slug' => 'terelj-lodge', 'category' => 'hotels', 'sub' => 'Гэр бааз', 'district' => 'Налайх', 'address' => 'Горхи-Тэрэлж', 'phone' => '99117788', 'price' => '₮₮₮', 'verified' => true,
                'amenities' => ['24 цагийн ресепшн', 'Ресторан', 'Өглөөний цай', 'Сауна', 'Караоке', 'Зогсоол', 'Гэр бүлийн өрөө'],
                'payments' => ['LendMN', 'Ард Апп', 'SocialPay'],
                'desc' => 'Хотоос 60 км. Байгальд хоносон гэрийн бааз, зунжингаа дүүрэн.'],
            ['org' => 'Цагаан Хас ХХК', 'name' => 'Цагаан Хас Хууль', 'slug' => 'tsagaan-khas', 'category' => 'legal', 'sub' => 'Өмгөөлөл', 'district' => 'Чингэлтэй', 'address' => 'Бага тойруу 18', 'phone' => '75759090', 'price' => '₮₮₮', 'verified' => true,
                'desc' => 'Иргэний болон аж ахуйн эрх зүйн мэргэшсэн хуулийн фирм.'],
            // Олон ангилалтай жишээ: гоо сайхан + үсчин + хумсны засал
            ['org' => 'Гоо Студио ХХК', 'name' => 'Гоо Студио 21', 'slug' => 'goo-studio-21', 'category' => 'beauty', 'sub' => 'Гоо сайхны салон', 'district' => 'Баянгол', 'address' => 'Амарсанаагийн гудамж 9', 'phone' => '88003344', 'price' => '₮₮', 'verified' => false,
                'extra_categories' => ['beauty-1', 'beauty-4'],
                'desc' => 'Үс засалт, будалт, хумсны засал, арьс арчилгааны цогц үйлчилгээ.'],
            ['org' => 'Кофе Хаус ХХК', 'name' => 'Кофе Хаус 21', 'slug' => 'coffee-house-21', 'category' => 'restaurants', 'sub' => 'Кафе, бэйкери', 'district' => 'Сүхбаатар', 'address' => 'Олимпын гудамж 12', 'phone' => '88001177', 'price' => '₮₮', 'verified' => true,
                'desc' => 'Ажиллахад тохиромжтой, суудал бүрт залгууртай кафе. Шинэ цэс, үнийн жагсаалттай.'],
            ['org' => 'Хаан Бууз ХХК', 'name' => 'Хаан Буузны газар', 'slug' => 'khaan-buuz', 'category' => 'restaurants', 'sub' => 'Монгол хоол', 'district' => 'Сүхбаатар', 'address' => 'Сеүлийн гудамж 5', 'phone' => '99113322', 'price' => '₮', 'verified' => true, 'open24' => true,
                'desc' => 'Түргэн үйлчилгээтэй үндэсний хоолны газар. Хүргэлт, авч явах үйлчилгээтэй.'],
            ['org' => 'Түмэн Мотор ХХК', 'name' => 'Түмэн Мотор', 'slug' => 'tumen-motor', 'category' => 'auto', 'sub' => 'Авто засвар', 'district' => 'Баянгол', 'address' => 'Амарсанаагийн гудамж 9', 'phone' => '99887711', 'price' => '₮₮', 'verified' => false,
                'desc' => 'Явах эд анги, дугуйн засвар, оношилгоо.'],
            ['org' => 'Урт Цагаан ХХК', 'name' => 'Урт Цагаан', 'slug' => 'urt-tsagaan', 'category' => 'restaurants', 'sub' => 'Монгол хоол', 'district' => 'Чингэлтэй', 'address' => 'Бага тойруу 18', 'phone' => '77884411', 'price' => '₮₮', 'verified' => true,
                'desc' => 'Уламжлалт монгол хоолны ресторан.'],
            ['org' => 'Сүлд Фарм ХХК', 'name' => 'Сүлд Эмийн Сан', 'slug' => 'suld-emiin-san', 'category' => 'health', 'sub' => 'Эмийн сан', 'district' => 'Баянзүрх', 'address' => 'Энхтайваны өргөн чөлөө 55', 'phone' => '70009911', 'price' => '₮', 'verified' => true, 'open24' => true,
                'desc' => '24 цагийн эмийн сан. Жороор олгох эм, анхны тусламжийн хэрэгсэл, эмчийн зөвлөгөө.'],
            // 3 дахь түвшний ангилалд бүртгэлтэй жишээ бизнесүүд
            ['org' => 'Смарт Скул ХХК', 'name' => 'Смарт Скул', 'slug' => 'smart-school', 'category' => 'education-1-1', 'sub' => 'Англи хэл', 'district' => 'Чингэлтэй', 'address' => 'Барилгачдын талбай 3', 'phone' => '70110099', 'price' => '₮₮', 'verified' => false,
                'desc' => 'IELTS, ярианы англи хэлний эрчимжүүлсэн сургалт.'],
            ['org' => 'Сакура Эдьюкейшн ХХК', 'name' => 'Сакура Япон Хэл', 'slug' => 'sakura-japanese', 'category' => 'education-1-2', 'sub' => 'Япон хэл', 'district' => 'Сүхбаатар', 'address' => 'Их сургуулийн гудамж 7', 'phone' => '99012345', 'price' => '₮₮', 'verified' => true,
                'desc' => 'JLPT N5–N1 бэлтгэл, Япон руу суралцахад дэмжлэг үзүүлнэ.'],
            // Хайлтын жишээ: «шүдний эмнэлэг баянзүрх», «тог», «шил хийх»
            ['org' => 'Мишээл Дент ХХК', 'name' => 'Мишээл Дент', 'slug' => 'misheel-dent', 'category' => 'health-1', 'sub' => 'Шүдний эмнэлэг', 'district' => 'Баянзүрх', 'address' => 'Энхтайваны өргөн чөлөө 101', 'phone' => '70113344', 'price' => '₮₮', 'verified' => true,
                'desc' => 'Шүдний эмчилгээ, цэвэрлэгээ, имплант. Оройн 21 цаг хүртэл ажиллана.'],
            ['org' => 'Гэрэл Электрик ХХК', 'name' => 'Гэрэл Электрик', 'slug' => 'gerel-electric', 'category' => 'construction-4', 'sub' => 'Цахилгаанчин', 'district' => 'Баянзүрх', 'address' => 'Их наяд 14', 'phone' => '99223311', 'price' => '₮₮', 'verified' => false,
                'desc' => 'Байр, оффисын цахилгааны угсралт, засвар. Тог татах, самбар солих, гэрэлтүүлэг.'],
            ['org' => 'Ус Тех ХХК', 'name' => 'Ус Тех Сантехник', 'slug' => 'us-tekh', 'category' => 'construction-3', 'sub' => 'Сантехник', 'district' => 'Сонгинохайрхан', 'address' => 'Тольт гудамж 5', 'phone' => '99334422', 'price' => '₮', 'verified' => false,
                'desc' => 'Сантехникийн засвар, ус халаагуур, шугам сүлжээний угсралт.'],
            ['org' => 'Тунгалаг Цонх ХХК', 'name' => 'Тунгалаг Цонх', 'slug' => 'tungalag-tsonkh', 'category' => 'construction-5', 'sub' => 'Цонх, хаалга', 'district' => 'Сонгинохайрхан', 'address' => 'Үйлдвэрийн гудамж 22', 'phone' => '95556677', 'price' => '₮₮', 'verified' => true,
                'desc' => 'Хуванцар цонх, хаалганы захиалга. Шил хийх, цонх солих ажил гүйцэтгэнэ.'],
            ['org' => 'Гэйм Зон ХХК', 'name' => 'Гэйм Зон', 'slug' => 'game-zone', 'category' => 'entertainment-6', 'sub' => 'PC тоглоомын газар', 'district' => 'Баянгол', 'address' => 'Хувьсгалчдын гудамж 8', 'phone' => '80112233', 'price' => '₮', 'verified' => false, 'open24' => true,
                'amenities' => ['VIP өрөө', 'Буфет', 'Өндөр хүчин чадлын тоног', 'Консол тоглоом', 'Чихэвч, гар', 'Зогсоол'],
                'payments' => ['Storepay', 'Pocket', 'QPay'],
                'desc' => 'RTX тоноглолтой 40 суудалтай PC тоглоомын газар. 24 цаг ажиллана.'],
        ];

        foreach ($samples as $sample) {
            $org = Organization::updateOrCreate(
                ['name' => $sample['org']],
                ['owner_id' => $owner->id, 'plan' => 'free'],
            );

            $category = Category::where('slug', $sample['category'])->first();

            $business = $org->businesses()->updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'category_id' => $category->id,
                    'subcategory' => $sample['sub'],
                    'name' => $sample['name'],
                    'description' => $sample['desc'],
                    'price_level' => $sample['price'],
                    'is_verified' => $sample['verified'],
                ],
            );

            // Нэмэлт ангиллууд (нэг бизнес олон ангилалд харагдана)
            $business->syncCategories(
                Category::whereIn('slug', $sample['extra_categories'] ?? [])->pluck('id')->all(),
            );

            $branch = $business->branches()->updateOrCreate(
                ['is_main' => true],
                [
                    'name' => $sample['district'].' салбар',
                    'slug' => $sample['slug'].'-1',
                    'city' => 'Улаанбаатар',
                    'district' => $sample['district'],
                    'khoroo' => random_int(1, 20).'-р хороо',
                    'address' => $sample['address'],
                    'lat' => 47.85 + random_int(0, 900) / 10000,
                    'lng' => 106.85 + random_int(0, 1500) / 10000,
                    'phone' => $sample['phone'],
                    'hours' => ($sample['open24'] ?? false) ? $hours247 : $hours,
                    'amenities' => $sample['amenities'] ?? collect(['Зогсоол', 'Картаар', 'Wi-Fi', 'Англи хэл'])->random(3)->values()->all(),
                    // Зээлийн апп — зарим газар хэсэгчилсэн төлбөр авдаг
                    'payments' => $sample['payments'] ?? collect(['LendMN', 'Storepay', 'Pocket', 'Ард Апп', 'Toki'])->random(random_int(0, 3))->values()->all(),
                    'status' => 'active',
                ],
            );

            $this->seedReviews($branch, $reviewers, random_int(2, 6));
            $this->seedStats($branch);
        }

        // --- Онцлох кампанит ажлууд ------------------------------------------------
        Campaign::updateOrCreate(
            ['organization_id' => $hangaiOrg->id, 'type' => 'category_featured', 'district' => 'Баянзүрх'],
            [
                'business_id' => $hangai->id,
                'category_id' => $auto->id,
                'slot' => 1,
                'days' => 30,
                'price' => 149000,
                'status' => 'active',
                'starts_at' => now()->subDays(19),
                'ends_at' => now()->addDays(11),
                'views_count' => 18420,
                'calls_count' => 312,
            ],
        );

        $nomin = Business::where('slug', 'modern-nomin')->first();

        Campaign::updateOrCreate(
            ['organization_id' => $nomin->organization_id, 'type' => 'home_featured', 'city' => 'Улаанбаатар'],
            [
                'business_id' => $nomin->id,
                'slot' => 1,
                'days' => 14,
                'price' => 134000,
                'status' => 'active',
                'starts_at' => now()->subDays(9),
                'ends_at' => now()->addDays(5),
                'views_count' => 32140,
                'calls_count' => 186,
            ],
        );

        // Хяналт хүлээж буй бүртгэлүүд (админ демо)
        $pendingOrg = Organization::updateOrCreate(
            ['name' => 'Ням Кофе ХХК'],
            ['owner_id' => $owner->id, 'plan' => 'free'],
        );

        $pendingBiz = $pendingOrg->businesses()->updateOrCreate(
            ['slug' => 'nyam-coffee'],
            [
                'category_id' => Category::where('slug', 'restaurants')->first()->id,
                'subcategory' => 'Кафе, бэйкери',
                'name' => 'Ням Кофе',
                'description' => 'Гар аргаар хуурсан кофе.',
                'price_level' => '₮₮',
            ],
        );

        $pendingBiz->branches()->updateOrCreate(
            ['slug' => 'nyam-coffee-1'],
            [
                'name' => 'Сүхбаатар салбар',
                'city' => 'Улаанбаатар',
                'district' => 'Сүхбаатар',
                'address' => 'Сөүлийн гудамж 12',
                'phone' => '88114422',
                'hours' => $hours,
                'status' => 'pending',
            ],
        );

    }

    protected function seedReviews(Branch $branch, $reviewers, int $count): void
    {
        $comments = [
            'Хөдөлгүүрийн доргилтыг нэг өдөрт шийдлээ. Үнэ нь тооцоолсноосоо хямд гарсан.',
            'Үйлчилгээ маш сайн, цагтаа амжуулдаг.',
            'Ажил сайн, харин цаг тохирсноос 40 минут хоцорсон. Урьдчилан мэдэгдвэл сайн.',
            'Цагтаа оруулсан, тайлбар нь тодорхой. Үнэ ч боломжийн.',
            'Хоёр дахь удаагаа үйлчлүүлж байна, сэтгэл хангалуун.',
            'Байршил олоход амархан, зогсоолтой.',
        ];

        foreach ($reviewers->random(min($count, $reviewers->count())) as $reviewer) {
            Review::updateOrCreate(
                ['branch_id' => $branch->id, 'user_id' => $reviewer->id],
                ['rating' => random_int(3, 5), 'comment' => collect($comments)->random(), 'status' => 'active'],
            );
        }

        $branch->refreshRating();
    }

    protected function seedStats(Branch $branch): void
    {
        for ($i = 30; $i >= 0; $i--) {
            $views = random_int(30, 160);
            BranchStat::updateOrCreate(
                // Carbon ашиглана — where/insert хоёулаа ижил datetime форматтай байх
                ['branch_id' => $branch->id, 'date' => now()->subDays($i)->startOfDay()],
                [
                    'views' => $views,
                    'calls' => (int) round($views * random_int(6, 12) / 100),
                    'directions' => (int) round($views * random_int(4, 8) / 100),
                    'views_category' => (int) round($views * 0.48),
                    'views_search' => (int) round($views * 0.24),
                    'views_map' => (int) round($views * 0.18),
                    'views_direct' => (int) round($views * 0.10),
                ],
            );
        }

        $branch->update([
            'views_count' => BranchStat::where('branch_id', $branch->id)->sum('views'),
            'calls_count' => BranchStat::where('branch_id', $branch->id)->sum('calls'),
            'directions_count' => BranchStat::where('branch_id', $branch->id)->sum('directions'),
        ]);
    }
}
