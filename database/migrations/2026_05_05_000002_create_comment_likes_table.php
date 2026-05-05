<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Write-once table — no updated_at needed.
     * Unique constraint enforces one-like-per-user at the DB level.
     */
    public function up(): void
    {
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('comment_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            // ── Prevent duplicate likes ────────────────────────────────
            // Also serves as a covering index for "has user liked?" checks:
            //   WHERE user_id = ? AND comment_id = ?
            $table->unique(['user_id', 'comment_id'], 'uq_user_comment_like');

            // ── Reverse lookup: "Who liked this comment?" ──────────────
            //   WHERE comment_id = ? ORDER BY created_at DESC
            $table->index(['comment_id', 'created_at'], 'idx_comment_likers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_likes');
    }
};
