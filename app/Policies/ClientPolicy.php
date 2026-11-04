<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::ClientsManage->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }
}
