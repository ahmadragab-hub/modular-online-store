<?php

namespace App\Domains\Customers\Queries;

use App\Domains\Customers\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListCustomersForAdminQuery
{
    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function execute(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::query()
            ->with('user')
            ->orderByDesc('updated_at');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.addcslashes(strtolower(trim($search)), '%_\\').'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(email) like ?', [$term])
                    ->orWhereRaw('LOWER(first_name) like ?', [$term])
                    ->orWhereRaw('LOWER(last_name) like ?', [$term]);
            });
        }

        return $query->paginate($perPage);
    }
}
