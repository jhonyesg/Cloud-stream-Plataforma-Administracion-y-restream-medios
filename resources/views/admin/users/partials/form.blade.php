@php
    $isEdit = isset($user) && $user->exists;
    $ownerOptions = \App\Models\User::orderBy('display_name')->orderBy('username')->limit(200)->get();

    $ownerList = $ownerOptions->mapWithKeys(fn ($u) => [$u->id => ($u->display_name ?? $u->username) . ' (' . $u->email . ')'])->all();
    $roleOptions = ['admin' => 'admin', 'client' => 'client'];
    $statusOptions = ['active' => 'active', 'suspended' => 'suspended'];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <x-form-input label="Username" name="username" :required="true" maxlength="60"
                  :value="$user->username ?? ''" color="indigo" />

    <x-form-input type="email" label="Email" name="email" :required="true"
                  :value="$user->email ?? ''" color="indigo" />

    <x-form-input label="Nombre a mostrar" name="display_name" maxlength="120"
                  :value="$user->display_name ?? ''" color="indigo" />

    <div>
        <x-password-input
            name="password"
            :label="$isEdit ? 'Contraseña (vacía para no cambiar)' : 'Contraseña'"
            :required="!$isEdit"
            autocomplete="new-password"
            color="indigo"
        />
    </div>

    <x-form-input type="select" label="Rol" name="role" :required="true"
                  :options="$roleOptions" :value="$user->role ?? 'client'" color="indigo" />

    <x-form-input type="select" label="Estado" name="status" :required="true"
                  :options="$statusOptions" :value="$user->status ?? 'active'" color="indigo" />

    <div class="sm:col-span-2">
        <x-form-input type="select" label="Owner (opcional)" name="owner_id"
                      :options="$ownerList" :value="$user->owner_id ?? ''"
                      placeholder="— Sin owner —" color="indigo" />
    </div>
</div>