<?php

namespace Database\Seeders;

use App\Models\BookingPolicy;
use App\Models\Contact;
use App\Models\Gallery;
use App\Models\GiftCard;
use App\Models\MonthlySpecial;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One sample record per resource, for local development
 * (customer login: user1@example.com / User@123).
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $user = User::query()->updateOrCreate(['user_name' => 'user1'], [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'user1@example.com',
                'phone' => '1234567890',
                'country' => 'Country',
                'city' => 'City',
                'address' => '123 Main St',
                'postal_code' => '12345',
                'password' => Hash::make('User@123'),
                'role' => 'user',
                'status' => 'active',
            ]);

            $product = Product::query()->updateOrCreate(['sku' => 'SKU001'], [
                'product_name' => 'Sample Product',
                'price' => 99.99,
                'qty' => 10,
                'product_images' => [],
                'description' => 'Sample product description',
                'status' => 'active',
            ]);

            GiftCard::query()->updateOrCreate(['sku' => 'GIFT001'], [
                'product_name' => 'Sample Gift Card',
                'price' => 50,
                'qty' => 5,
                'product_images' => [],
                'description' => 'Sample gift card description',
                'status' => 'active',
            ]);

            Service::query()->updateOrCreate(['service_name' => 'Sample Service'], [
                'service_image' => '/uploads/sample.jpg',
                'service_description' => 'Sample service description',
                'button_url' => 'https://example.com',
                'status' => 'active',
            ]);

            Role::query()->updateOrCreate(['name' => 'admin'], [
                'description' => 'Administrator role',
                'status' => 'active',
            ]);

            if (! Order::query()->where('user_id', $user->id)->exists()) {
                $this->createSampleOrder($user, $product);
            }

            Gallery::query()->firstOrCreate(['url' => '/uploads/sample.jpg']);

            Contact::query()->firstOrCreate(['email' => 'contact@example.com'], [
                'name' => 'Contact Name',
                'phone' => '9876543210',
                'address' => '456 Main St',
                'subject' => 'Sample Subject',
                'message' => 'Sample message',
            ]);

            BookingPolicy::query()->updateOrCreate(['title' => 'Sample Policy'], [
                'description' => 'Sample booking policy description',
                'button_url' => 'https://example.com/policy',
                'status' => 'active',
            ]);

            MonthlySpecial::query()->firstOrCreate(['month' => 'Sample Special'], [
                'image' => '/uploads/sample.jpg',
            ]);
        });
    }

    private function createSampleOrder(User $user, Product $product): void
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'total_amount' => $product->price,
            'status' => 'pending',
            'payment_intent_id' => '',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->product_name,
            'price' => $product->price,
            'qty' => 1,
            'image' => '',
            'position' => 0,
        ]);

        $address = [
            'name' => 'John Doe',
            'address' => '123 Main St',
            'city' => 'City',
            'country' => 'Country',
            'email' => 'user1@example.com',
            'phone' => '1234567890',
        ];

        foreach ([OrderAddress::TYPE_SHIPPING, OrderAddress::TYPE_BILLING] as $type) {
            $order->addresses()->create(['type' => $type] + $address);
        }
    }
}
