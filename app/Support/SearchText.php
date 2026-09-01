<?php

namespace App\Support;

/**
 * Хайлтын текстийн нэгдсэн боловсруулалт.
 *
 * Хэрэглэгч «шүдний эмнэлэг», «shudnii emneleg», «shudni emnelg» гэж
 * бүгдийг бичдэг. Тиймээс кирилл ба латин бичлэгийг НЭГ ижил түлхүүр рүү
 * хөрвүүлээд (key) индекс ба хайлт хоёуланд нь ашиглана.
 */
class SearchText
{
    /** Кирилл → латин (албан бус, өдөр тутмын бичлэгт ойр) */
    private const CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'ye', 'ё' => 'yo',
        'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'ө' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ү' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh',
        'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /**
     * Латин бичлэгийн олон хувилбарыг нэг хэлбэрт оруулна.
     * kh→h, ts→c, y→i, давхар үсэг → ганц (uu→u, ээ→e гэх мэт).
     */
    public static function fold(string $value): string
    {
        $s = mb_strtolower(trim($value));

        // Кириллийг латин болгоно (латин байвал хэвээрээ)
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            $out .= self::CYRILLIC[$ch] ?? $ch;
        }

        // Ижил дуудлагатай бичлэгүүдийг нэгтгэнэ
        $out = strtr($out, ['kh' => 'h', 'ts' => 'c', 'x' => 'h', 'w' => 'v', 'q' => 'k', 'y' => 'i']);

        // Үсэг, тоо, зайнаас бусдыг зайгаар солино
        $out = preg_replace('/[^a-z0-9]+/u', ' ', $out) ?? '';

        // Давхар үсгийг ганц болгоно: «shudnii» ба «shudni» ижил түлхүүртэй
        $out = preg_replace('/(.)\1+/u', '$1', $out) ?? '';

        return trim(preg_replace('/\s+/', ' ', $out) ?? '');
    }

    /**
     * Хайлтын үгс (2 ба түүнээс дээш үсэгтэй).
     *
     * @return array<int, string>
     */
    public static function tokens(string $value): array
    {
        $tokens = array_filter(
            explode(' ', self::fold($value)),
            fn (string $t) => mb_strlen($t) >= 2,
        );

        return array_values(array_unique($tokens));
    }

    /**
     * Хоёр үгийн ойролцоо эсэх (үсгийн алдаа тэвчих).
     */
    public static function isClose(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $len = min(strlen($a), strlen($b));

        if ($len < 4) {
            return false;
        }

        // Урт үгэнд 2 хүртэл, богинод 1 үсгийн зөрүү
        $allowed = $len >= 7 ? 2 : 1;

        return levenshtein($a, $b) <= $allowed;
    }
}
