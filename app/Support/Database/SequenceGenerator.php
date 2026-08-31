<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SequenceGenerator
{
    public function next(string $name): int
    {
        if (blank($name)) {
            throw new InvalidArgumentException('A sequence name is required.');
        }

        return DB::transaction(function () use ($name): int {
            $now = now();

            DB::table('sequences')->insertOrIgnore([
                'name' => $name,
                'value' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $current = DB::table('sequences')
                ->where('name', $name)
                ->lockForUpdate()
                ->value('value');

            $next = (int) $current + 1;

            DB::table('sequences')
                ->where('name', $name)
                ->update([
                    'value' => $next,
                    'updated_at' => $now,
                ]);

            return $next;
        }, attempts: 5);
    }
}
