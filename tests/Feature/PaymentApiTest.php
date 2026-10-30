<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_payment_is_registered_and_related_to_its_access(): void
    {
        $this->travelTo(now()->startOfSecond());
        $access = $this->createAccess();

        $this->postJson('/api/payments', ['access_id' => $access->id, 'amount' => '45.50'])
            ->assertCreated()
            ->assertJsonPath('data.access_id', $access->id)
            ->assertJsonPath('data.amount', '45.50')
            ->assertJsonPath('data.status', 'paid');

        $payment = Payment::sole();
        $this->assertTrue($payment->access->is($access));
        $this->assertTrue($access->fresh()->payment->is($payment));
        $this->assertTrue($payment->paid_at->equalTo(now()));
        $this->assertDatabaseHas('payments', ['access_id' => $access->id, 'amount' => 45.50, 'status' => 'paid']);
    }

    public function test_payments_can_be_listed(): void
    {
        $payment = $this->createPayment();

        $this->getJson('/api/payments')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);
    }

    public function test_a_payment_can_be_retrieved(): void
    {
        $payment = $this->createPayment();

        $this->getJson('/api/payments/'.$payment->id)->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.access_id', $payment->access_id);
    }

    public function test_an_unknown_payment_returns_json_not_found(): void
    {
        $this->get('/api/payments/9999')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_payment_requires_an_existing_access(): void
    {
        $this->postJson('/api/payments', ['access_id' => 9999, 'amount' => '20.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('access_id');

        $this->assertDatabaseCount('payments', 0);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected(mixed $amount): void
    {
        $this->postJson('/api/payments', ['access_id' => $this->createAccess()->id, 'amount' => $amount])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public static function invalidAmounts(): array
    {
        return [
            'cero' => [0],
            'negativo' => [-10],
            'no numérico' => ['abc'],
            'fracción menor a un centavo' => ['0.001'],
            'fuera de precisión decimal' => ['100000000.00'],
        ];
    }

    public function test_required_fields_cannot_be_omitted(): void
    {
        $this->postJson('/api/payments', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['access_id', 'amount']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_access_cannot_receive_a_duplicate_payment(): void
    {
        $payment = $this->createPayment();

        $this->postJson('/api/payments', ['access_id' => $payment->access_id, 'amount' => '30.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('access_id');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('20.00', $payment->fresh()->amount);
    }

    public function test_status_and_paid_at_are_assigned_by_the_server(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->postJson('/api/payments', [
            'access_id' => $this->createAccess()->id,
            'amount' => '0.01',
            'status' => 'unknown',
            'paid_at' => '2000-01-01',
        ])->assertCreated()->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount', '0.01');

        $this->assertTrue(Payment::sole()->paid_at->equalTo(now()));
    }

    private function createAccess(): Access
    {
        $vehicle = User::factory()->create()->vehicles()->create([
            'plate' => 'PAY-'.(Access::count() + 1),
            'brand' => 'Nissan', 'model' => 'Versa', 'color' => 'Azul',
        ]);

        return $vehicle->accesses()->create(['entry_at' => now()]);
    }

    private function createPayment(): Payment
    {
        return $this->createAccess()->payment()->create([
            'amount' => '20.00', 'status' => 'paid', 'paid_at' => now(),
        ]);
    }
}
