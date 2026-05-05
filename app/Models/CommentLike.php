<?php

namespace App\Models;

use Database\Factories\CommentLikeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Write-once like record.
 *
 * The unique(user_id, comment_id) constraint at the DB level
 * guarantees idempotent like operations — no application-level
 * race condition can produce duplicate likes.
 *
 * No updated_at column since likes are immutable once created.
 */
#[Fillable(['user_id', 'comment_id'])]
class CommentLike extends Model
{
    /** @use HasFactory<CommentLikeFactory> */
    use HasFactory;

    /**
     * Disable updated_at — likes are write-once, never modified.
     */
    const UPDATED_AT = null;

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  Relationships
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * The user who liked the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The comment that was liked.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}
