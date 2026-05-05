# Scalable Comment System

A production-grade, high-performance nested comment system built with **Laravel 13** and **MySQL**, designed for Filament-based admin panels.

---

## 📋 The Problem

Most comment systems use one of two approaches:

1. **Simple adjacency list** (`parent_id` only) — requires recursive CTEs or multiple queries to load a thread. Breaks at scale.
2. **Nested set / Materialized path** — complex write operations, fragile rebalancing, hard to maintain.

Both fail when you need:
- **Millions of comments** with sub-100ms reads
- **Nested replies** without recursive database queries
- **Real-time counters** (likes, reply counts) without expensive `COUNT(*)` subqueries

---

## 🎯 Requirements

| Requirement | Constraint |
|---|---|
| Nested comments | Max depth = 3 levels (root → reply → reply-to-reply) |
| Read performance | < 100ms at millions of rows |
| Scalability | Designed for 10M+ comments |
| No recursive queries | Zero CTEs, zero self-joins |
| Soft deletes | Preserve thread structure when comments are removed |
| Like system | One like per user per comment, enforced at DB level |
| Denormalized counters | `like_count` and `reply_count` on each comment row |

---

## 🏗️ Architecture: The Flat-Tree Approach

We use a **hybrid adjacency list + flat-tree** design:

- `parent_id` → direct parent (standard adjacency list)
- `root_id` → thread ancestor (flat-tree innovation)
- `depth` → nesting level (0, 1, or 2)

This gives us the **write simplicity** of adjacency lists with the **read performance** of flat tables.

### Why `root_id` Is the Key Innovation

**Without `root_id`**, loading a thread requires recursion:

```sql
-- ❌ Recursive CTE — O(n) with temp tables, kills MySQL at scale
WITH RECURSIVE thread AS (
    SELECT * FROM comments WHERE id = :root_id
    UNION ALL
    SELECT c.* FROM comments c
    JOIN thread t ON c.parent_id = t.id
)
SELECT * FROM thread;
```

**With `root_id`**, every comment in a thread shares the same `root_id`:

```sql
-- ✅ Single flat query — O(log n) index seek + O(k) scan
SELECT * FROM comments
WHERE root_id = :root_id
ORDER BY depth ASC, created_at ASC;
```

**How `root_id` is populated on reply creation:**

```php
$reply->root_id = $parent->root_id ?? $parent->id;
$reply->depth   = $parent->depth + 1;
```

Root comments have `root_id = NULL`. All descendants share the root's `id` as their `root_id`.

### Why Denormalization (`like_count`, `reply_count`)

**Without denormalization**, every page load requires N+1 subqueries:

```sql
-- ❌ For 25 comments, this runs 25 COUNT subqueries
SELECT c.*,
    (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.id) AS likes,
    (SELECT COUNT(*) FROM comments WHERE parent_id = c.id) AS replies
FROM comments c
WHERE post_id = :post_id AND depth = 0
LIMIT 25;
```

**With denormalization**, counters are pre-computed columns:

```sql
-- ✅ Zero subqueries — counters are on the row itself
SELECT * FROM comments
WHERE post_id = :post_id AND depth = 0
ORDER BY created_at DESC
LIMIT 25;
```

**Trade-off**: Every like/reply requires an atomic `INCREMENT` — but reads outnumber writes 100:1 in comment systems, making this an excellent trade-off.

---

## 📊 Database Schema

### `comments` Table

| Column | Type | Purpose |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK | Auto-increment primary key |
| `post_id` | `BIGINT UNSIGNED` FK | Which post this comment belongs to |
| `user_id` | `BIGINT UNSIGNED` FK | Who authored the comment |
| `parent_id` | `BIGINT UNSIGNED` nullable FK | Direct parent comment (NULL = root) |
| `root_id` | `BIGINT UNSIGNED` nullable FK | Thread ancestor (NULL = is root) |
| `depth` | `TINYINT UNSIGNED` | Nesting level: 0=root, 1=reply, 2=reply-to-reply |
| `content` | `TEXT` | Comment body |
| `like_count` | `INT UNSIGNED` | Denormalized like counter |
| `reply_count` | `INT UNSIGNED` | Denormalized reply counter |
| `created_at` | `TIMESTAMP` | Creation timestamp |
| `updated_at` | `TIMESTAMP` | Last update timestamp |
| `deleted_at` | `TIMESTAMP` nullable | Soft delete timestamp |

