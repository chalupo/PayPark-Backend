<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_entry_creates_an_active_access_with_server_time(): void
    {
        $this->travelTo(now()->startOfSecond());
        $vehicle = $this->createVehicle();

        $this->postJson('/api/accesses/entry', [
            'vehicle_id' => $vehicle->id,
            'entry_at' => '2000-01-01',
            'status' => 'closed',
        ])->assertCreated()
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.exit_at', null);

        $access = Access::sole();
        $this->assertTrue($access->entry_at->equalTo(now()));
        $this->assertDatabaseHas('accesses', [
            'vehicle_id' => $vehicle->id, 'status' => 'active', 'exit_at' => null,
        ]);
    }

    public function test_availability_decreases_after_entry(): void
    {
        $this->getJson('/api/availability')->assertExactJson([
            'capacity' => 50, 'occupied' => 0, 'available' => 50,
        ]);

        $this->postJson('/api/accesses/entry', ['vehicle_id' => $this->createVehicle()->id])
            ->assertCreated();

        $this->getJson('/api/availability')->assertExactJson([
            'capacity' => 50, 'occupied' => 1, 'available' => 49,
        ]);
    }

    public function test_duplicate_active_entry_is_rejected(): void
    {
        $vehicle = $this->createVehicle();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])->assertCreated();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])
            ->assertStatus(409)->assertJsonPath('message', 'El vehículo ya tiene una entrada activa.');

        $this->assertDatabaseCount('accesses', 1);
    }

    public function test_exit_closes_the_active_access_and_records_server_time(): void
    {
        $vehicle = $this->createVehicle();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])->assertCreated();
        $this->travel(1)->hours();
        $this->travelTo(now()->startOfSecond());

        $this->postJson('/api/accesses/'.$vehicle->id.'/exit')
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $access = Access::sole();
        $this->assertTrue($access->exit_at->equalTo(now()));
        $this->assertTrue($access->exit_at->greaterThan($access->entry_at));
        $this->assertDatabaseHas('accesses', ['id' => $access->id, 'status' => 'closed']);
    }

    public function test_availability_increases_after_exit(): void
    {
        $vehicle = $this->createVehicle();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])->assertCreated();
        $this->postJson('/api/accesses/'.$vehicle->id.'/exit')->assertOk();

        $this->getJson('/api/availability')->assertExactJson([
            'capacity' => 50, 'occupied' => 0, 'available' => 50,
        ]);
    }

    public function test_exit_without_active_entry_is_rejected(): void
    {
        $vehicle = $this->createVehicle();
        $this->postJson('/api/accesses/'.$vehicle->id.'/exit')
            ->assertStatus(409)->assertJsonPath('message', 'El vehículo no tiene una entrada activa.');

        $this->assertDatabaseCount('accesses', 0);
    }

    public function test_full_parking_lot_rejects_entry_and_admits_one_after_exit(): void
    {
        $vehicles = [];
        for ($i = 0; $i < 50; $i++) {
            $vehicle = $this->createVehicle();
            $vehicles[] = $vehicle;
            $vehicle->accesses()->create(['entry_at' => now(), 'status' => 'active']);
        }
        $waitingVehicle = $this->createVehicle();

        $this->getJson('/api/availability')->assertExactJson([
            'capacity' => 50, 'occupied' => 50, 'available' => 0,
        ]);
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $waitingVehicle->id])
            ->assertStatus(409)->assertJsonPath('message', 'El estacionamiento está lleno.');
        $this->assertDatabaseCount('accesses', 50);

        $this->postJson('/api/accesses/'.$vehicles[0]->id.'/exit')->assertOk();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $waitingVehicle->id])->assertCreated();
        $this->assertSame(50, Access::where('status', 'active')->count());
    }

    public function test_availability_counts_only_active_accesses(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createVehicle()->accesses()->create(['entry_at' => now(), 'status' => 'active']);
        }
        $this->createVehicle()->accesses()->create([
            'entry_at' => now()->subHour(), 'exit_at' => now(), 'status' => 'closed',
        ]);

        $this->getJson('/api/availability')->assertOk()->assertExactJson([
            'capacity' => 50, 'occupied' => 3, 'available' => 47,
        ]);
    }

    public function test_entry_requires_an_existing_vehicle(): void
    {
        $this->postJson('/api/accesses/entry', [])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
        $this->postJson('/api/accesses/entry', ['vehicle_id' => 9999])->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
        $this->postJson('/api/accesses/9999/exit')->assertNotFound();
        $this->assertDatabaseCount('accesses', 0);
    }

    public function test_accesses_can_be_listed_and_retrieved(): void
    {
        $access = $this->createVehicle()->accesses()->create(['entry_at' => now()]);

        $this->getJson('/api/accesses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $access->id);
        $this->getJson('/api/accesses/'.$access->id)->assertOk()
            ->assertJsonPath('data.id', $access->id);
        $this->getJson('/api/accesses/9999')->assertNotFound();
    }

    public function test_repeated_exit_is_rejected_and_vehicle_can_enter_again(): void
    {
        $vehicle = $this->createVehicle();
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])->assertCreated();
        $this->postJson('/api/accesses/'.$vehicle->id.'/exit')->assertOk();
        $closedAt = Access::sole()->exit_at;
        $this->postJson('/api/accesses/'.$vehicle->id.'/exit')->assertStatus(409);
        $this->assertTrue(Access::sole()->exit_at->equalTo($closedAt));
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicle->id])->assertCreated();
        $this->assertDatabaseCount('accesses', 2);
        $this->assertSame(1, $vehicle->accesses()->where('status', 'active')->count());
    }

    private function createVehicle(): Vehicle
    {
        return User::factory()->create()->vehicles()->create([
            'plate' => 'TEST-'.(Vehicle::count() + 1),
            'brand' => 'Nissan', 'model' => 'Versa', 'color' => 'Azul',
        ]);
    }
}
