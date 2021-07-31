<?php

declare(strict_types=1);

namespace App\Service;

class TestService
{
    public function getInfoById(int $id): array
    {
        return [
            'id' => $id,
            'name' => '名字',
        ];
    }
}
