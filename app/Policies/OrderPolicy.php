<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        if ($user->role === 'administrator' || $user->role === 'provider') {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): Response
    {
        if ($user->role === 'administrator' || $user->role === 'provider') {
            return Response::allow();
        } elseif ($user->id === $order->customer->id) {
            return Response::allow();
        }

        return Response::deny('Esta tela exige permissão de administrador.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        if ($user->role === 'administrator' || $user->role === 'provider') {
            return Response::allow();
        }

        return Response::deny('Esta permissão está desabilitada.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): Response
    {
        return Response::deny('Esta permissão está desabilitada.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): Response
    {
        return Response::deny('Não é possível apagar uma ocorrência');
    }

    public function cancel(User $user, Order $order): Response
    {
        if ($order->customer->id === $user->id && $order->status === 'opened') {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function editTag(User $user, Order $order): Response
    {
        if (
            ($order->customer->id === $user->id
                || $order->provider && $order->provider->id === $user->id)
            && $order->status === OrderStatus::inProgress()->value
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function editSolution(User $user, Order $order): Response
    {
        if (
            ($order->provider && $order->provider->id === $user->id)
            && $order->status === OrderStatus::inProgress()->value
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function editService(User $user, Order $order): Response
    {
        $role = request()->session()->get('role');
        if (
            ($order->provider && $order->provider->id === $user->id)
            && $order->status === OrderStatus::inProgress()->value
            && $role != 'cutomer'
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function inProgress(User $user, Order $order): Response
    {

        if (
            ($order->provider && $order->provider->id === $user->id || $order->provider === null)
            &&
            ($order->status === OrderStatus::opened()->value
                || $order->status === OrderStatus::pendingCustomerResponse()->value
                || $order->status === OrderStatus::pendingItResource()->value)
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function close(User $user, Order $order): Response
    {
        if (
            $order->provider && $order->provider->id === $user->id
            && $order->status === OrderStatus::inProgress()->value && $order->solution != null
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function commit(User $user, Order $order): Response
    {
        if (
            $order->customer->id === $user->id && $order->status === 'closed'
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function reserve(User $user, Order $order): Response
    {
        if (
            $order->provider && $order->provider->id === $user->id
            && $order->status === OrderStatus::inProgress()->value
            || $order->status === OrderStatus::reserved()->value && $user->role === 'administrator'
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function pendingCustomer(User $user, Order $order): Response
    {
        if (
            $order->provider && $order->provider->id === $user->id
            && $order->status === OrderStatus::inProgress()->value
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function pendingResource(User $user, Order $order): Response
    {
        if (
            $order->provider && $order->provider->id === $user->id
            && $order->status === OrderStatus::inProgress()->value
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }

    public function requestHelp(User $user, Order $order): Response
    {
        if (
            $order->customer->id === $user->id
            && $order->status === OrderStatus::opened()->value && $order->isLate
            && ! request()->session()->get('helpRequested')
        ) {
            return Response::allow();
        }

        return Response::deny('Esta ocorrência não pode ser cancelada.');
    }
}
