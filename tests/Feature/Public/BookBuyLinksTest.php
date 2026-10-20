<?php

namespace Tests\Feature\Public;

use App\Models\Book;
use Database\Seeders\BookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookBuyLinksTest extends TestCase
{
    use RefreshDatabase;

    private function bookWithLinks(): Book
    {
        $book = Book::factory()->create(['is_published' => true]);
        $book->purchaseLinks()->create(['website_name' => 'Rokomari', 'url' => 'https://www.rokomari.com/book/1/x', 'sort_order' => 0]);
        $book->purchaseLinks()->create(['website_name' => 'Wafilife', 'url' => 'https://www.wafilife.com/x/pd/1', 'sort_order' => 1]);

        return $book;
    }

    public function test_the_homepage_shows_each_stores_logo_and_link_on_the_book_card(): void
    {
        $this->bookWithLinks();

        $this->get(route('home'))->assertOk()
            ->assertSee('images/brands/rokomari.png', false)
            ->assertSee('images/brands/wafilife.svg', false)
            ->assertSee('https://www.rokomari.com/book/1/x', false)
            ->assertSee('https://www.wafilife.com/x/pd/1', false);
    }

    public function test_the_book_page_shows_the_buy_links_up_front(): void
    {
        $book = $this->bookWithLinks();

        $this->get(route('books.show', $book))->assertOk()
            ->assertSee('images/brands/rokomari.png', false)
            ->assertSee('https://www.wafilife.com/x/pd/1', false);
    }

    public function test_an_unknown_store_falls_back_to_its_name_without_a_logo(): void
    {
        $book = Book::factory()->create(['is_published' => true]);
        $book->purchaseLinks()->create(['website_name' => 'Boi Bazar', 'url' => 'https://example.test/b', 'sort_order' => 0]);

        $this->get(route('books.show', $book))->assertOk()->assertSee('Boi Bazar');
    }

    public function test_rerunning_the_seeder_never_deletes_or_overwrites_the_admins_work(): void
    {
        Storage::fake('public');
        $this->seed(BookSeeder::class);

        // The admin adds a book, edits a seeded book's title and links, and removes a link.
        $mine = Book::factory()->create(['title' => 'অ্যাডমিনের নিজের বই', 'is_published' => true]);
        $seeded = Book::query()->where('sort_order', 1)->firstOrFail();
        $seeded->update(['title' => 'সম্পাদিত শিরোনাম']);
        $seeded->purchaseLinks()->delete();
        $seeded->purchaseLinks()->create(['website_name' => 'Boi Bazar', 'url' => 'https://example.test/x', 'sort_order' => 0]);

        $this->seed(BookSeeder::class);   // the slip DEPLOYMENT.md warns against

        $this->assertNotNull($mine->fresh(), 'an admin-added book must survive');
        $this->assertSame('সম্পাদিত শিরোনাম', $seeded->fresh()->title);
        $this->assertSame(['Boi Bazar'], $seeded->fresh()->purchaseLinks()->pluck('website_name')->all());
        $this->assertSame(11, Book::query()->count(), '10 catalogue books + the admin\'s, nothing recreated or removed');
    }

    public function test_the_seeded_catalogue_has_both_store_links_for_every_book(): void
    {
        Storage::fake('public');
        $this->seed(BookSeeder::class);

        $books = Book::query()->with('purchaseLinks')->get();
        $this->assertCount(10, $books);

        foreach ($books as $book) {
            $stores = $book->purchaseLinks->pluck('website_name')->all();
            $this->assertSame(['Rokomari', 'Wafilife'], $stores, $book->title);
        }
    }

    public function test_rerunning_the_seeder_restores_a_missing_cover_file_but_never_overwrites_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(\Database\Seeders\BookSeeder::class);
        $book = Book::query()->where('cover_path', 'books/salafder-jibonkotha.png')->firstOrFail();

        \Illuminate\Support\Facades\Storage::disk('public')->delete($book->cover_path);   // a new container: files are gone
        $this->seed(\Database\Seeders\BookSeeder::class);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($book->cover_path);

        \Illuminate\Support\Facades\Storage::disk('public')->put($book->cover_path, 'custom');
        $this->seed(\Database\Seeders\BookSeeder::class);
        $this->assertSame('custom', \Illuminate\Support\Facades\Storage::disk('public')->get($book->cover_path), 'an existing file is left alone');
    }

    public function test_the_catalogue_carries_publisher_and_pages_and_only_fills_blanks_on_a_rerun(): void
    {
        Storage::fake('public');
        $this->seed(BookSeeder::class);

        $this->assertSame(10, Book::query()->whereNotNull('publisher')->count(), 'every catalogue book has a publisher');
        $this->assertSame(240, Book::query()->where('title', 'সালাফদের জীবনকথা')->value('page_count'));
        $this->assertNull(Book::query()->where('title', 'ঈমান ভঙ্গের কারণ')->value('page_count'), 'no store lists it, so it stays empty rather than guessed');

        // The admin edits one book and blanks another's fields: a re-run fills only the blank ones.
        $edited = Book::query()->where('title', 'সালাফদের ক্ষুধা')->firstOrFail();
        $edited->update(['publisher' => 'Admin Publisher', 'page_count' => 999]);
        $blank = Book::query()->where('title', 'খুশুখুজু')->firstOrFail();
        $blank->update(['publisher' => null, 'page_count' => null]);

        $this->seed(BookSeeder::class);

        $this->assertSame('Admin Publisher', $edited->fresh()->publisher, 'an admin-set value is never overwritten');
        $this->assertSame(999, $edited->fresh()->page_count);
        $this->assertSame('সমকালীন প্রকাশন', $blank->fresh()->publisher);
        $this->assertSame(88, $blank->fresh()->page_count);
    }

    public function test_a_corrected_catalogue_author_is_applied_only_while_the_old_value_is_unedited(): void
    {
        Storage::fake('public');
        $this->seed(BookSeeder::class);

        $book = Book::query()->where('title', 'খুশুখুজু')->firstOrFail();
        $this->assertStringContainsString('ইবনুল কায়্যিম', $book->author);

        // Simulate production, which still holds the OLD catalogue author: the next seed corrects it …
        $book->update(['author' => 'মাওলানা মুহাম্মাদ নুমান | রিফাত হাসান']);
        $this->seed(BookSeeder::class);
        $this->assertStringContainsString('ইবনুল কায়্যিম', $book->fresh()->author);

        // … but an author the admin typed is left alone.
        $book->update(['author' => 'Admin chosen author']);
        $this->seed(BookSeeder::class);
        $this->assertSame('Admin chosen author', $book->fresh()->author);
    }
}
