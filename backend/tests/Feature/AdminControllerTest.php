<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_update_another_administrator(): void
    {
        $admin = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create([
            'cpf' => '12345678901',
            'password' => 'senha-anterior',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users'))
            ->put(route('admin.users.update', $managedAdmin), [
                'name' => 'Administradora Atualizada',
                'cpf' => '987.654.321-00',
                'email' => 'admin.atualizada@example.test',
                'status' => 'blocked',
                'password' => 'NovaSenha@123',
                'password_confirmation' => 'NovaSenha@123',
                'role' => 'client',
                'form_context' => 'edit_admin_'.$managedAdmin->id,
            ]);

        $response->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', 'Cadastro do administrador atualizado.');
        $managedAdmin->refresh();
        $this->assertSame('Administradora Atualizada', $managedAdmin->name);
        $this->assertSame('98765432100', $managedAdmin->cpf);
        $this->assertSame('admin.atualizada@example.test', $managedAdmin->email);
        $this->assertSame('blocked', $managedAdmin->status);
        $this->assertSame('admin', $managedAdmin->role);
        $this->assertTrue(Hash::check('NovaSenha@123', $managedAdmin->password));
    }

    public function test_admin_can_update_client_without_changing_password_or_role(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create([
            'cpf' => '12345678901',
            'password' => 'senha-existente',
            'status' => 'pending',
        ]);
        $originalPassword = $client->password;

        $response = $this->actingAs($admin)
            ->from(route('admin.clients'))
            ->put(route('admin.clients.update', $client), [
                'name' => 'Cliente Atualizada',
                'cpf' => $client->cpf,
                'email' => $client->email,
                'status' => 'active',
                'password' => '',
                'password_confirmation' => '',
                'role' => 'admin',
                'form_context' => 'edit_client_'.$client->id,
            ]);

        $response->assertRedirect(route('admin.clients'))
            ->assertSessionHas('success', 'Cadastro do cliente atualizado.');
        $client->refresh();
        $this->assertSame('Cliente Atualizada', $client->name);
        $this->assertSame('active', $client->status);
        $this->assertSame('client', $client->role);
        $this->assertSame($originalPassword, $client->password);
    }

    public function test_duplicate_email_is_rejected_without_changing_client(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create(['cpf' => '12345678901']);
        $otherClient = User::factory()->client()->create();
        $originalName = $client->name;

        $response = $this->actingAs($admin)
            ->from(route('admin.clients'))
            ->put(route('admin.clients.update', $client), [
                'name' => 'Nome que não deve ser salvo',
                'cpf' => $client->cpf,
                'email' => $otherClient->email,
                'status' => 'active',
                'form_context' => 'edit_client_'.$client->id,
            ]);

        $response->assertRedirect(route('admin.clients'))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está em uso.']);
        $this->assertSame($originalName, $client->fresh()->name);
    }

    public function test_invalid_cpf_is_rejected_without_clearing_client_cpf(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create(['cpf' => '12345678901']);

        $response = $this->actingAs($admin)
            ->from(route('admin.clients'))
            ->put(route('admin.clients.update', $client), [
                'name' => $client->name,
                'cpf' => 'CPF inválido',
                'email' => $client->email,
                'status' => 'active',
                'form_context' => 'edit_client_'.$client->id,
            ]);

        $response->assertRedirect(route('admin.clients'))
            ->assertSessionHasErrors(['cpf' => 'O CPF deve conter 11 dígitos.']);
        $this->assertSame('12345678901', $client->fresh()->cpf);
    }

    public function test_admin_cannot_block_own_account(): void
    {
        $admin = User::factory()->admin()->create(['cpf' => '12345678901']);

        $response = $this->actingAs($admin)
            ->from(route('admin.users'))
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'cpf' => $admin->cpf,
                'email' => $admin->email,
                'status' => 'blocked',
                'form_context' => 'edit_admin_'.$admin->id,
            ]);

        $response->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors(['status' => 'Você não pode bloquear o próprio acesso administrativo.']);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_client_cannot_update_a_registration(): void
    {
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();

        $response = $this->actingAs($client)->put(route('admin.clients.update', $otherClient), [
            'name' => 'Alteração indevida',
            'cpf' => null,
            'email' => $otherClient->email,
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $this->assertNotSame('Alteração indevida', $otherClient->fresh()->name);
    }

    public function test_administrator_update_route_does_not_accept_a_client_record(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $client), [
            'name' => $client->name,
            'cpf' => null,
            'email' => $client->email,
            'status' => 'active',
        ]);

        $response->assertNotFound();
    }

    public function test_client_update_route_does_not_accept_an_administrator_record(): void
    {
        $admin = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put(route('admin.clients.update', $managedAdmin), [
            'name' => $managedAdmin->name,
            'cpf' => null,
            'email' => $managedAdmin->email,
            'status' => 'active',
        ]);

        $response->assertNotFound();
    }

    public function test_administrator_page_offers_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $managedAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk()
            ->assertSee('Editar administrador')
            ->assertSee(route('admin.users.update', $managedAdmin));
    }

    public function test_client_page_offers_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();

        $response = $this->actingAs($admin)->get(route('admin.clients'));

        $response->assertOk()
            ->assertSee('Editar cliente')
            ->assertSee(route('admin.clients.update', $client));
    }
}
