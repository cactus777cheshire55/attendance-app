<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BreakTimeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'break_in' => $this->break_in
                ? Carbon::parse($this->break_in)->format('H:i:s')
                : null,
            'break_out' => $this->break_out
                ? Carbon::parse($this->break_out)->format('H:i:s')
                : null,
        ];
    }
}
