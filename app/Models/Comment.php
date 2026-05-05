<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Flat-table comment model with root_id for O(1) thread retrieval.
 *
 * Hierarchy is stored denormalized:
 *   - parent_id → direct parent (adjacency list)
 *   - root_id   → thread ancestor (flattened tree)
 *   - depth     → nesting level (0, 1, or 2)
 *
 * This design eliminates recursive CTEs entirely.
 * All thread queries resolve to a single indexed SELECT.
 */
#[Fillable(['post_id', 'user_id', 'parent_id', 'root_id', 'depth', 'content'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Maximum allowed nesting depth.
     * 0 = root, 1 = reply, 2 = reply-to-reply.
     */
    public const int MAX_DEPTH = 2;

    // ── Relationships ──────────────────────────────────────────────

    /**
     * The post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The user who authored this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Direct parent comment (NULL for root comments).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct child replies to this comment.
     * Uses idx_parent_children index.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
                    ->orderBy('created_at');
    }

    /**
     * The root-level ancestor of this thread.
     */
    public function rootComment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    /**
     * All comments in this thread (only valid on root comments).
     * Uses idx_thread_replies index.
     *
     * Returns all descendants — NOT recursive. Single flat query:
     *   SELECT * FROM comments WHERE root_id = ? ORDER BY depth, created_at
     */
    public function thread(): HasMany
    {
        return $this->hasMany(self::class, 'root_id')
                    ->orderBy('depth')
                    ->orderBy('created_at');
    }

    /**
     * Likes on this comment.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * Only root-level comments (depth = 0).
     *
     * Usage: Comment::roots()->where('post_id', $id)->latest()->paginate()
     * Index: idx_post_roots (post_id, depth, created_at)
     */
    public function scopeRoots($query)
    {
        return $query->where('depth', 0);
    }

    /**
     * Comments for a specific post.
     *
     * Usage: Comment::forPost($postId)->roots()->latest()->paginate()
     */
    public function scopeForPost($query, int $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * All comments in a specific thread (by root_id).
     *
     * Usage: Comment::inThread($rootId)->get()
     * Index: idx_thread_replies (root_id, depth, created_at)
     */
    public function scopeInThread($query, int $rootId)
    {
        return $query->where('root_id', $rootId)
                     ->orderBy('depth')
                     ->orderBy('created_at');
    }

    // ── Depth Guard ────────────────────────────────────────────────

    /**
     * Check if this comment is at maximum nesting depth.
     * Use before allowing reply creation.
     */
    public function isMaxDepth(): bool
    {
        return $this->depth >= self::MAX_DEPTH;
    }

    /**
     * Determine the root_id for a new reply to this comment.
     *
     * If this comment IS a root (root_id = null), use this comment's id.
     * If this comment is already a reply, propagate its root_id.
     */
    public function resolveRootId(): int
    {
        return $this->root_id ?? $this->id;
    }

    // ── Counter Methods ────────────────────────────────────────────

    /**
     * Atomically increment the like counter.
     * Called after a CommentLike is created.
     */
    public function incrementLikeCount(): void
    {
        $this->increment('like_count');
    }

    /**
     * Atomically decrement the like counter.
     * Called after a CommentLike is deleted (unlike).
     */
    public function decrementLikeCount(): void
    {
        $this->decrement('like_count');
    }

    /**
     * Atomically increment the reply counter.
     * Called after a child comment is created.
     */
    public function incrementReplyCount(): void
    {
        $this->increment('reply_count');
    }

    /**
     * Atomically decrement the reply counter.
     * Called after a child comment is deleted.
     */
    public function decrementReplyCount(): void
    {
        $this->decrement('reply_count');
    }
}
