<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

class SequenceGenerator
{
    public function next(string $name): int
    {
        return $this->reserve($name, 1)[0];
    }

    /** @return list<int> */
    public function reserve(string $name, int $count): array
    {
        if (blank($name)) {
            throw new InvalidArgumentException((string) __('A sequence name is required.'));
        }

        if ($count < 1) {
            throw new InvalidArgumentException((string) __('A positive sequence range is required.'));
        }

        return DB::transaction(function () use ($count, $name): array {
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

            if (! is_int($current) && (! is_string($current) || ! ctype_digit($current))) {
                throw new UnexpectedValueException('Stored sequence value must be a non-negative integer.');
            }

            $currentValue = is_int($current) ? $current : (int) $current;
            $first = $currentValue + 1;
            $last = $currentValue + $count;

            DB::table('sequences')
                ->where('name', $name)
                ->update([
                    'value' => $last,
                    'updated_at' => $now,
                ]);

            return range($first, $last);
        }, attempts: 5);
    }
}
