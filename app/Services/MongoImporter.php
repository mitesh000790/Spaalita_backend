<?php

namespace App\Services;

use App\Exceptions\UploadException;
use App\Models\MonthlySpecial;
use App\Models\OrderAddress;
use App\Models\UserAddress;
use App\Support\ExtendedJson;
use App\Support\ObjectId;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imports MongoDB collection exports into the MySQL schema, keeping every
 * document's ObjectId so existing references, URLs and client data stay valid.
 *
 * Re-running an import replaces the records it contains (matched by id).
 */
class MongoImporter
{
    /**
     * Collection name => importer method, in dependency order.
     */
    public const COLLECTIONS = [
        'categories' => 'importCategories',
        'products' => 'importProducts',
        'giftcards' => 'importGiftCards',
        'admins' => 'importAdmins',
        'users' => 'importUsers',
        'coupons' => 'importCoupons',
        'orders' => 'importOrders',
        'services' => 'importServices',
        'bookingpolicies' => 'importBookingPolicies',
        'monthlyspecials' => 'importMonthlySpecials',
        'roles' => 'importRoles',
        'contacts' => 'importContacts',
        'galleries' => 'importGalleries',
    ];

    /**
     * Legacy WooCommerce category id => category id, for products whose
     * categories still hold WooCommerce ids.
     *
     * @var array<string, string>
     */
    private array $legacyCategoryIds = [];

    public function __construct(private readonly ImageUploadService $uploads) {}

    /**
     * Import every collection file found in the directory.
     *
     * @param  callable(string, int): void|null  $progress  called with (collection, count)
     * @return array<string, int> collection => imported document count
     */
    public function importDirectory(string $directory, ?callable $progress = null): array
    {
        $imported = [];

        Schema::withoutForeignKeyConstraints(function () use ($directory, $progress, &$imported) {
            foreach (self::COLLECTIONS as $collection => $method) {
                $file = $this->findFile($directory, $collection);

                if ($file === null) {
                    continue;
                }

                $documents = ExtendedJson::readFile($file);
                DB::transaction(fn () => array_walk($documents, fn (array $document) => $this->{$method}($document)));

                $imported[$collection] = count($documents);

                if ($progress !== null) {
                    $progress($collection, count($documents));
                }
            }
        });

        return $imported;
    }

