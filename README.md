# Scalable Comment System — Production Architecture

A high-performance, production-grade nested comment system built with **Laravel 13**, **MySQL**, and **Filament v4**. Designed for sub-100ms read performance at scale (10M+ rows) using a flat-tree architecture.

---

## 🚀 System Architecture

This system eliminates the "Recursive Query" problem found in standard comment systems by utilizing a **Flat-Tree** approach.

| Feature | Implementation | Performance |
|---|---|---|
| **Reads** | Single flat query using `root_id` | **O(log n)** index seek |
| **Nesting** | Max depth = 3 levels | Enforced by Service Layer |
| **N+1 Queries** | Eager-loading presets (7 queries total) | **O(1)** regardless of page size |
| **Counters** | Denormalized `like_count` & `reply_count` | Zero subqueries on read |
| **Authorization** | Centralized `PostPolicy` | Owner/Admin permissions |

---

## 📂 Project Flow & Panels

The application is divided into three distinct zones:

### 1. Public Feed (`/`)
*   Displays a global feed of all posts from **regular users**.
*   Real-time relative timestamps and author badges.
*   Guest users can browse posts but must register/login to interact.

### 2. User Panel (`/app`)
*   **Registration & Login**: Full auth flow included.
*   **My Posts**: Users can create, edit, and delete their own posts.
*   **Discussion**: Full interaction suite (Comment, Reply, Like, Delete).
*   **Dashboard**: Shows personal stats (total posts and comments) and latest activity.

### 3. Admin Panel (`/admin`)
*   **Global Management**: Admins can view all posts and manage comment visibility.
*   **User Moderation**: Admins can activate or deactivate user accounts (read-only mode for user details).
*   **Dashboard**: Shows global stats and top performing posts/users.

---

## 🔑 Login Credentials

Run `php artisan migrate:fresh --seed` to initialize the database with these accounts:

### Admin User
- **URL**: [http://localhost:8000/admin](http://localhost:8000/admin)
- **Email**: `admin@admin.com`
- **Password**: `password`

### Regular User
- **URL**: [http://localhost:8000/app](http://localhost:8000/app)
- **Email**: `user@user.com`
- **Password**: `password`

---

## 🛠️ Tech Stack & Implementation

### Database Schema
We use 4 composite indexes on the `comments` table to map exactly to query patterns:
1.  `idx_post_roots`: `(post_id, depth, created_at)`
2.  `idx_thread_replies`: `(root_id, depth, created_at)`
3.  `idx_parent_children`: `(parent_id, created_at)`
4.  `idx_user_comments`: `(user_id, created_at)`

### Service Layer (`CommentService.php`)
All business logic is isolated here to keep the UI clean:
*   `createComment()`: Atomic insertion.
*   `replyToComment()`: Calculates `depth`, propagates `root_id`, and increments parent's `reply_count`.
*   `toggleLike()`: Idempotent logic using DB-level unique constraints.
*   `deleteComment()`: Soft-deletes and syncs counters.

---

## ⚙️ Quick Start

```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Initialize Database & Assets
php artisan migrate:fresh --seed
php artisan filament:assets

# 4. Start Server
php artisan serve
npm run dev
```
