<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Эрхийн бичиг (subscription plans)
    |--------------------------------------------------------------------------
    | Үнэ ₮ (MNT), НӨАТ орсон. byl.mn-ээр төлөгдөнө.
    */
    'plans' => [
        'free' => [
            'name' => 'Үнэгүй',
            'price' => 0,
            'term_years' => 1,
            'limits' => [
                'businesses' => 1,
                'branches' => 1, // зөвхөн 1 (үндсэн) хаяг — салбар нэмэх боломжгүй
                'images_per_branch' => 1,
            ],
            'analytics' => false,
            'top_list' => false,
            'verified_badge' => false,
        ],
        'standard' => [
            'name' => 'Стандарт',
            'price' => 120000,
            'price_monthly' => 15000,
            'term_years' => 1,
            'limits' => [
                'businesses' => 1,
                'branches' => 0, // 0 = хязгааргүй
                'images_per_branch' => 5,
            ],
            'analytics' => true,
            'top_list' => false,
            'verified_badge' => false,
        ],
        'business' => [
            'name' => 'Бизнес',
            'price' => 290000,
            'price_monthly' => 35000,
            'term_years' => 2,
            'limits' => [
                'businesses' => 5,
                'branches' => 0, // хязгааргүй
                'images_per_branch' => 20,
            ],
            'analytics' => true,
            'top_list' => true,
            'verified_badge' => true,
        ],
    ],

    // Салбар бүрийн нэмэлт төлбөр (эрхийн хугацаанд, эхний салбар үнэгүй)
    'branch_addon_price' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Онцлох байршил (сурталчилгааны бүтээгдэхүүн)
    |--------------------------------------------------------------------------
    */
    'ads' => [
        'category_featured' => [
            'name' => 'Ангиллын онцлох',
            'slots' => 3, // ангилал + дүүрэг тус бүрт зэрэг 3 онцлох
            'prices' => [7 => 44000, 14 => 79000, 30 => 149000],
            'business_plan_discount' => 0.10, // Бизнес эрхтэйд −10%
        ],
        'home_featured' => [
            'name' => 'Нүүр хуудасны онцлох',
            'slots' => 6, // хот бүрт 6 зай
            'prices' => [7 => 74000, 14 => 134000, 30 => 249000],
            'business_plan_discount' => 0.0,
        ],
        'keyword' => [
            'name' => 'Хайлтын үг',
            'slots' => 3, // үг тутамд 3 зай
            'prices' => [7 => 26000, 14 => 47000, 30 => 89000],
            'business_plan_discount' => 0.0,
        ],
    ],

];
