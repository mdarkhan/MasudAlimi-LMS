<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BookSeeder extends Seeder
{
    /**
     * The real catalogue (title, author byline, cover image, where to buy). Source cover
     * files live in `database/seeders/assets/books/` (committed) — `storage/app/public/`
     * is gitignored, so seeding copies each one in rather than referencing it in place;
     * a fresh `migrate:fresh --seed` on any machine reproduces the real covers.
     *
     * `links` are [store name => URL]; the store name is what the site uses to pick the
     * store's logo (see components/ui/buy-links.blade.php). `publisher` / `pages` come from the
     * stores' own listings (Rokomari spec tab); `pages` is null where no store lists a page count. `author_was` marks a corrected byline (see run()).
     */
    private const BOOKS = [
        [
            'title' => 'সালাফদের জীবনকথা', 'publisher' => 'সমকালীন প্রকাশন', 'pages' => 240, 'author' => 'শাইখ আব্দুল আযীয | শাইখ বাহাউদ্দীন উকাইল', 'cover' => 'salafder-jibonkotha.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/191076/salafder-jibon-kotha',
                'Wafilife' => 'https://www.wafilife.com/salafder-jibon-kotha/pd/4360',
            ],
        ],
        [
            'title' => 'একটি মজার তাফসীর বলি', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 144, 'author' => 'মাসউদ আলিমী', 'cover' => 'ekti-mojar-tafsir-boli.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/214094/ekti-mojar-tafseer-boli',
                'Wafilife' => 'https://www.wafilife.com/ekti-mojar-tafsir-boli/pd/8602',
            ],
        ],
        [
            'title' => 'ঈমান ভঙ্গের কারণ', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => null, 'author' => 'শাইখ সুলায়মান ইবনু নাসির আল উলওয়ান', 'cover' => 'iman-vonger-karon.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/174462/eman-vonger-karon-naoyakidul-islam-er-byakhya',
                'Wafilife' => 'https://www.wafilife.com/iman-vonger-karon/pd/3763',
            ],
        ],
        [
            'title' => 'ফিতনার দিনে নির্জনবাস', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 112, 'author' => 'ইমাম ইবনু আবিদ দুনিয়া | মাসউদ আলিমী', 'cover' => 'fitnar-dine-nirjonbash.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/192538/fitnar-dine-nirjonbas',
                'Wafilife' => 'https://www.wafilife.com/fitnar-dine-nirjonbash/pd/4476',
            ],
        ],
        [
            'title' => 'সালাফদের ক্ষুধা', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 144, 'author' => 'ইমাম ইবনু আবিদ দুনিয়া | মাসউদ আলিমী', 'cover' => 'salafder-khuda.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/185601/salafder-kkhudha',
                'Wafilife' => 'https://www.wafilife.com/salafder-khuda/pd/4061',
            ],
        ],
        [
            'title' => 'ধনসম্পদ ও পদমর্যাদার লোভ', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 120, 'author' => 'ইবনু রজব হাম্বলি | মাসউদ আলিমী', 'cover' => 'dhonsampad-o-podmoryadar-lov.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/218040/dhonsompod-o-podomorjadar-lov',
                'Wafilife' => 'https://www.wafilife.com/dhonsompod-o-podomorjadar-lov/pd/10673',
            ],
        ],
        [
            'title' => 'আদ দুনিয়া- মুমিনের কারাগার, কাফিরের জান্নাত', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 224, 'author' => 'শাইখ মুহাম্মাদ আব্দুর রহমান ইওয়াদ | মাসউদ আলিমী', 'cover' => 'ad-duniya.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/572245/ad-duniya-muminer-karagar-kafirer-jannat',
                'Wafilife' => 'https://www.wafilife.com/ad-duniya-muminer-karagar-kafirer-jannat/pd/114479',
            ],
        ],
        [
            'title' => 'কেন আমরা নামাজ পড়ি?', 'publisher' => 'তাসনিফ পাবলিকেশন', 'pages' => 240, 'author' => 'ইসমাইল আল মুকাদ্দাম | মাসউদ আলিমী', 'cover' => 'keno-amra-namaz-pori.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/238906/keno-amra-namaj-pori',
                'Wafilife' => 'https://www.wafilife.com/keno-amra-namaj-pori/pd/16510',
            ],
        ],
        [
            'title' => 'খুশুখুজু', 'publisher' => 'সমকালীন প্রকাশন', 'pages' => 88, 'author' => 'আল্লামা ইবনুল কায়্যিম জাওযিয়্যাহ (রহ.) | মাসউদুর রহমান', 'author_was' => 'মাওলানা মুহাম্মাদ নুমান | রিফাত হাসান', 'cover' => 'khushukhuju.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/120306/khushu-khuju',
                'Wafilife' => 'https://www.wafilife.com/khushu-khuju/pd/3589',
            ],
        ],
        [
            'title' => 'নবীজির পদাঙ্ক অনুসরণ', 'publisher' => 'সীরাত পাবলিকেশন', 'pages' => 180, 'author' => 'ইবনু রজব হাম্বলি (রহিমাহুল্লাহ)', 'cover' => 'nobijir-podank-onushoron.png',
            'links' => [
                'Rokomari' => 'https://www.rokomari.com/book/167718/nobijir-podangko-anusoron',
                'Wafilife' => 'https://www.wafilife.com/nobijir-podanko-onushoron/pd/3538',
            ],
        ],
    ];

    /**
     * Additive only: creates a catalogue book that does not exist yet (with its cover and
     * links) and touches NOTHING that does. Once the site is live the admin panel owns
     * books — a re-run of this seeder (DEPLOYMENT.md says first deploy only, but a slip is
     * easy) must never delete a book the admin added, or reset a title, cover or purchase
     * link the admin has edited. It used to do both.
     *
     * To change a book after it exists, use the admin panel, not this file.
     */
    public function run(): void
    {
        foreach (self::BOOKS as $order => $row) {
            $slug = Slug::make($row['title'], 'book');

            $existing = Book::query()->where('slug', $slug)->first();
            if ($existing !== null) {
                // Hosts with an ephemeral filesystem (a fresh container every deploy) lose
                // storage/app/public. Put a MISSING catalogue cover back — never overwrite a file
                // that is there, and never touch a book whose cover the admin has replaced.
                $coverPath = 'books/'.$row['cover'];
                $sourcePath = __DIR__.'/assets/books/'.$row['cover'];
                if ($existing->cover_path === $coverPath && is_file($sourcePath) && ! Storage::disk(config('filesystems.uploads_disk'))->exists($coverPath)) {
                    Storage::disk(config('filesystems.uploads_disk'))->put($coverPath, file_get_contents($sourcePath));
                }

                // Fill publisher / page count ONLY where the admin left them blank — a value that is there
                // (typed or edited by the admin) is never touched.
                if (blank($existing->publisher) && filled($row['publisher'] ?? null)) {
                    $existing->publisher = $row['publisher'];
                }
                if (blank($existing->page_count) && filled($row['pages'] ?? null)) {
                    $existing->page_count = $row['pages'];
                }
                // A catalogue correction to a byline: applied only while the stored author is STILL the exact old
                // catalogue value (`author_was`) — an author the admin has edited is never overwritten.
                if (isset($row['author_was']) && $existing->author === $row['author_was']) {
                    $existing->author = $row['author'];
                }
                if ($existing->isDirty()) {
                    $existing->save();
                }

                continue;
            }

            $coverPath = 'books/'.$row['cover'];
            $sourcePath = __DIR__.'/assets/books/'.$row['cover'];
            if (is_file($sourcePath)) {
                Storage::disk(config('filesystems.uploads_disk'))->put($coverPath, file_get_contents($sourcePath));
            }

            $book = Book::query()->create([
                'slug' => $slug,
                'title' => $row['title'],
                'author' => $row['author'],
                'publisher' => $row['publisher'] ?? null,
                'page_count' => $row['pages'] ?? null,
                'cover_path' => $coverPath,
                'sort_order' => $order + 1,
                'is_published' => true,
            ]);

            $position = 0;
            foreach ($row['links'] as $store => $url) {
                $book->purchaseLinks()->create(['website_name' => $store, 'url' => $url, 'sort_order' => $position++]);
            }
        }
    }
}
