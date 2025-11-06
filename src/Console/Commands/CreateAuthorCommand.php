<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use Timeax\FortiPlugin\Models\Author;

class CreateAuthorCommand extends Command
{
    /** @var string */
    protected $signature = 'fp:author 
        {--email= : Optional email for the author} 
        {--password= : Optional plaintext password; if omitted, a strong one is generated}';

    /** @var string */
    protected $description = 'Create a new FortiPlugin Author (with optional email/password).';

    public function handle(): int
    {
        try {
            // Resolve email (validate if provided; otherwise generate a unique one)
            $email = (string)($this->option('email') ?? '');
            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->error('Invalid email format.');
                    return self::FAILURE;
                }
                if (Author::query()->where('email', $email)->exists()) {
                    $this->error("Email already exists: {$email}");
                    return self::FAILURE;
                }
            } else {
                $email = $this->generateUniqueEmail();
            }

            // Resolve password (use provided or generate)
            $plainPassword = (string)($this->option('password') ?? '');
            if ($plainPassword === '') {
                $plainPassword = $this->generatePassword();
            }

            // Derive slug/name (simple, deterministic defaults)
            $local = Str::before($email, '@');
            $name = $this->humanizeHandle($local);
            $slug = $this->uniqueSlug('author-' . Str::slug($local));

            $author = new Author();
            $author->email = $email;
            $author->password = Hash::make($plainPassword);
            $author->name = $name;
            $author->slug = $slug;
            // $author->status = 'active';          // uncomment if your schema requires it
            // $author->verified = false;           // uncomment if your schema requires it
            $author->save();

            $this->info('Author created successfully.');
            $this->line('');
            $this->line('== Credentials =======================');
            $this->line('Email:    ' . $email);
            $this->line('Password: ' . $plainPassword);
            $this->line('======================================');
            $this->line('');
            $this->line('Keep this password safe. (Only shown once.)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Failed to create author: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function generateUniqueEmail(): string
    {
        // Example domain; adjust if you prefer using your app domain
        $domain = 'example.com';
        do {
            $local = 'author-' . Str::lower(Str::random(10));
            $email = "{$local}@{$domain}";
        } while (Author::query()->where('email', $email)->exists());

        return $email;
    }

    private function generatePassword(): string
    {
        // 20-char mixed password; customize to your password policy
        return Str::password(20);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Author::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function humanizeHandle(string $handle): string
    {
        // Turn "john-doe_123" into "John Doe 123"
        $h = preg_replace('/[-_]+/', ' ', $handle);
        return trim(Str::title((string)$h));
    }
}