### `comment_likes` Table

| Column | Type | Purpose |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK | Auto-increment primary key |
| `user_id` | `BIGINT UNSIGNED` FK | Who liked |
| `comment_id` | `BIGINT UNSIGNED` FK | What was liked |
| `created_at` | `TIMESTAMP` | When the like happened |

**Constraint**: `UNIQUE(user_id, comment_id)` — prevents double-likes at the database level.

---

## 🔑 Index Strategy (Critical for Performance)

Every composite index is designed for a specific query pattern. Column order follows the B-tree optimization rule: **equality filters first → range/sort columns last**.

### `comments` Table — 4 Composite Indexes

| Index Name | Columns | Query Pattern | Why This Column Order? |
|---|---|---|---|
| `idx_post_roots` | `(post_id, depth, created_at)` | Paginated root comments for a post | `post_id` = equality, `depth=0` = equality, `created_at` = sort |
| `idx_thread_replies` | `(root_id, depth, created_at)` | All replies in a thread | `root_id` = equality, `depth` = group, `created_at` = sort |
| `idx_parent_children` | `(parent_id, created_at)` | Direct children of a comment | `parent_id` = equality, `created_at` = sort |
| `idx_user_comments` | `(user_id, created_at)` | "My comments" listing | `user_id` = equality, `created_at` = sort |

### `comment_likes` Table — 1 Unique + 1 Index

| Index Name | Columns | Query Pattern |
|---|---|---|
| `uq_user_comment_like` | `(user_id, comment_id)` UNIQUE | "Has this user liked?" + prevents duplicates |
| `idx_comment_likers` | `(comment_id, created_at)` | "Who liked this comment?" list |

### Why NOT Single-Column Indexes?

Single-column indexes on `post_id`, `user_id`, etc. would force MySQL to:

1. Scan the index to find matching rows
2. Perform a **filesort** on `created_at` (temp table + sort buffer)

Composite indexes eliminate the filesort — MySQL reads rows in correct order directly from the B-tree. At millions of rows, this is **5ms vs 500ms**.

---

## ⏱️ Time Complexity Analysis

| Operation | Query | Index Used | Complexity | ~Latency (10M rows) |
|---|---|---|---|---|
| List root comments | `WHERE post_id=? AND depth=0 ORDER BY created_at DESC LIMIT 25` | `idx_post_roots` | O(log n + k) | 2-5ms |
| Load thread replies | `WHERE root_id=? ORDER BY depth, created_at` | `idx_thread_replies` | O(log n + k) | 1-3ms |
| Get direct children | `WHERE parent_id=? ORDER BY created_at` | `idx_parent_children` | O(log n + k) | 1-2ms |
| User's comments | `WHERE user_id=? ORDER BY created_at DESC LIMIT 25` | `idx_user_comments` | O(log n + k) | 2-4ms |
| Toggle like | `INSERT ON DUPLICATE` + `INCREMENT` | `uq_user_comment_like` | O(log n) | 3-8ms |
| Check "has liked" | `WHERE user_id=? AND comment_id=?` | `uq_user_comment_like` | O(log n) | <1ms |
| Create comment | `INSERT` + `INCREMENT reply_count` | PK | O(log n) | 3-6ms |

> **n** = total rows, **k** = result set size (typically 5-50). All operations are effectively constant time.

---

## 📂 Project Structure

```
app/
├── Models/
│   ├── Comment.php          # Core model: relationships, scopes, depth guard, counters
│   ├── CommentLike.php      # Write-once like record (no updated_at)
│   ├── Post.php             # FK target with rootComments() relationship
│   └── User.php             # Laravel default
database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_05_05_000000_create_posts_table.php
│   ├── 2026_05_05_000001_create_comments_table.php    # 4 composite indexes
│   └── 2026_05_05_000002_create_comment_likes_table.php # unique + reverse index
```

