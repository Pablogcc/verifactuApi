<?php

namespace App\Services;

use Carbon\Carbon;

class DateTimeServer
{
    public function dateTimeService()
    {
        $now = Carbon::now();

        return [
            'fecha' => $now->toDateString(),
            'hora' => $now->toTimeString(),
            'zona_horaria' => $now->format('P'),
        ];
    }
}
