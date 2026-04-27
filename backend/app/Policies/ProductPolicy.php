<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * View product (for inventory and stock history)
     */
    public function view(User $user, Product $product): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    /**
     * Create product
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    /**
     * Update product
     */
    public function update(User $user, Product $product): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    /**
     * Delete product
     */
    public function delete(User $user, Product $product): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

}