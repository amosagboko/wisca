<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-configurable character domains (Faith, Integrity, Excellence, etc.)
        Schema::create('character_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');           // e.g. "Faith", "Integrity"
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            // rubric: [{"level":1,"label":"Beginning","description":"..."},{"level":2,...},...]
            $table->json('rubric')->nullable();
            // Minimum level that counts toward CE-02 numerator (default 3 = Secure)
            $table->unsignedTinyInteger('passing_level')->default(3);
            $table->integer('display_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Per-learner rating for each domain per term
        Schema::create('character_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('level');          // 1=Beginning, 2=Developing, 3=Secure, 4=Exemplary
            $table->text('notes')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learner_id', 'character_domain_id', 'academic_session_id', 'term_id'],
                'char_rating_unique');
            $table->index(['academic_session_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_ratings');
        Schema::dropIfExists('character_domains');
    }
};
