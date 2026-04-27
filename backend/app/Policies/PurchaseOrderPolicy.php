<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function receiveGoods(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }

    public function recordPayment(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function export(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return in_array($user->role, ['owner', 'manager', 'cashier']);
    }
}