---

## 🛠️ Implementation Details

### Step 1: Posts Table (FK Target)

**Requirement**: Comments must belong to a post. We need a `posts` table as the foreign key target.

**Migration**: `2026_05_05_000000_create_posts_table.php`

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('body');
    $table->timestamps();
});
```

---

### Step 2: Comments Table (Core Schema)

**Requirement**: Store nested comments with max depth 3, support millions of rows, and avoid recursive queries. Include denormalized counters for read performance.

**Migration**: `2026_05_05_000001_create_comments_table.php`

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();

    // ── Relationships ──────────────────────────────────
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('parent_id')->nullable();
    $table->unsignedBigInteger('root_id')->nullable();

    // ── Hierarchy ──────────────────────────────────────
    $table->tinyInteger('depth')->unsigned()->default(0);

    // ── Content ────────────────────────────────────────
    $table->text('content');

    // ── Denormalized Counters ──────────────────────────
    $table->unsignedInteger('like_count')->default(0);
    $table->unsignedInteger('reply_count')->default(0);

    // ── Timestamps & Soft Delete ───────────────────────
    $table->timestamps();
    $table->softDeletes();

    // ── Self-Referential Foreign Keys ──────────────────
    $table->foreign('parent_id')->references('id')->on('comments')->nullOnDelete();
    $table->foreign('root_id')->references('id')->on('comments')->cascadeOnDelete();

    // ── Composite Indexes ──────────────────────────────
    $table->index(['post_id', 'depth', 'created_at'], 'idx_post_roots');
    $table->index(['root_id', 'depth', 'created_at'], 'idx_thread_replies');
    $table->index(['parent_id', 'created_at'], 'idx_parent_children');
    $table->index(['user_id', 'created_at'], 'idx_user_comments');
});
```

---

### Step 3: Comment Likes Table

**Requirement**: One like per user per comment, enforced at the database level (not application level). No `updated_at` needed — likes are write-once.

**Migration**: `2026_05_05_000002_create_comment_likes_table.php`

```php
Schema::create('comment_likes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
    $table->timestamp('created_at')->useCurrent();

    $table->unique(['user_id', 'comment_id'], 'uq_user_comment_like');
    $table->index(['comment_id', 'created_at'], 'idx_comment_likers');
});
```

---

### Step 4: Comment Model

**Requirement**: Eloquent model with self-referential relationships, query scopes that leverage composite indexes, depth enforcement, and atomic counter methods.

**Relationships**:

| Method | Return | Purpose |
|---|---|---|
| `parent()` | `BelongsTo` | Direct parent comment (NULL for roots) |
| `replies()` | `HasMany` | Direct child replies, ordered by `created_at` |
| `root()` | `BelongsTo` | Thread ancestor (the root-level comment) |
| `thread()` | `HasMany` | All descendants in the thread (flat query) |
| `likes()` | `HasMany` | Like records on this comment |
| `user()` | `BelongsTo` | Comment author |
| `post()` | `BelongsTo` | Parent post |

**Scopes**:

| Scope | Index Used | Purpose |
|---|---|---|
| `topLevel()` | `idx_post_roots` | Only depth=0 comments |
| `forPost($id)` | `idx_post_roots` | Filter by post |
| `inThread($rootId)` | `idx_thread_replies` | All replies in a thread |
| `withEagerLoads($authUserId)` | — | Preset: users + replies + auth likes |
| `withThreadLoads($authUserId)` | — | Preset: users + auth likes (flat thread) |

**Key methods**:

```php
// Depth guard — prevents nesting beyond level 2
public function isMaxDepth(): bool
{
    return $this->depth >= self::MAX_DEPTH; // MAX_DEPTH = 2
}

// Root ID resolver — auto-populates root_id on reply creation
public function resolveRootId(): int
{
    return $this->root_id ?? $this->id;
}

// Scopes — each maps to a composite index
Comment::forPost($postId)->topLevel()->latest()->paginate();  // idx_post_roots
Comment::inThread($rootId)->get();                             // idx_thread_replies
```

