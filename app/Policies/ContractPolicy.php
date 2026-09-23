<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Support\Permissions;

class ContractPolicy
{
    /**
     * Determine whether the user can view any contracts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActiveApplicationUser();
    }

    /**
     * Determine whether the user can view the contract.
     */
    public function view(User $user, Contract $contract): bool
    {
        if ($user->isActiveApplicationUser()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can download the contract artifact.
     */
    public function download(User $user, Contract $contract): bool
    {
        return $this->view($user, $contract);
    }

    /**
     * Determine whether the user can create contracts.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can issue the contract.
     */
    public function issue(User $user, Contract $contract): bool
    {
        return Permissions::allows($user, Permissions::ISSUE_CONTRACTS);
    }

    /**
     * Determine whether the user can void the contract.
     */
    public function void(User $user, Contract $contract): bool
    {
        return Permissions::allows($user, Permissions::ISSUE_CONTRACTS);
    }

    /**
     * Determine whether the user can supersede the contract.
     */
    public function supersede(User $user, Contract $contract): bool
    {
        return Permissions::allows($user, Permissions::ISSUE_CONTRACTS);
    }
}