    /**
     * "<collection>.json" or a Compass-style "<database>.<collection>.json".
     */
    private function findFile(string $directory, string $collection): ?string
    {
        foreach (glob(rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $name = strtolower(basename($path, '.json'));

            if ($name === $collection || str_ends_with($name, '.'.$collection)) {
                return $path;
            }
        }

        return null;
    }

    private function importCategories(array $document): void
    {
        $this->insertCategory($document, null, 0, $document);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $root  the root document (sub-categories have no timestamps of their own)
     */
    private function insertCategory(array $node, ?string $parentId, int $position, array $root): void
    {
        $id = $this->id($node);
        $image = is_array($node['image'] ?? null) ? $node['image'] : null;

        $this->replace('categories', $id, [
            'o_id' => is_numeric($node['oId'] ?? null) ? (int) $node['oId'] : null,
            'parent_id' => $parentId,
            'name' => (string) ($node['name'] ?? ''),
            'slug' => (string) ($node['slug'] ?? ''),
            'description' => $node['description'] ?? '',
            'image_id' => is_numeric($image['id'] ?? null) ? (int) $image['id'] : null,
            'image_src' => $image['src'] ?? null,
            'count' => (int) ($node['count'] ?? 0),
            'position' => $position,
        ] + $this->timestamps($root));

        if (isset($node['oId'])) {
            $this->legacyCategoryIds[(string) $node['oId']] = $id;
        }

        foreach (array_values($node['children'] ?? []) as $index => $child) {
            if (is_array($child)) {
                $this->insertCategory($child, $id, $index + 1, $root);
            }
        }
    }

    private function importProducts(array $document): void
    {
        $this->insertCatalogItem('products', 'category_product', 'product_id', $document);
    }

    private function importGiftCards(array $document): void
    {
        $this->insertCatalogItem('gift_cards', 'category_gift_card', 'gift_card_id', $document);
    }

    private function insertCatalogItem(string $table, string $pivot, string $foreignKey, array $document): void
    {
        $id = $this->id($document);

        $this->replace($table, $id, [
            'product_name' => (string) ($document['productName'] ?? ''),
            'sku' => (string) ($document['sku'] ?? $id),
            'price' => $document['price'] ?? 0,
            'regular_price' => (string) ($document['regular_price'] ?? ''),
            'sale_price' => (string) ($document['sale_price'] ?? ''),
            'qty' => (int) ($document['qty'] ?? 0),
            'product_images' => json_encode(array_values($document['productImages'] ?? [])),
            'description' => (string) ($document['description'] ?? ''),
            'short_description' => $document['short_description'] ?? '',
            'status' => $document['status'] ?? 'active',
            'slug' => $document['slug'] ?? '',
            'tax_status' => $document['tax_status'] ?? 'none',
            'shipping_required' => $document['shipping_required'] ?? true,
            'shipping_taxable' => $document['shipping_taxable'] ?? false,
            'stock_status' => $document['stock_status'] ?? 'instock',
            'related_ids' => json_encode(array_values($document['related_ids'] ?? [])),
        ] + $this->timestamps($document));

        DB::table($pivot)->where($foreignKey, $id)->delete();

        $categoryIds = collect($document['categories'] ?? [])
            ->map(fn ($category) => $this->categoryId($category))
            ->filter()
            ->unique()
            ->values();
        $existing = DB::table('categories')->whereIn('id', $categoryIds)->pluck('id')->all();

        DB::table($pivot)->insert($categoryIds
            ->filter(fn (string $categoryId) => in_array($categoryId, $existing, true))
            ->map(fn (string $categoryId) => ['category_id' => $categoryId, $foreignKey => $id])
            ->all());
    }

    private function importAdmins(array $document): void
    {
        $this->replace('admins', $this->id($document), [
            'username' => (string) ($document['username'] ?? ''),
            'email' => (string) ($document['email'] ?? ''),
            'password' => (string) ($document['password'] ?? ''),
            'role' => $document['role'] ?? 'admin',
            'is_active' => $document['isActive'] ?? true,
        ] + $this->timestamps($document));
    }

    private function importUsers(array $document): void
    {
        $id = $this->id($document);

        $this->replace('users', $id, [
            'profile_picture' => $document['profilePicture'] ?? '',
            'user_name' => (string) ($document['userName'] ?? $id),
            'first_name' => (string) ($document['firstName'] ?? ''),
            'last_name' => (string) ($document['lastName'] ?? ''),
            'email' => (string) ($document['email'] ?? $id),
            'phone' => (string) ($document['phone'] ?? ''),
            'country' => (string) ($document['country'] ?? ''),
            'city' => (string) ($document['city'] ?? ''),
            'address' => (string) ($document['address'] ?? ''),
            'postal_code' => (string) ($document['postalCode'] ?? ''),
            'password' => (string) ($document['password'] ?? ''),
            'role' => $document['role'] ?? 'user',
            'status' => $document['status'] ?? 'active',
            'reset_password_token' => $document['resetPasswordToken'] ?? null,
            'reset_password_expire' => $this->date($document['resetPasswordExpire'] ?? null),
        ] + $this->timestamps($document));

        DB::table('user_addresses')->where('user_id', $id)->delete();

        foreach ([UserAddress::TYPE_BILLING => 'billing', UserAddress::TYPE_SHIPPING => 'shipping'] as $type => $field) {
            $address = $document[$field] ?? null;

            if (! is_array($address) || $address === []) {
                continue;
            }

            $row = ['user_id' => $id, 'type' => $type];

            foreach (UserAddress::FIELDS[$type] as $key => $column) {
                $row[$column] = isset($address[$key]) ? (string) $address[$key] : null;
            }

            DB::table('user_addresses')->insert($row);
        }
    }

    private function importCoupons(array $document): void
    {
        $id = $this->id($document);

        $this->replace('coupons', $id, [
            'code' => (string) ($document['code'] ?? $id),
            'discount_type' => (string) ($document['discountType'] ?? 'fixed'),
            'discount_value' => $document['discountValue'] ?? 0,
            'allow_free_shipping' => $document['allowFreeShipping'] ?? false,
            'expiry_date' => $this->date($document['expiryDate'] ?? null) ?? now()->format('Y-m-d H:i:s.v'),
            'min_order_amount' => $document['minOrderAmount'] ?? 0,
            'max_order_amount' => $document['maxOrderAmount'] ?? null,
            'categories' => json_encode(array_values($document['categories'] ?? [])),
            'exclude_categories' => json_encode(array_values($document['excludeCategories'] ?? [])),
            'usage_limit_per_coupon' => $document['usageLimitPerCoupon'] ?? null,
            'usage_limit_per_user' => $document['usageLimitPerUser'] ?? null,
            'usage_count' => (int) ($document['usageCount'] ?? 0),
            'status' => $document['status'] ?? 'active',
        ] + $this->timestamps($document));

        DB::table('coupon_products')->where('coupon_id', $id)->delete();

        $lists = [['products', 'productsModel', false], ['excludeProducts', 'excludeProductsModel', true]];

        foreach ($lists as [$field, $modelField, $excluded]) {
            foreach (array_values($document[$field] ?? []) as $index => $productId) {
                DB::table('coupon_products')->insert([
                    'coupon_id' => $id,
                    'productable_type' => $document[$modelField][$index] ?? 'Product',
                    'productable_id' => is_string($productId) ? $productId : null,
                    'is_excluded' => $excluded,
                ]);
            }
        }
    }

    private function importOrders(array $document): void
    {
        $id = $this->id($document);
        $guest = is_array($document['guestInfo'] ?? null) ? $document['guestInfo'] : [];
        $coupon = is_array($document['coupon'] ?? null) ? $document['coupon'] : null;

        $this->replace('orders', $id, [
            'user_id' => is_string($document['user'] ?? null) ? $document['user'] : null,
            'guest_name' => $guest['name'] ?? null,
            'guest_email' => $guest['email'] ?? null,
            'guest_phone' => $guest['phone'] ?? null,
            'total_amount' => $document['totalAmount'] ?? 0,
            'status' => $document['status'] ?? 'pending',
            'payment_intent_id' => $document['paymentIntentId'] ?? null,
            'payment_method' => $document['paymentMethod'] ?? null,
            'payment_status' => $document['paymentStatus'] ?? 'pending',
            'coupon_code' => $coupon['code'] ?? null,
            'coupon_discount_amount' => $coupon === null ? null : ($coupon['discountAmount'] ?? 0),
            'coupon_free_shipping_amount' => $coupon === null ? null : ($coupon['freeShippingAmount'] ?? 0),
        ] + $this->timestamps($document));

        DB::table('order_items')->where('order_id', $id)->delete();

        foreach (array_values($document['items'] ?? []) as $position => $item) {
            DB::table('order_items')->insert([
                'id' => is_string($item['_id'] ?? null) ? $item['_id'] : ObjectId::generate(),
                'order_id' => $id,
                'product_id' => is_string($item['productId'] ?? null) ? $item['productId'] : null,
                'name' => $item['name'] ?? null,
                'price' => $item['price'] ?? null,
                'qty' => $item['qty'] ?? null,
                'image' => $item['image'] ?? null,
                'position' => $position,
            ]);
        }

        DB::table('order_addresses')->where('order_id', $id)->delete();

        foreach ([OrderAddress::TYPE_SHIPPING => 'shippingAddress', OrderAddress::TYPE_BILLING => 'billingAddress'] as $type => $field) {
            $address = $document[$field] ?? null;

            if (! is_array($address)) {
                continue;
            }

            $row = ['order_id' => $id, 'type' => $type];

            foreach (OrderAddress::FIELDS as $key => $column) {
                $row[$column] = isset($address[$key]) ? (string) $address[$key] : '';
            }

            DB::table('order_addresses')->insert($row);
        }
    }

    private function importServices(array $document): void
    {
        $this->replace('services', $this->id($document), [
            'service_name' => (string) ($document['serviceName'] ?? ''),
            'service_image' => (string) ($document['serviceImage'] ?? ''),
            'service_description' => (string) ($document['serviceDescription'] ?? ''),
            'button_url' => (string) ($document['buttonUrl'] ?? ''),
            'status' => $document['status'] ?? 'active',
        ] + $this->timestamps($document));
    }

    private function importBookingPolicies(array $document): void
    {
        $this->replace('booking_policies', $this->id($document), [
            'title' => (string) ($document['title'] ?? ''),
            'description' => (string) ($document['description'] ?? ''),
            'button_url' => (string) ($document['buttonUrl'] ?? ''),
            'status' => $document['status'] ?? 'inactive',
        ] + $this->timestamps($document));
    }

    /**
     * Base64 data URI images are saved as files under /uploads/monthlySpecial/,
     * as the API does (a large one would not fit in one database packet).
     */
    private function importMonthlySpecials(array $document): void
    {
        $image = (string) ($document['image'] ?? '');

        if (ImageUploadService::isDataUri($image)) {
            try {
                $folder = MonthlySpecial::UPLOAD_FOLDER;
                $image = "/uploads/{$folder}/".$this->uploads->storeDataUri($image, $folder);
            } catch (UploadException) {
                // Not a recognised image: keep the value as it was in MongoDB.
            }
        }

        $this->replace('monthly_specials', $this->id($document), [
            'month' => (string) ($document['month'] ?? $document['title'] ?? ''),
            'image' => $image,
        ] + $this->timestamps($document));
    }

    private function importRoles(array $document): void
    {
        $this->replace('roles', $this->id($document), [
            'name' => (string) ($document['name'] ?? ''),
            'description' => $document['description'] ?? null,
            'status' => $document['status'] ?? 'active',
        ] + $this->timestamps($document));
    }

    private function importContacts(array $document): void
    {
        $this->replace('contacts', $this->id($document), [
            'name' => (string) ($document['name'] ?? ''),
            'email' => (string) ($document['email'] ?? ''),
            'phone' => (string) ($document['phone'] ?? ''),
            'address' => $document['address'] ?? null,
            'subject' => (string) ($document['subject'] ?? ''),
            'message' => (string) ($document['message'] ?? ''),
            'created_at' => $this->date($document['createdAt'] ?? null),
        ]);
    }

    private function importGalleries(array $document): void
    {
        $url = $document['url'] ?? ($document['images'][0] ?? null);

        if (is_string($url)) {
            $this->replace('galleries', $this->id($document), ['url' => $url] + $this->timestamps($document));
        }
    }

    /**
     * Insert a row, replacing any existing row with the same id.
     *
     * @param  array<string, mixed>  $values
     */
    private function replace(string $table, string $id, array $values): void
    {
        DB::table($table)->where('id', $id)->delete();
        DB::table($table)->insert(['id' => $id] + $values);
    }

    private function id(array $document): string
    {
        return is_string($document['_id'] ?? null) ? $document['_id'] : ObjectId::generate();
    }

    /**
     * A category reference: an ObjectId, or a legacy WooCommerce id.
     */
    private function categoryId(mixed $category): ?string
    {
        if (is_string($category) && ObjectId::isValid($category)) {
            return strtolower($category);
        }

        return is_scalar($category) ? ($this->legacyCategoryIds[(string) $category] ?? null) : null;
    }

    /**
     * @return array{created_at: string|null, updated_at: string|null}
     */
    private function timestamps(array $document): array
    {
        $createdAt = $this->date($document['createdAt'] ?? null);

        return [
            'created_at' => $createdAt,
            'updated_at' => $this->date($document['updatedAt'] ?? null) ?? $createdAt,
        ];
    }

    private function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v')
            : null;
    }
}