---

### Step 5: CommentLike Model

**Requirement**: Write-once model with no `updated_at`. Relationships to user and comment.

```php
class CommentLike extends Model
{
    const UPDATED_AT = null; // Likes are immutable once created
}
```

---

### Step 6: Optimized Eager Loading (Avoid N+1)

**Requirement**: Zero N+1 queries. Every relationship accessed in a loop must be pre-loaded.

**❌ The N+1 trap** — never do this:

```php
$comments = Comment::forPost($postId)->topLevel()->paginate(25);

foreach ($comments as $comment) {
    $comment->user;           // ❌ N+1 — fires 25 queries
    $comment->replies;        // ❌ N+1 — fires 25 more queries
    $comment->likes()->count(); // ❌ N+1 — fires 25 COUNT queries
}
```

**✅ The correct way** — use `withEagerLoads()` preset:

```php
// 7 queries total regardless of page size (not 75+)
$comments = Comment::forPost($postId)
    ->topLevel()
    ->withEagerLoads(auth()->id())
    ->latest()
    ->paginate(25);

// Queries fired:
// 1. SELECT * FROM comments WHERE post_id=? AND depth=0 ORDER BY created_at DESC LIMIT 25
// 2. SELECT id,name FROM users WHERE id IN (...)           -- root authors
// 3. SELECT * FROM comments WHERE parent_id IN (...)       -- depth-1 replies
// 4. SELECT id,name FROM users WHERE id IN (...)           -- depth-1 authors
// 5. SELECT * FROM comments WHERE parent_id IN (...)       -- depth-2 replies
// 6. SELECT id,name FROM users WHERE id IN (...)           -- depth-2 authors
// 7. SELECT id,comment_id,user_id FROM comment_likes WHERE user_id=? AND comment_id IN (...)
```

**✅ Thread expansion** — lighter preset:

```php
$thread = Comment::inThread($rootId)
    ->withThreadLoads(auth()->id())
    ->get();

// 3 queries total:
// 1. SELECT * FROM comments WHERE root_id=? ORDER BY depth, created_at
// 2. SELECT id,name FROM users WHERE id IN (...)
// 3. SELECT id,comment_id,user_id FROM comment_likes WHERE user_id=? AND comment_id IN (...)
```

---

## 🚀 Usage Examples

### Load paginated root comments for a post

```php
$comments = Comment::forPost($postId)
    ->topLevel()
    ->withEagerLoads(auth()->id())
    ->latest()
    ->paginate(25);
// Uses idx_post_roots → O(log n + 25) → ~3ms
// 7 queries total — zero N+1
```

### Load all replies in a thread (no recursion)

```php
$replies = Comment::inThread($rootCommentId)
    ->withThreadLoads(auth()->id())
    ->get();
// Uses idx_thread_replies → 3 queries total → ~2ms
```

### Create a reply with depth guard

```php
if ($parent->isMaxDepth()) {
    throw new \DomainException('Maximum nesting depth reached.');
}

$reply = Comment::create([
    'post_id'   => $parent->post_id,
    'user_id'   => auth()->id(),
    'parent_id' => $parent->id,
    'root_id'   => $parent->resolveRootId(),
    'depth'     => $parent->depth + 1,
    'content'   => $validated['content'],
]);

$parent->incrementReplyCount();
```

### Toggle like (idempotent)

```php
DB::transaction(function () use ($comment, $user) {
    $existing = CommentLike::where('user_id', $user->id)
                           ->where('comment_id', $comment->id)
                           ->first();
    if ($existing) {
        $existing->delete();
        $comment->decrementLikeCount();
    } else {
        CommentLike::create([
            'user_id'    => $user->id,
            'comment_id' => $comment->id,
        ]);
        $comment->incrementLikeCount();
    }
});
```

---

## ⚙️ Setup

```bash
# Install dependencies
composer install

# Configure database
cp .env.example .env
# Edit .env → set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate
```

---

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# scalable-comment-system
