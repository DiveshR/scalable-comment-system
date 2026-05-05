<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Minimal Post model — serves as the parent entity for comments.
 */
#[Fillable(['user_id', 'title', 'body'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * The user who authored this post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All comments on this post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Only root-level comments on this post (for paginated listing).
     * Uses idx_post_roots index: (post_id, depth, created_at).
     */
    public function rootComments(): HasMany
    {
        return $this->hasMany(Comment::class)
                    ->where('depth', 0)
                    ->latest();
    }
}
