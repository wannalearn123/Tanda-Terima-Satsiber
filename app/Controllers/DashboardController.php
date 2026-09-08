<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Models\Pengiriman;
use App\Services\Validator;
use PDO;

final class DashboardController
{
    public static function index(PDO $pdo, array $query): array
    {
        $from = Validator::dateOpt($query['from'] ?? null);
        $to = Validator::dateOpt($query['to'] ?? null);
        $q = mb_substr(trim((string) ($query['q'] ?? '')), 0, 100);
        $page = ctype_digit((string) ($query['page'] ?? '1')) ? (int) $query['page'] : 1;

        $res = Pengiriman::paginate($pdo, $from, $to, $q === '' ? null : $q, $page, Config::perPage());
        $res['from'] = $from ?? '';
        $res['to'] = $to ?? '';
        $res['q'] = $q;
        return $res;
    }
}
