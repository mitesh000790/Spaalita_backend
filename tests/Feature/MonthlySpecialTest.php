<?php

namespace Tests\Feature;

use App\Models\MonthlySpecial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonthlySpecialTest extends TestCase
{
    use RefreshDatabase;

    private const STORED_PATH = '#^/uploads/monthlySpecial/\d{13}-\d+\.(png|jpg|gif|webp)$#';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
    }

    public function test_a_large_base64_image_is_saved_as_a_file(): void
    {
        // About 2 MB, larger than MySQL's default 1 MB max_allowed_packet.
        $png = self::pngBytes().random_bytes(1_500_000);

        $response = $this->postJson('/api/monthly-special', [
            'month' => 'October',
            'image' => 'data:image/png;base64,'.base64_encode($png),
        ], $this->asAdmin());

        $response->assertCreated()->assertJsonPath('data.month', 'October');
        $path = $response->json('data.image');
        $this->assertMatchesRegularExpression(self::STORED_PATH, $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertSame($png, Storage::disk('uploads')->get('monthlySpecial/'.basename($path)));
        $this->assertSame($path, MonthlySpecial::query()->value('image'));
    }

    public function test_the_file_type_comes_from_the_image_bytes(): void
    {
        $headers = $this->asAdmin();
        $images = [
            'January' => ["\xFF\xD8\xFF\xE0".random_bytes(64), '.jpg'],
            'February' => ['GIF89a'.random_bytes(64), '.gif'],
            'March' => ['RIFF'.pack('V', 64).'WEBPVP8 '.random_bytes(64), '.webp'],
        ];

        foreach ($images as $month => [$bytes, $extension]) {
            // The declared type is ignored: these are all sent as "image/png".
            $path = $this->postJson('/api/monthly-special', ['month' => $month, 'image' => 'data:image/png;base64,'.base64_encode($bytes)], $headers)
                ->assertCreated()->json('data.image');
            $this->assertStringEndsWith($extension, $path);
        }
    }

    public function test_data_that_is_not_an_image_is_rejected(): void
    {
        $headers = $this->asAdmin();
        $invalid = ['message' => 'Invalid image. Only jpg, png, gif or webp images are allowed.', 'success' => false];

        $this->postJson('/api/monthly-special', ['month' => 'May', 'image' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')], $headers)
            ->assertStatus(400)->assertExactJson($invalid);
        $this->postJson('/api/monthly-special', ['month' => 'May', 'image' => 'data:image/png;base64,***'], $headers)
            ->assertStatus(400)->assertExactJson($invalid);

        $this->assertSame(0, MonthlySpecial::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_multipart_uploads_and_required_fields(): void
    {
        $headers = $this->asAdmin();

        $path = $this->multipart('POST', '/api/monthly-special', [['month', 'April']], [['image', 'Image-Sep-29-2026-12_46_02-PM-791x1024.png', 'image/png']], $headers)
            ->assertCreated()->json('data.image');
        $this->assertMatchesRegularExpression(self::STORED_PATH, $path);
        Storage::disk('uploads')->assertExists('monthlySpecial/'.basename($path));

        $required = ['success' => false, 'message' => 'All fields are required'];
        $this->multipart('POST', '/api/monthly-special', [['month', 'June']], [], $headers)->assertStatus(400)->assertExactJson($required);
        $this->postJson('/api/monthly-special', ['month' => 'June', 'image' => ''], $headers)->assertStatus(400)->assertExactJson($required);
        $this->postJson('/api/monthly-special', ['image' => 'data:image/png;base64,'.base64_encode(self::pngBytes())], $headers)
            ->assertStatus(400)->assertExactJson($required);
    }

    public function test_replaced_and_deleted_images_are_removed(): void
    {
        $headers = $this->asAdmin();
        $dataUri = 'data:image/png;base64,'.base64_encode(self::pngBytes());

        $special = $this->postJson('/api/monthly-special', ['month' => 'August', 'image' => $dataUri], $headers)->json('data');
        $first = 'monthlySpecial/'.basename($special['image']);

        // The admin form sends the current path back when no new file is chosen.
        $this->putJson("/api/monthly-special/{$special['_id']}", ['month' => 'August', 'image' => $special['image']], $headers)
            ->assertOk()->assertJsonPath('data.image', $special['image']);
        Storage::disk('uploads')->assertExists($first);

        $second = $this->putJson("/api/monthly-special/{$special['_id']}", ['month' => 'August', 'image' => $dataUri], $headers)
            ->assertOk()->json('data.image');
        $this->assertNotSame($special['image'], $second);
        Storage::disk('uploads')->assertMissing($first);
        Storage::disk('uploads')->assertExists('monthlySpecial/'.basename($second));

        $this->deleteJson("/api/monthly-special/{$special['_id']}", [], $headers)->assertOk();
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_urls_are_stored_as_sent(): void
    {
        $url = 'https://spaalita.ca/wp-content/uploads/2026/01/2026-FebSpecials-1024x1024.png';

        $this->postJson('/api/monthly-special', ['month' => 'September', 'image' => $url], $this->asAdmin())
            ->assertCreated()->assertJsonPath('data.image', $url);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }
}
