<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema Design: Flat-table with root_id for O(1) thread retrieval.
     * Max depth = 3 (0=root, 1=reply, 2=reply-to-reply).
     * Denormalized counters avoid N+1 subqueries on read-heavy pages.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // ── Relationships ──────────────────────────────────────────
            $table->foreignId('post_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Direct parent — NULL means this is a root comment.
            $table->unsignedBigInteger('parent_id')->nullable();

            // Thread ancestor — NULL means this IS the root.
            // All descendants in a thread share the same root_id,
            // enabling single-query thread loading without recursion.
            $table->unsignedBigInteger('root_id')->nullable();

            // ── Hierarchy Metadata ─────────────────────────────────────
            // 0 = root comment, 1 = reply, 2 = reply-to-reply (max)
            $table->tinyInteger('depth')->unsigned()->default(0);

            // ── Content ────────────────────────────────────────────────
            $table->text('content');

            // ── Denormalized Counters ──────────────────────────────────
            // Maintained via atomic INCREMENT/DECREMENT on write.
            // Eliminates COUNT(*) subqueries on every read.
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);

            // ── Timestamps & Soft Delete ───────────────────────────────
            $table->timestamps();
            $table->softDeletes();

            // ── Self-Referential Foreign Keys ──────────────────────────
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('comments')
                  ->nullOnDelete();

            $table->foreign('root_id')
                  ->references('id')
                  ->on('comments')
                  ->cascadeOnDelete();

            // ── Composite Indexes ──────────────────────────────────────
            //
            // Each index is designed for a specific query pattern.
            // Column order follows: equality filters → range/sort columns.
            //
            // 1. Paginated root comments for a post (main listing)
            //    WHERE post_id = ? AND depth = 0 ORDER BY created_at DESC
            $table->index(['post_id', 'depth', 'created_at'], 'idx_post_roots');

            // 2. All replies within a thread (thread expansion)
            //    WHERE root_id = ? ORDER BY depth ASC, created_at ASC
            $table->index(['root_id', 'depth', 'created_at'], 'idx_thread_replies');

            // 3. Direct children of a specific comment
            //    WHERE parent_id = ? ORDER BY created_at ASC
            $table->index(['parent_id', 'created_at'], 'idx_parent_children');

            // 4. All comments by a user ("My Comments" in Filament)
            //    WHERE user_id = ? ORDER BY created_at DESC
            $table->index(['user_id', 'created_at'], 'idx_user_comments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
