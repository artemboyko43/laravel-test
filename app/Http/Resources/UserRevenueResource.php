<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRevenueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->id,
            'email' => $this->email,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_revenue' => (float) ($this->total_revenue ?? 0),
        ];
    }
}
