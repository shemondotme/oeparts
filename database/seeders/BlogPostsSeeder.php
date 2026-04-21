<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostsSeeder extends Seeder
{
    public function run(): void
    {
        // Create blog categories
        $categoryNames = [
            'Maintenance Tips',
            'How-To Guides',
            'Industry News',
            'New Arrivals',
            'OEM Tips',
            'Workshop Insights'
        ];

        $categories = [];
        foreach ($categoryNames as $index => $name) {
            $categories[] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => ['en' => $name, 'bn' => $name], 'sort_order' => $index]
            );
        }

        // Get or create admin user
        $author = Admin::first();
        if (!$author) {
            $author = Admin::create([
                'name' => 'Admin',
                'email' => 'admin@oemhub.test',
                'password' => bcrypt('password'),
            ]);
        }

        // Blog post data
        $posts = [
            [
                'title' => ['en' => 'How to Choose the Right OEM Parts for Your Vehicle', 'bn' => 'How to Choose the Right OEM Parts for Your Vehicle'],
                'excerpt' => ['en' => 'Learn the key factors to consider when selecting genuine OEM parts. From compatibility checks to warranty verification, we cover everything you need to know.', 'bn' => 'Learn the key factors to consider when selecting genuine OEM parts. From compatibility checks to warranty verification, we cover everything you need to know.'],
                'content' => ['en' => 'Detailed content about choosing OEM parts...', 'bn' => 'Detailed content about choosing OEM parts...'],
                'category_id' => $categories[0]->id,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => ['en' => '5 Signs Your Brake Pads Need Replacement', 'bn' => '5 Signs Your Brake Pads Need Replacement'],
                'excerpt' => ['en' => 'Don\'t ignore these warning signs. Discover when it\'s time to replace your brake pads and how to choose the best OEM replacements for optimal safety.', 'bn' => 'Don\'t ignore these warning signs. Discover when it\'s time to replace your brake pads and how to choose the best OEM replacements for optimal safety.'],
                'content' => ['en' => 'Detailed content about brake pad replacement...', 'bn' => 'Detailed content about brake pad replacement...'],
                'category_id' => $categories[1]->id,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => ['en' => 'The Rise of Electric Vehicle Components in Europe', 'bn' => 'The Rise of Electric Vehicle Components in Europe'],
                'excerpt' => ['en' => 'Explore the growing demand for EV components and how OEMHub is meeting the needs of European workshops with certified electric vehicle parts.', 'bn' => 'Explore the growing demand for EV components and how OEMHub is meeting the needs of European workshops with certified electric vehicle parts.'],
                'content' => ['en' => 'Detailed content about EV components...', 'bn' => 'Detailed content about EV components...'],
                'category_id' => $categories[2]->id,
                'published_at' => now()->subDays(8),
            ],
            [
                'title' => ['en' => 'New Arrival: Bosch Fuel Injectors Now Available', 'bn' => 'New Arrival: Bosch Fuel Injectors Now Available'],
                'excerpt' => ['en' => 'We\'re excited to announce our latest addition - genuine Bosch fuel injectors compatible with major European vehicle brands. Limited stock available.', 'bn' => 'We\'re excited to announce our latest addition - genuine Bosch fuel injectors compatible with major European vehicle brands. Limited stock available.'],
                'content' => ['en' => 'Detailed content about Bosch fuel injectors...', 'bn' => 'Detailed content about Bosch fuel injectors...'],
                'category_id' => $categories[3]->id,
                'published_at' => now()->subDays(11),
            ],
            [
                'title' => ['en' => 'Understanding OEM Part Numbers: A Complete Guide', 'bn' => 'Understanding OEM Part Numbers: A Complete Guide'],
                'excerpt' => ['en' => 'Decode OEM part numbers like a pro. This comprehensive guide explains the numbering system used by major manufacturers and how to verify authenticity.', 'bn' => 'Decode OEM part numbers like a pro. This comprehensive guide explains the numbering system used by major manufacturers and how to verify authenticity.'],
                'content' => ['en' => 'Detailed content about OEM part numbers...', 'bn' => 'Detailed content about OEM part numbers...'],
                'category_id' => $categories[4]->id,
                'published_at' => now()->subDays(14),
            ],
            [
                'title' => ['en' => 'Top 10 Tools Every Auto Workshop Needs in 2026', 'bn' => 'Top 10 Tools Every Auto Workshop Needs in 2026'],
                'excerpt' => ['en' => 'From diagnostic scanners to specialized torque wrenches, discover the essential tools that will boost your workshop\'s efficiency and service quality.', 'bn' => 'From diagnostic scanners to specialized torque wrenches, discover the essential tools that will boost your workshop\'s efficiency and service quality.'],
                'content' => ['en' => 'Detailed content about workshop tools...', 'bn' => 'Detailed content about workshop tools...'],
                'category_id' => $categories[5]->id,
                'published_at' => now()->subDays(17),
            ],
        ];

        foreach ($posts as $postData) {
            BlogPost::create([
                'title' => $postData['title'],
                'slug' => Str::slug($postData['title']['en']),
                'excerpt' => $postData['excerpt'],
                'content' => $postData['content'],
                'category_id' => $postData['category_id'],
                'author_id' => $author->id,
                'status' => 'published',
                'published_at' => $postData['published_at'],
            ]);
        }

        $this->command->info('✓ Created 6 blog categories and 6 blog posts!');
    }
}
