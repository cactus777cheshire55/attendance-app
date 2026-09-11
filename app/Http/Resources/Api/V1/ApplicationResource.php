<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'requested_date' => $this->requested_date,
            'requested_clock_in' => $this->requested_clock_in,
            'requested_clock_out' => $this->requested_clock_out,
        ];
    }
}
