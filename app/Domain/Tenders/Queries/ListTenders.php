<?php

namespace App\Domain\Tenders\Queries;

use App\Domain\Tenders\Models\Tender;

class ListTenders
{
    public function execute(array $filters)
    {
        $query = Tender::query();
        foreach (['status', 'type'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['q'])) {
            $query->where('title', 'ilike', '%'.$filters['q'].'%');
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 20)->withQueryString();
    }
}
