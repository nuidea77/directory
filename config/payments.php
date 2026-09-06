<?php

/*
 * Зээл, хэсэгчилсэн төлбөрийн аппуудын ЭХНИЙ өгөгдөл (seed).
 *
 * Ажиллах үед жагсаалт нь payment_apps хүснэгтээс уншигдана — админаас
 * нэмэх/засах/лого байршуулах боломжтой. Энэ файл нь зөвхөн шинэ
 * суулгалтад анхны утгыг өгнө (PaymentAppSeeder).
 *
 * Зээл, хэсэгчилсэн төлбөрийн аппууд — салбар бүр аль аппаар нь
 * үйлчилдэгээ сонгоно. Нэр нь салбарын payments талбарт хадгалагдах тул
 * ӨӨРЧЛӨХГҮЙ байх ёстой (өөрчилвөл шүүлтүүр таарахгүй).
 *
 * 'slug'  — логоны файлын нэр ба өнгө таних түлхүүр.
 * Бодит лого: public/img/payments/{slug}.svg (.png/.webp ч болно) —
 * файл байрлуулбал автоматаар харагдана, байхгүй бол брэндийн өнгөтэй
 * түр тэмдэг гарна (resources/js/data/paymentBrands.js).
 *
 * 'wordmark' => true  — лого нь нэрээ агуулсан бол (ж: «Storepay» гэсэн
 * бичигтэй лого) хажууд нь текст нэрийг давхардуулж харуулахгүй.
 */
return [
    // Логог тухайн байгууллагын албан ёсны эх сурвалжаас авна (домэйныг
    // нь аль компани болохыг эргэлзээгүй болгохын тулд бичив).
    // 'color' — лого байхгүй үеийн брэндийн өнгө.
    ['slug' => 'lendmn', 'name' => 'LendMN', 'color' => '#1a7f5a'],       // lend.mn
    ['slug' => 'storepay', 'name' => 'Storepay', 'color' => '#e0342b'],   // storepay.mn
    ['slug' => 'pocket', 'name' => 'Pocket', 'color' => '#7b3fe4'],       // pocket.mn
    ['slug' => 'sono', 'name' => 'Sono', 'color' => '#f0a500'],           // sono.mn
    ['slug' => 'ard', 'name' => 'Ард Апп', 'color' => '#0b63ce'],         // ardapp.mn
    ['slug' => 'toki', 'name' => 'Toki', 'color' => '#111827'],           // toki.mn
    ['slug' => 'hipay', 'name' => 'HiPay', 'color' => '#00a1e0'],         // hipay.mn
    ['slug' => 'monpay', 'name' => 'MonPay', 'color' => '#e11d48'],       // monpay.mn
    ['slug' => 'qpay', 'name' => 'QPay', 'color' => '#0f766e'],           // qpay.mn
    ['slug' => 'socialpay', 'name' => 'SocialPay', 'color' => '#c2185b'], // socialpay.mn
];
