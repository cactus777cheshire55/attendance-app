<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $isShow = $this->showDetails ?? false;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => UserResource::make($this->whenLoaded('user')),
            'date' => $this->date?->format('Y-m-d'),
            'clock_in' => $this->clock_in?->format('H:i:s'),
            'clock_out' => $this->clock_out?->format('H:i:s'),
            'total_time' => $this->formatted_total_time,
            'total_break_time' => $this->formatted_total_break_time,
            'comment' => $this->comment,

            'breaks' => $this->when(
                $isShow,
                fn () => BreakTimeResource::collection($this->breakTimes)
            ),

            'applications' => $this->when(
                $isShow,
                fn () => ApplicationResource::collection($this->applications)
            ),
        ];
    }
}
