<?php

/*
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
    // нь аль компани болохыг эргэлзээгүй болгохын тулд бичив)
    ['slug' => 'lendmn', 'name' => 'LendMN'],       // lend.mn
    ['slug' => 'storepay', 'name' => 'Storepay'],   // storepay.mn
    ['slug' => 'pocket', 'name' => 'Pocket'],       // pocket.mn
    ['slug' => 'sono', 'name' => 'Sono'],           // sono.mn
    ['slug' => 'ard', 'name' => 'Ард Апп'],         // ardapp.mn
    ['slug' => 'toki', 'name' => 'Toki'],           // toki.mn
    ['slug' => 'hipay', 'name' => 'HiPay'],         // hipay.mn
    ['slug' => 'monpay', 'name' => 'MonPay'],       // monpay.mn
    ['slug' => 'qpay', 'name' => 'QPay'],           // qpay.mn
    ['slug' => 'socialpay', 'name' => 'SocialPay'], // socialpay.mn
];
