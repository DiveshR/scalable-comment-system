<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * ┌──────────────────────────────────────────────────────────────────┐
 * │  EAGER LOADING GUIDE — Avoid N+1 at all costs                   │
 * ├──────────────────────────────────────────────────────────────────┤
 * │                                                                  │
 * │  Listing root comments:                                          │
 * │    Comment::forPost($id)->topLevel()                             │
 * │           ->with(['user:id,name', 'replies.user:id,name'])       │
 * │           ->withCount('likes')                                   │
 * │           ->latest()->paginate(25);                              │
 * │                                                                  │
 * │  Loading a full thread:                                          │
 * │    Comment::inThread($rootId)                                    │
 * │           ->with(['user:id,name'])                               │
 * │           ->get();                                               │
 * │                                                                  │
 * │  Check if auth user liked (batch):                               │
 * │    ->with(['likes' => fn($q) => $q->where('user_id', auth()->id())])  │
 * │                                                                  │
 * │  NEVER do this in a loop:                                        │
 * │    ❌ $comment->replies  (triggers N+1)                          │
 * │    ❌ $comment->likes()->count()  (triggers N+1)                 │
 * │    ✅ Use ->with() or ->withCount() BEFORE the query             │
 * └──────────────────────────────────────────────────────────────────┘
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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Relationships
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * The post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The user who authored this comment.
     *
     * Eager load with select to avoid pulling unnecessary columns:
     *   ->with('user:id,name')
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Direct parent comment (NULL for root comments).
     *
     * Index: idx_parent_children (parent_id, created_at)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct child replies to this comment.
     *
     * Index: idx_parent_children (parent_id, created_at)
     *
     * ⚠️  Always eager load — never access in a loop:
     *   ->with(['replies.user:id,name'])
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
                    ->orderBy('created_at');
    }

    /**
     * The root-level ancestor of this thread.
     *
     * Root comments have root_id = NULL (they ARE the root).
     * All descendants point to the thread's first comment.
     */
    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    /**
     * All comments in this thread (only meaningful on root comments).
     *
     * Index: idx_thread_replies (root_id, depth, created_at)
     *
     * Returns all descendants in a single flat query — NOT recursive:
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
     *
     * For counts:  ->withCount('likes')  — uses like_count if available
     * For auth check: ->with(['likes' => fn($q) => $q->where('user_id', $userId)])
     */
    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Scopes
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Only top-level (root) comments where depth = 0.
     *
     * Index: idx_post_roots (post_id, depth, created_at)
     *
     * Usage:
     *   Comment::forPost($postId)->topLevel()->latest()->paginate(25);
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->where('depth', 0);
    }

    /**
     * Comments for a specific post.
     *
     * Index: idx_post_roots (post_id, depth, created_at)
     *
     * Usage:
     *   Comment::forPost($postId)->topLevel()
     *          ->with(['user:id,name', 'replies.user:id,name'])
     *          ->latest()->paginate(25);
     */
    public function scopeForPost(Builder $query, int $postId): Builder
    {
        return $query->where('post_id', $postId);
    }

    /**
     * All comments in a specific thread (by root_id).
     *
     * Index: idx_thread_replies (root_id, depth, created_at)
     *
     * Usage:
     *   Comment::inThread($rootId)->with('user:id,name')->get();
     */
    public function scopeInThread(Builder $query, int $rootId): Builder
    {
        return $query->where('root_id', $rootId)
                     ->orderBy('depth')
                     ->orderBy('created_at');
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Optimized Eager Loading Presets
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Eager load for listing root comments (main page).
     *
     * Loads:
     *   - user (id + name only — avoids pulling email, password, etc.)
     *   - replies with their users (one level deep)
     *   - replies of replies with their users (two levels deep)
     *
     * This turns 3N+1 queries into exactly 7 queries regardless of page size:
     *   1. comments (root)
     *   2. users (root authors)
     *   3. comments (depth-1 replies)
     *   4. users (depth-1 authors)
     *   5. comments (depth-2 replies)
     *   6. users (depth-2 authors)
     *   7. comment_likes (auth user's likes for "liked" state)
     *
     * Usage:
     *   Comment::forPost($postId)->topLevel()
     *          ->withEagerLoads(auth()->id())
     *          ->latest()->paginate(25);
     */
    public function scopeWithEagerLoads(Builder $query, ?int $authUserId = null): Builder
    {
        $query->with([
            'user:id,name',
            'replies' => function (HasMany $q) {
                $q->with([
                    'user:id,name',
                    'replies.user:id,name',
                ]);
            },
        ]);

        // Batch-check if the authenticated user has liked each comment.
        // Avoids N separate "has user liked?" queries.
        if ($authUserId) {
            $query->with([
                'likes' => fn (HasMany $q) => $q->where('user_id', $authUserId)
                                                 ->select(['id', 'comment_id', 'user_id']),
            ]);
        }

        return $query;
    }

    /**
     * Eager load for thread view (expanding a thread).
     *
     * Lighter than withEagerLoads — thread is flat, no nested replies needed.
     *
     * Usage:
     *   Comment::inThread($rootId)->withThreadLoads()->get();
     */
    public function scopeWithThreadLoads(Builder $query, ?int $authUserId = null): Builder
    {
        $query->with('user:id,name');

        if ($authUserId) {
            $query->with([
                'likes' => fn (HasMany $q) => $q->where('user_id', $authUserId)
                                                 ->select(['id', 'comment_id', 'user_id']),
            ]);
        }

        return $query;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Depth Guard
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

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

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Atomic Counter Methods
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * Atomically increment the like counter.
     * Uses UPDATE comments SET like_count = like_count + 1 WHERE id = ?
     */
    public function incrementLikeCount(): void
    {
        $this->increment('like_count');
    }

    /**
     * Atomically decrement the like counter.
     */
    public function decrementLikeCount(): void
    {
        $this->decrement('like_count');
    }

    /**
     * Atomically increment the reply counter.
     */
    public function incrementReplyCount(): void
    {
        $this->increment('reply_count');
    }

    /**
     * Atomically decrement the reply counter.
     */
    public function decrementReplyCount(): void
    {
        $this->decrement('reply_count');
    }
}
